<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Lecturer;
use App\Models\PhotocopyPlace;
use App\Models\Schedule;
use App\Services\Search\QueryIntentParserService;
use App\Services\Search\SmartPhotocopySearchService;
use App\Services\Search\SpatialLandmarkResolverService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCopilotService
{
    /**
     * Konfigurasi koneksi ClassAI Router (OpenAI-Compatible) REST API.
     */
    protected string $endpoint;
    protected string $apiKey;
    protected string $modelId;
    protected int $timeout;
    protected int $maxTurns;
    protected int $maxTokens;
    protected float $temperature;

    /**
     * Data interaksi kaya & ringkasan multi-opsi untuk respon JSON controller
     */
    protected array $lastFacultySummary = [];
    protected array $lastSchedules = [];
    protected array $lastQuickReplies = [];
    protected array $lastMapActions = [];
    protected ?array $lastPrimaryMapAction = null;
    protected array $currentMentionedFaculties = [];
    protected array $lastCards = [];
    protected array $lastActions = [];
    protected ?string $lastAdvisoryNote = null;
    protected array $lastRuntimeContext = [];

    protected QueryIntentParserService $intentParser;
    protected SmartPhotocopySearchService $photocopySearch;
    protected SpatialLandmarkResolverService $spatialResolver;

    /**
     * Detektor Kemiripan Singkatan & Fakultas USU (Human Confusion Map + Levenshtein Distance <= 2).
     *
     * @param string $inputCodeOrText
     * @return array
     */
    public static function findSimilarBuildingsOrFaculties(string $inputCodeOrText): array
    {
        $inputUpper = strtoupper(trim($inputCodeOrText));
        $facultyAliases = SparqlService::getFacultyAliases();

        // 1. Kamus Pasangan Rawan Tertukar (Human Confusion Map)
        $confusionMap = [
            'FEB'      => [
                ['code' => 'FIB', 'name' => 'Fakultas Ilmu Budaya', 'reason' => 'Beda 1 huruf (E vs I), paling sering tertukar'],
                ['code' => 'MM-FEB', 'name' => 'Magister Manajemen USU (MM-FEB)', 'reason' => 'Gedung pascasarjana rumpun ekonomi FEB'],
                ['code' => 'FKEP', 'name' => 'Fakultas Keperawatan', 'reason' => 'Sama-sama berawalan huruf F dan berakhiran konsonan'],
            ],
            'FIB'      => [
                ['code' => 'FEB', 'name' => 'Fakultas Ekonomi dan Bisnis', 'reason' => 'Beda 1 huruf (I vs E), sering tertukar'],
                ['code' => 'FISIP', 'name' => 'Fakultas Ilmu Sosial dan Ilmu Politik', 'reason' => 'Rumpun ilmu sosial humaniora'],
                ['code' => 'FH', 'name' => 'Fakultas Hukum', 'reason' => 'Rumpun ilmu sosial humaniora'],
            ],
            'FISIP'    => [
                ['code' => 'FPSI', 'name' => 'Fakultas Psikologi', 'reason' => 'Singkatan berawalan F dan berakhiran SI'],
                ['code' => 'FIB', 'name' => 'Fakultas Ilmu Budaya', 'reason' => 'Rumpun ilmu sosial humaniora'],
                ['code' => 'FASILKOM-TI', 'name' => 'Fakultas Ilmu Komputer dan TI', 'reason' => 'Letak gedung berdekatan di koridor tengah kampus'],
            ],
            'FPSI'     => [
                ['code' => 'FISIP', 'name' => 'Fakultas Ilmu Sosial dan Ilmu Politik', 'reason' => 'Sering tertukar karena singkatan mirip'],
                ['code' => 'FP', 'name' => 'Fakultas Pertanian', 'reason' => 'Singkatan mirip berawalan FP'],
                ['code' => 'FK', 'name' => 'Fakultas Kedokteran', 'reason' => 'Rumpun ilmu kesehatan/psikologi'],
            ],
            'FK'       => [
                ['code' => 'FKG', 'name' => 'Fakultas Kedokteran Gigi', 'reason' => 'Sama-sama rumpun medis/kedokteran'],
                ['code' => 'FKM', 'name' => 'Fakultas Kesehatan Masyarakat', 'reason' => 'Rumpun ilmu kesehatan'],
                ['code' => 'FKEP', 'name' => 'Fakultas Keperawatan', 'reason' => 'Rumpun ilmu kesehatan'],
                ['code' => 'FF', 'name' => 'Fakultas Farmasi', 'reason' => 'Rumpun ilmu kesehatan'],
            ],
            'FKG'      => [
                ['code' => 'FK', 'name' => 'Fakultas Kedokteran', 'reason' => 'Kedokteran Umum'],
                ['code' => 'FKM', 'name' => 'Fakultas Kesehatan Masyarakat', 'reason' => 'Kesehatan Masyarakat'],
                ['code' => 'FKEP', 'name' => 'Fakultas Keperawatan', 'reason' => 'Keperawatan'],
            ],
            'FKM'      => [
                ['code' => 'FK', 'name' => 'Fakultas Kedokteran', 'reason' => 'Kedokteran Umum'],
                ['code' => 'FKG', 'name' => 'Fakultas Kedokteran Gigi', 'reason' => 'Kedokteran Gigi'],
                ['code' => 'FKEP', 'name' => 'Fakultas Keperawatan', 'reason' => 'Keperawatan'],
            ],
            'FT'       => [
                ['code' => 'FH', 'name' => 'Fakultas Hukum', 'reason' => 'Singkatan 2 huruf berawalan F'],
                ['code' => 'FP', 'name' => 'Fakultas Pertanian', 'reason' => 'Fakultas Pertanian'],
                ['code' => 'FF', 'name' => 'Fakultas Farmasi', 'reason' => 'Fakultas Farmasi'],
                ['code' => 'FASILKOM-TI', 'name' => 'Fakultas Ilmu Komputer dan TI', 'reason' => 'Sama-sama rumpun teknik/teknologi'],
            ],
            'GKB'      => [
                ['code' => 'PERPUS-USU', 'name' => 'Perpustakaan USU', 'reason' => 'Sering tertukar karena sama-sama fasilitas kuliah bersama'],
                ['code' => 'AUDITORIUM', 'name' => 'Auditorium', 'reason' => 'Gedung umum pertemuan kampus USU'],
            ],
        ];

        $candidates = [];
        $added = [];

        if (isset($confusionMap[$inputUpper])) {
            foreach ($confusionMap[$inputUpper] as $item) {
                $candidates[] = $item;
                $added[] = $item['code'];
            }
        }

        // 2. Levenshtein Distance (<= 2)
        foreach ($facultyAliases as $fKey => $fInfo) {
            if ($fKey === $inputUpper || in_array($fKey, $added, true)) {
                continue;
            }
            $dist = levenshtein($inputUpper, $fKey);
            if ($dist <= 2) {
                $candidates[] = [
                    'code'   => $fKey,
                    'name'   => $fInfo['name'],
                    'reason' => "Ejaan singkatan sangat mirip (jarak perbedaan {$dist} huruf)",
                ];
                $added[] = $fKey;
            }
        }

        return $candidates;
    }

    /**
     * Katalog Ringkas Kode & Nama Gedung Resmi USU untuk Injeksi System Prompt
     */
    public static function getCompactBuildingCatalog(): string
    {
        return "KATALOG LENGKAP KODE & NAMA GEDUNG RESMI KAMPUS USU:\n" .
            "- FEB: Fakultas Ekonomi dan Bisnis (Gedung FEB, MM-FEB)\n" .
            "- FIB: Fakultas Ilmu Budaya (Gedung FIB)\n" .
            "- FASILKOM / FASILKOM-TI: Fakultas Ilmu Komputer dan TI (FASILKOM-TI, FASILKOM-B, GD-FASILKOM)\n" .
            "- FT: Fakultas Teknik (FT-MESIN, FT-KIMIA, FT-LINGKUNGAN)\n" .
            "- FH: Fakultas Hukum\n" .
            "- FK: Fakultas Kedokteran\n" .
            "- FKG: Fakultas Kedokteran Gigi\n" .
            "- FKM: Fakultas Kesehatan Masyarakat\n" .
            "- FP: Fakultas Pertanian (FP-TEKNIK)\n" .
            "- FMIPA / MIPA: Perpustakaan Cabang MIPA\n" .
            "- FISIP: Fakultas Ilmu Sosial dan Ilmu Politik (FISIP, TAX-CENTER, MSP-FISIP)\n" .
            "- FPSI: Fakultas Psikologi\n" .
            "- FKEP: Fakultas Keperawatan\n" .
            "- FF: Fakultas Farmasi\n" .
            "- GKB: Gedung Kuliah Bersama\n" .
            "- VOKASI: Fakultas Vokasi\n" .
            "- PERPUS-USU: Perpustakaan USU\n" .
            "- AUDITORIUM: Auditorium USU\n" .
            "- REKTORAT: Gedung Rektorat USU";
    }

    /**
     * Konstruktor: Inisialisasi kredensial Bearer Token dan konfigurasi ClassAI Router.
     */
    public function __construct(
        ?QueryIntentParserService $intentParser = null,
        ?SmartPhotocopySearchService $photocopySearch = null,
        ?SpatialLandmarkResolverService $spatialResolver = null
    ) {
        $this->endpoint    = config('copilot.endpoint', 'https://router.classai.id/v1/chat/completions');
        $this->apiKey      = (string) config('copilot.api_key', '');
        $this->modelId     = config('copilot.model_id', 'claude-haiku-4.5');
        $this->timeout     = (int) config('copilot.timeout', 120);
        $this->maxTurns    = (int) config('copilot.max_turns', 4);
        $this->maxTokens   = (int) config('copilot.max_tokens', 1024);
        $this->temperature = (float) config('copilot.temperature', 0.2);

        $this->intentParser    = $intentParser ?? app(QueryIntentParserService::class);
        $this->photocopySearch = $photocopySearch ?? app(SmartPhotocopySearchService::class);
        $this->spatialResolver = $spatialResolver ?? app(SpatialLandmarkResolverService::class);
    }

    /**
     * Eksekusi percakapan Tanya Ucup dengan arsitektur Local-First Grounded Engines
     * + optional ClassAI Router enrichment (zero HTTP 500 bila offline/key kosong).
     *
     * @param string $userMessage Pesan baru dari pengguna
     * @param array $history Riwayat percakapan sebelumnya
     * @param array $runtimeContext Konteks koordinat GPS & halaman aktif
     * @return array Respon terformat lengkap
     */
    public function chat(string $userMessage, array $history = [], array $runtimeContext = []): array
    {
        // Reset state interaksi kaya untuk putaran baru
        $this->lastFacultySummary = [];
        $this->lastSchedules = [];
        $this->lastQuickReplies = [];
        $this->lastMapActions = [];
        $this->lastPrimaryMapAction = null;
        $this->lastCards = [];
        $this->lastActions = [];
        $this->lastAdvisoryNote = null;
        $this->lastRuntimeContext = $runtimeContext;

        // 0. Guardrail Read-Only (mencegah modifikasi jadwal lewat AI)
        if (preg_match('/\b(hapus|delete|tambah|insert|buatkan\s+jadwal|update|ubah|ganti|drop|kprs|edit\s+jadwal)\b/i', $userMessage)
            && !preg_match('/\b(pulpen|fotocopy|print|jilid|kertas)\b/i', $userMessage)) {
            return [
                'reply'           => "⚠️ **Mode Read-Only Aktif**\nMaaf, Ucup adalah pemandu & asisten pencarian kampus yang bersifat baca-saja. Perubahan atau penghapusan data jadwal tidak dapat dilakukan melalui asisten ini.",
                'intent'          => 'guardrail_readonly',
                'advisory_note'   => null,
                'cards'           => [],
                'actions'         => [],
                'map_action'      => null,
                'faculty_summary' => [],
                'schedules'       => [],
                'quick_replies'   => [],
                'map_actions'     => [],
                'grounded_source' => 'local_guardrail',
                'usage'           => null,
            ];
        }

        // 1. Jalankan QueryIntentParserService terlebih dahulu (Deterministik, 100% Grounded)
        $parsedIntent = $this->intentParser->parse($userMessage);
        $primaryIntent = $parsedIntent['primary_intent'] ?? 'general_campus_qa';

        if ($primaryIntent === 'smart_fotocopy_search') {
            return $this->handleGroundedPhotocopyChat($userMessage, $parsedIntent, $runtimeContext);
        }

        if ($primaryIntent === 'spatial_building_finder') {
            return $this->handleGroundedSpatialChat($userMessage, $parsedIntent, $runtimeContext);
        }

        if ($primaryIntent === 'route_navigation') {
            return $this->handleGroundedRouteChat($userMessage, $parsedIntent, $runtimeContext);
        }

        /** @var SparqlService $sparqlService */
        $sparqlService = app(SparqlService::class);
        $parsedUser = $sparqlService->parseConversationalQuery($userMessage);
        $this->currentMentionedFaculties = $parsedUser['detected_faculties'];

        // 2. Untuk query jadwal/umum, coba ClassAI LLM hanya jika API Key terkonfigurasi;
        //    bila key kosong atau error jaringan, fallback mulus ke Local Grounded Responder (HTTP 200).
        if (!empty($this->apiKey)) {
            try {
                return $this->runClassAiLoop($userMessage, $history, $sparqlService, $parsedUser, $parsedIntent, $primaryIntent);
            } catch (\Throwable $e) {
                Log::warning('[AiCopilotService] ClassAI unavailable, switching cleanly to local grounded responder: ' . $e->getMessage());
            }
        }

        return $this->respondLocallyForScheduleOrGeneral($userMessage, $history, $sparqlService, $parsedUser, $parsedIntent, $primaryIntent);
    }

    /**
     * Pipeline ClassAI OpenAI-compatible multi-turn tool loop (dipanggil hanya bila API key tersedia).
     */
    protected function runClassAiLoop(
        string $userMessage,
        array $history,
        SparqlService $sparqlService,
        array $parsedUser,
        array $parsedIntent,
        string $primaryIntent
    ): array {

        // 2. Siapkan array percakapan sesuai format OpenAI Chat Completions API
        $messages = [];

        // Masukkan System Prompt sebagai pesan dengan peran 'system'
        $systemPrompt = $this->buildSystemPrompt();
        $messages[] = [
            'role'    => 'system',
            'content' => $systemPrompt,
        ];

        // Batasi riwayat percakapan agar hemat token dan memori
        $historyLimit = config('copilot.history_limit', 8);
        $recentHistory = array_slice($history, -$historyLimit);

        foreach ($recentHistory as $msg) {
            if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'])) {
                $messages[] = [
                    'role'    => $msg['role'],
                    'content' => (string) $msg['content'],
                ];
            }
        }

        // 2b. Analisis Sensitivitas Percakapan (Human Sensitivity, Context Carry-Over & Stem Expansion)
        $hasDoubt = $parsedUser['has_doubt'] ?? false;
        $cleanKeyword = $parsedUser['clean_keyword'] ?? '';

        // Context Carry-Over: Jika pengguna mengajukan koreksi/lanjutan tanpa kata kunci mata kuliah baru
        $carriedTopic = null;
        if (empty($cleanKeyword) && !empty($recentHistory)) {
            for ($i = count($recentHistory) - 1; $i >= 0; $i--) {
                $hMsg = $recentHistory[$i];
                if (($hMsg['role'] ?? '') === 'user') {
                    $hParsed = $sparqlService->parseConversationalQuery($hMsg['content'] ?? '');
                    if (!empty($hParsed['clean_keyword'])) {
                        $carriedTopic = $hParsed['clean_keyword'];
                        break;
                    }
                }
            }
        }

        // Cek kandidat fakultas/gedung yang mirip jika pengguna menyebut singkatan atau tampak ragu
        $similarSuggestions = [];
        foreach ($this->currentMentionedFaculties as $mf) {
            $simList = self::findSimilarBuildingsOrFaculties($mf);
            if (!empty($simList)) {
                $top = $simList[0];
                $similarSuggestions[] = "Singkatan '{$mf}' sangat mirip dengan '{$top['code']}' ({$top['name']}) [Alasan: {$top['reason']}]";
            }
        }

        // Cek apakah ada kata lisan yang perlu diekspansi akar katanya (contoh: "mikir" -> "pemikiran")
        $stemHints = [];
        $stemMap = SparqlService::getColloquialStemMap();
        $tokens = preg_split('/\s+/', $cleanKeyword, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $t) {
            $lowerT = mb_strtolower($t, 'UTF-8');
            if (isset($stemMap[$lowerT])) {
                $synonyms = implode(', ', $stemMap[$lowerT]);
                $stemHints[] = "Kata lisan '{$t}' merujuk pada topik akademis: [{$synonyms}]";
            }
        }

        // Susun Catatan Konteks Cerdas untuk Model
        $contextNotes = [];
        if ($carriedTopic) {
            $contextNotes[] = "- KONTEKS LANJUTAN: Topik mata kuliah sebelumnya adalah '{$carriedTopic}'. Pengguna tidak menyebut topik baru, sehingga topik ini WAJIB digunakan untuk pencarian lanjutan!";
        }
        if (!empty($stemHints)) {
            $contextNotes[] = "- EKSPANSI BAHASA LISAN: " . implode('. ', $stemHints) . ". Gunakan akar kata akademis ini saat mencari jadwal (jangan mencari kata lisan mentah)!";
        }
        if (!empty($similarSuggestions)) {
            $contextNotes[] = "- KEMIRIPAN SINGKATAN FAKULTAS: " . implode('. ', $similarSuggestions) . ". Jika pengguna ragu atau di fakultas yang disebut tidak ada jadwal, tebak dan tawarkan secara proaktif ke fakultas yang mirip tersebut!";
        }

        if (!empty($contextNotes)) {
            $messages[] = [
                'role'    => 'system',
                'content' => "[PETUNJUK SENSITIVITAS & KONTEKS PERCAKAPAN]\n" . implode("\n", $contextNotes) . "\nTebak secara cerdas, proaktif, dan tawarkan opsi mata kuliah yang cocok!",
            ];
        }

        // Tambahkan pesan pengguna terkini
        $messages[] = [
            'role'    => 'user',
            'content' => $userMessage,
        ];

        // 3. Persiapkan parameter OpenAI Function Calling
        $tools = $this->getToolDefinitions();
        $turn = 0;
        $finalReplyText = '';
        $accumulatedUsage = ['inputTokens' => 0, 'outputTokens' => 0, 'totalTokens' => 0];

        // Perulangan Tool Loop (Maksimal 4 Putaran)
        while ($turn < $this->maxTurns) {
            $turn++;

            $payload = [
                'model'       => $this->modelId,
                'messages'    => $messages,
                'temperature' => $this->temperature,
                'max_tokens'  => $this->maxTokens,
                'tools'       => $tools,
                'tool_choice' => 'auto',
            ];

            // Kirim request HTTP POST ke ClassAI Router menggunakan Bearer Token
            // withoutVerifying() disertakan untuk mencegah cURL error 60 SSL Certificate pada localhost Windows
            // connectTimeout(30) dan timeout(120) untuk kehandalan jaringan
            $response = Http::withToken($this->apiKey)
                ->withoutVerifying()
                ->connectTimeout(30)
                ->timeout(120)
                ->retry(2, 500)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, $payload);

            // Penanganan Error HTTP
            if ($response->failed()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? $response->body() ?? 'Terjadi kesalahan saat memanggil ClassAI.';

                Log::error("[AiCopilotService] HTTP Request ke ClassAI Gagal [HTTP {$response->status()}]: {$errorMessage}", [
                    'endpoint' => $this->endpoint,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);

                throw new \RuntimeException("Layanan ClassAI mengalami kendala [HTTP {$response->status()}]: {$errorMessage}");
            }

            $responseData = $response->json();

            // Akumulasi penggunaan token (Format OpenAI: prompt_tokens, completion_tokens, total_tokens)
            if (isset($responseData['usage'])) {
                $accumulatedUsage['inputTokens']  += ($responseData['usage']['prompt_tokens'] ?? 0);
                $accumulatedUsage['outputTokens'] += ($responseData['usage']['completion_tokens'] ?? 0);
                $accumulatedUsage['totalTokens']  += ($responseData['usage']['total_tokens'] ?? 0);
            }

            $choice = $responseData['choices'][0] ?? [];
            $choiceMessage = $choice['message'] ?? [];

            // Masukkan balasan model ke dalam riwayat percakapan sesi saat ini
            $messages[] = $choiceMessage;

            // Jika model meminta pemanggilan tool (tool_calls ada dan terisi)
            if (!empty($choiceMessage['tool_calls']) && is_array($choiceMessage['tool_calls'])) {
                foreach ($choiceMessage['tool_calls'] as $toolCall) {
                    $toolCallId  = $toolCall['id'] ?? uniqid('call_');
                    $toolName    = $toolCall['function']['name'] ?? '';
                    $toolArgsRaw = $toolCall['function']['arguments'] ?? '{}';
                    $toolInput   = is_string($toolArgsRaw) ? (json_decode($toolArgsRaw, true) ?: []) : (array) $toolArgsRaw;

                    Log::info("[AiCopilotService] Putaran {$turn}: Memanggil tool '{$toolName}'", [
                        'toolCallId' => $toolCallId,
                        'input'      => $toolInput,
                    ]);

                    try {
                        $toolResultData = $this->executeTool($toolName, $toolInput);
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content'      => json_encode($toolResultData, JSON_UNESCAPED_UNICODE),
                        ];
                    } catch (\Throwable $e) {
                        Log::error("[AiCopilotService] Gagal menjalankan tool '{$toolName}': " . $e->getMessage());
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content'      => json_encode([
                                'status'  => 'error',
                                'message' => 'Gagal mengambil data dari database: ' . $e->getMessage(),
                            ]),
                        ];
                    }
                }

                // Lanjutkan ke putaran berikutnya untuk memberikan hasil tool ke model
                continue;
            }

            // Jika model selesai memberikan teks jawaban final
            if (!empty($choiceMessage['content'])) {
                $finalReplyText .= $choiceMessage['content'];
            }

            break;
        }

        if (empty($finalReplyText)) {
            $finalReplyText = 'Maaf, saya tidak dapat menemukan jawaban yang sesuai saat ini. Silakan ajukan pertanyaan lain.';
        }

        // 4. Ekstraksi Tag [MAP_ACTION: {...}] dari Teks Respon
        $extractedMapAction = null;
        if (preg_match('/\[MAP_ACTION:\s*(\{.*?\})\]/s', $finalReplyText, $matches)) {
            $jsonParsed = json_decode($matches[1], true);
            if (is_array($jsonParsed)) {
                $extractedMapAction = $jsonParsed;
            }
            // Bersihkan tag dari teks yang akan dibaca pengguna agar tampilan rapi
            $finalReplyText = trim(preg_replace('/\[MAP_ACTION:\s*\{.*?\}\]/s', '', $finalReplyText));
        }

        $finalMapAction = $extractedMapAction ?? $this->lastPrimaryMapAction;

        return [
            'reply'           => $finalReplyText,
            'intent'          => $primaryIntent,
            'advisory_note'   => $this->lastAdvisoryNote,
            'cards'           => $this->lastCards,
            'actions'         => $this->lastActions,
            'map_action'      => $finalMapAction,
            'faculty_summary' => $this->lastFacultySummary,
            'schedules'       => $this->lastSchedules,
            'quick_replies'   => $this->lastQuickReplies,
            'map_actions'     => $this->lastMapActions,
            'grounded_source' => 'classai_grounded',
            'usage'           => $accumulatedUsage,
        ];
    }

    /**
     * Handler Grounded 1: Pencarian Cerdas Fotokopi & ATK lintas 32 toko (zero hallucination).
     */
    protected function handleGroundedPhotocopyChat(string $userMessage, array $parsedIntent, array $runtimeContext): array
    {
        $search = $this->photocopySearch->search($userMessage, [
            'parsed' => $parsedIntent,
            'lat'    => $runtimeContext['user_lat'] ?? null,
            'lng'    => $runtimeContext['user_lng'] ?? null,
            'limit'  => 6,
        ]);

        $results = $search['results'] ?? [];
        $advisory = $search['advisory_note'] ?? null;
        $anchorLabel = $search['anchor']['label'] ?? 'Pusat Kampus USU';

        $cards = array_map(fn (array $item): array => [
            'type' => 'fotocopy_item',
            'data' => $item,
        ], $results);

        $actions = [];
        $quickReplies = [];
        $mapAction = null;

        if (!empty($results)) {
            $top = $results[0];
            $actions = [
                ['label' => 'Pesan di ' . $top['store']['name'], 'url' => $top['actions']['order_url'], 'type' => 'order'],
                ['label' => 'Rute GPS ke Toko', 'url' => $top['actions']['route_url'], 'type' => 'route'],
                ['label' => 'Buka Pencarian Fotokopi', 'url' => route('fotocopy.index', ['q' => $userMessage]), 'type' => 'browse'],
            ];
            $mapAction = [
                'type'          => 'start_route',
                'target_key'    => $top['actions']['route_key'],
                'building_name' => $top['store']['name'],
                'lat'           => $top['store']['latitude'],
                'lng'           => $top['store']['longitude'],
            ];

            $lines = [];
            if ($advisory) {
                $lines[] = "ℹ️ {$advisory}";
            }
            $lines[] = "Berikut rekomendasi terbaik di sekitar **{$anchorLabel}** (berdasarkan harga hemat, jarak real-time, dan jam buka WIB sekarang):";
            foreach (array_slice($results, 0, 4) as $i => $it) {
                $no = $i + 1;
                $op = $it['store']['operational_status'] ?? null;
                $statusText = $op ? ($op['status_label'] . ' • ' . $op['detail_text']) : (!empty($it['store']['is_open_now']) ? 'Buka' : 'Tutup');
                $lines[] = "{$no}. **{$it['name']}** — **{$it['formatted_price']}/{$it['unit']}** di *{$it['store']['name']}* (~{$it['store']['distance_meters']} m / {$it['store']['walk_minutes']} mnt jalan • {$statusText})";
            }
            $lines[] = "\nKlik **Pesan di Toko** untuk langsung memesan atau **Rute GPS** untuk navigasi in-app menuju toko.";
            $reply = implode("\n", $lines);

            $quickReplies = [
                ['label' => 'Urutkan Paling Dekat', 'query' => $userMessage . ' terdekat'],
                ['label' => 'Cari yang Buka Sekarang', 'query' => $userMessage . ' buka sekarang'],
            ];
        } else {
            $activeStoreCount = PhotocopyPlace::where('is_active', true)->count();
            $reply = $advisory ?: "Maaf, Ucup belum menemukan produk atau jasa yang cocok di katalog {$activeStoreCount} mitra fotokopi USU untuk \"{$userMessage}\". Coba gunakan kata kunci lebih singkat seperti **pulpen**, **print warna**, **jilid hardcover**, atau **kertas HVS**.";
            $quickReplies = [
                ['label' => 'Pulpen murah terdekat', 'query' => 'cari pulpen murah terdekat'],
                ['label' => 'Print warna murah', 'query' => 'print warna murah terdekat'],
            ];
        }

        return [
            'reply'           => $reply,
            'intent'          => 'smart_fotocopy_search',
            'advisory_note'   => $advisory,
            'cards'           => $cards,
            'actions'         => $actions,
            'map_action'      => $mapAction,
            'faculty_summary' => [],
            'schedules'       => [],
            'quick_replies'   => $quickReplies,
            'map_actions'     => [],
            'grounded_source' => 'smart_photocopy_engine',
            'usage'           => null,
        ];
    }

    /**
     * Handler Grounded 2: Penalaran Spasial Gedung Kampus & Clue Arah Hadap.
     */
    protected function handleGroundedSpatialChat(string $userMessage, array $parsedIntent, array $runtimeContext): array
    {
        $resolved = $this->spatialResolver->resolve($userMessage, [
            'parsed' => $parsedIntent,
            'limit'  => 5,
        ]);

        $candidates = $resolved['candidates'] ?? [];
        $advisory = $resolved['advisory_note'] ?? null;

        $cards = array_map(fn (array $cand): array => [
            'type' => 'spatial_building',
            'data' => $cand,
        ], $candidates);

        $actions = [];
        $mapAction = null;
        $quickReplies = [];

        if (!empty($candidates)) {
            $top = $candidates[0];
            $mapAction = [
                'type'          => 'focus_building',
                'building_id'   => $top['building_id'],
                'building_name' => $top['name'],
                'lat'           => $top['latitude'],
                'lng'           => $top['longitude'],
                'room_id'       => null,
                'room_name'     => null,
                'schedule_id'   => null,
            ];
            $actions = [
                ['label' => "Lihat {$top['code']} di Peta", 'url' => $top['actions']['focus_url'], 'type' => 'focus'],
                ['label' => "Rute GPS ke {$top['code']}", 'url' => $top['actions']['route_url'], 'type' => 'route'],
            ];

            $lines = [];
            if ($advisory) {
                $lines[] = "ℹ️ {$advisory}";
            }
            $lines[] = "Berdasarkan analisis posisi dan orientasi hadap gedung dari patokan yang kamu sebutkan, ini **Top " . count($candidates) . " kemungkinan gedung/kelasmu**:";
            foreach ($candidates as $i => $c) {
                $no = $i + 1;
                $op = $c['operational_status'] ?? null;
                $opLine = $op ? " • Status Kelas ({$op['hours_label']}): **{$op['status_label']}** ({$op['detail_text']})" : '';
                $lines[] = "{$no}. **{$c['name']} ({$c['code']})** — **{$c['confidence_percent']}% cocok**{$opLine}\n   {$c['spatial_reason']}";
            }
            $lines[] = "\nPilih **Lihat di Peta** untuk memeriksa denah/ruangan gedung atau **Rute GPS ke Sini** untuk langsung dituntun in-app.";
            $reply = implode("\n", $lines);

            foreach (array_slice($candidates, 0, 2) as $c) {
                $quickReplies[] = [
                    'label' => "Cek Jadwal di {$c['code']}",
                    'query' => "Lihat semua jadwal kuliah di gedung {$c['code']}",
                ];
            }
        } else {
            $reply = $advisory ?: 'Maaf, Ucup membutuhkan minimal satu patokan gedung (seperti FIB, FMIPA, GKB, atau Perpustakaan USU) agar bisa menebak gedung kelasmu.';
        }

        return [
            'reply'           => $reply,
            'intent'          => 'spatial_building_finder',
            'advisory_note'   => $advisory,
            'cards'           => $cards,
            'actions'         => $actions,
            'map_action'      => $mapAction,
            'faculty_summary' => [],
            'schedules'       => [],
            'quick_replies'   => $quickReplies,
            'map_actions'     => [],
            'grounded_source' => 'spatial_landmark_engine',
            'usage'           => null,
        ];
    }

    /**
     * Handler Grounded 3: Permintaan Navigasi / Rute Langsung In-App.
     */
    protected function handleGroundedRouteChat(string $userMessage, array $parsedIntent, array $runtimeContext): array
    {
        // If photocopy item or shop mentioned, route to best matching fotocopy
        if (!empty($parsedIntent['item_terms']) || preg_match('/\b(fotocopy|percetakan|atk)\b/i', $userMessage)) {
            $fc = $this->handleGroundedPhotocopyChat($userMessage, $parsedIntent, $runtimeContext);
            $fc['intent'] = 'route_navigation';
            return $fc;
        }

        $mentioned = $parsedIntent['mentioned_buildings'] ?? [];
        if (!empty($mentioned)) {
            $b = $mentioned[0];
            $buildingModel = Building::with('rooms')->find($b['id']);
            $roomsCount = $buildingModel ? $buildingModel->rooms->count() : 0;
            $opStatus = app(OperationalStatusService::class)->evaluateBuildingStatus($buildingModel);

            $card = [
                'type' => 'spatial_building',
                'data' => [
                    'building_id'        => (int) $b['id'],
                    'code'               => (string) $b['code'],
                    'name'               => (string) $b['name'],
                    'faculty'            => (string) ($b['faculty'] ?: 'Universitas Sumatera Utara'),
                    'category'           => (string) ($b['category'] ?: 'Gedung Kampus'),
                    'latitude'           => (float) $b['latitude'],
                    'longitude'          => (float) $b['longitude'],
                    'confidence_percent' => 100,
                    'spatial_reason'     => "Siap menavigasikan rute in-app ke **{$b['name']} ({$b['code']})** menggunakan graf jalan & pejalan kaki USU.",
                    'operational_status' => $opStatus,
                    'facing_compass'     => '-',
                    'distances'          => [],
                    'rooms_count'        => $roomsCount,
                    'has_360_tour'       => false,
                    'matched_schedules'  => [],
                    'actions'            => [
                        'focus_url' => url('/map?focus_building=' . $b['id']),
                        'route_url' => url('/map?route_to=building:' . $b['id']),
                        'route_key' => 'building:' . $b['id'],
                    ],
                ],
            ];

            return [
                'reply'           => "🧭 Siap! Klik **Rute GPS ke Sini** di bawah untuk membuka jalur navigasi in-app langsung menuju **{$b['name']} ({$b['code']})**.",
                'intent'          => 'route_navigation',
                'advisory_note'   => null,
                'cards'           => [$card],
                'actions'         => [
                    ['label' => "Rute GPS ke {$b['code']}", 'url' => url('/map?route_to=building:' . $b['id']), 'type' => 'route'],
                    ['label' => "Lihat {$b['code']} di Peta", 'url' => url('/map?focus_building=' . $b['id']), 'type' => 'focus'],
                ],
                'map_action'      => [
                    'type'          => 'start_route',
                    'target_key'    => 'building:' . $b['id'],
                    'building_id'   => (int) $b['id'],
                    'building_name' => (string) $b['name'],
                    'lat'           => (float) $b['latitude'],
                    'lng'           => (float) $b['longitude'],
                ],
                'faculty_summary' => [],
                'schedules'       => [],
                'quick_replies'   => [],
                'map_actions'     => [],
                'grounded_source' => 'route_navigation_engine',
                'usage'           => null,
            ];
        }

        return $this->handleGroundedSpatialChat($userMessage, $parsedIntent, $runtimeContext);
    }

    /**
     * Handler Grounded 4 (Offline / LLM Fallback): Jadwal Kuliah, Dosen, & Detail Gedung Lokal.
     */
    protected function respondLocallyForScheduleOrGeneral(
        string $userMessage,
        array $history,
        SparqlService $sparqlService,
        array $parsedUser,
        array $parsedIntent,
        string $primaryIntent
    ): array {
        // 1. Cek permintaan jadwal pribadi ("jadwal saya", "kelasku")
        if (preg_match('/\b(jadwal\s+saya|jadwal\s+kuliahku|kelasku|my\s+schedule)\b/i', $userMessage)) {
            $mySched = $this->toolAmbilJadwalSaya(['hari' => $parsedIntent['day'] ?? null]);
            if (empty($mySched['authenticated'])) {
                return [
                    'reply'           => "📅 Untuk menampilkan **Jadwal Kuliah Pribadi** milikmu, silakan **Masuk (Login)** terlebih dahulu menggunakan akun mahasiswa USUClassMap.\n\nNamun kalau kamu belum login, kamu tetap bisa ketik nama mata kuliah, dosen, atau clue lokasi gedungnya (contoh: *\"Jadwal Pancasila hari Senin\"* atau *\"di depan FIB samping FMIPA\"*).",
                    'intent'          => 'schedule_search',
                    'advisory_note'   => null,
                    'cards'           => [],
                    'actions'         => [],
                    'map_action'      => null,
                    'faculty_summary' => [],
                    'schedules'       => [],
                    'quick_replies'   => [
                        ['label' => 'Jadwal MKWU Senin', 'query' => 'Jadwal Pancasila hari Senin'],
                    ],
                    'map_actions'     => [],
                    'grounded_source' => 'local_grounded_academic',
                    'usage'           => null,
                ];
            }

            $total = (int) ($mySched['total_kelas'] ?? 0);
            $cards = array_map(fn ($s) => ['type' => 'schedule', 'data' => $s], array_slice($this->lastSchedules, 0, 8));
            return [
                'reply'           => $total > 0
                    ? "📅 Ditemukan **{$total} jadwal kuliah pribadi** Anda. Klik **Lihat di Peta** pada kartu di bawah untuk langsung menyorot ruang kelasnya."
                    : '📅 Anda belum memiliki jadwal kelas terdaftar untuk semester ini.',
                'intent'          => 'schedule_search',
                'advisory_note'   => null,
                'cards'           => $cards,
                'actions'         => [],
                'map_action'      => $this->lastSchedules[0]['map_action'] ?? null,
                'faculty_summary' => [],
                'schedules'       => $this->lastSchedules,
                'quick_replies'   => [],
                'map_actions'     => [],
                'grounded_source' => 'local_grounded_academic',
                'usage'           => null,
            ];
        }

        // 2. Cek pencarian dosen eksplisit
        if (preg_match('/\b(dosen|pengajar|prof|dr\.?)\b/i', $userMessage)) {
            $cleanDosen = trim(preg_replace('/\b(info|cari|jadwal|dosen|mengajar|pengajar|dimana)\b/i', '', $userMessage) ?? '');
            if (strlen($cleanDosen) >= 3) {
                $dosenRes = $this->toolCariDosen(['nama_dosen' => $cleanDosen]);
                if (($dosenRes['status'] ?? '') === 'success' && !empty($dosenRes['dosen'])) {
                    $firstDosen = $dosenRes['dosen'][0];
                    $firstJadwal = $firstDosen['jadwal_mengajar'][0] ?? null;
                    $cards = [];
                    foreach (array_slice($firstDosen['jadwal_mengajar'], 0, 6) as $jm) {
                        $this->lastSchedules[] = [
                            'schedule_id'   => $jm['schedule_id'],
                            'course_name'   => $jm['mata_kuliah'],
                            'course_code'   => $jm['kode_mk'],
                            'sks'           => null,
                            'day'           => $jm['hari'],
                            'time'          => $jm['jam'],
                            'class_group'   => $jm['kelas'],
                            'room_name'     => $jm['ruangan'],
                            'floor'         => 1,
                            'lecturer'      => $firstDosen['nama'],
                            'building_name' => $jm['gedung'],
                            'building_code' => '',
                            'map_action'    => [
                                'type'          => 'focus_room',
                                'building_name' => $jm['gedung'],
                                'lat'           => $jm['latitude'],
                                'lng'           => $jm['longitude'],
                                'room_name'     => $jm['ruangan'],
                                'schedule_id'   => $jm['schedule_id'],
                            ],
                        ];
                    }
                    $cards = array_map(fn ($s) => ['type' => 'schedule', 'data' => $s], $this->lastSchedules);
                    return [
                        'reply'           => "👨‍🏫 Ditemukan profil dosen **{$firstDosen['nama']}** dengan **{$firstDosen['total_jadwal']} jadwal mengajar**.",
                        'intent'          => 'schedule_search',
                        'advisory_note'   => null,
                        'cards'           => $cards,
                        'actions'         => [],
                        'map_action'      => $firstJadwal ? [
                            'type'          => 'focus_room',
                            'building_name' => $firstJadwal['gedung'],
                            'lat'           => $firstJadwal['latitude'],
                            'lng'           => $firstJadwal['longitude'],
                            'room_name'     => $firstJadwal['ruangan'],
                        ] : null,
                        'faculty_summary' => [],
                        'schedules'       => $this->lastSchedules,
                        'quick_replies'   => [],
                        'map_actions'     => [],
                        'grounded_source' => 'local_grounded_lecturer',
                        'usage'           => null,
                    ];
                }
            }
        }

        // 3. Pencarian Jadwal via toolCariJadwalKuliah (mendukung carry-over history + SPARQL/SQLite)
        $keyword = !empty($parsedIntent['course_terms'])
            ? implode(' ', $parsedIntent['course_terms'])
            : ($parsedUser['core_keyword'] ?? '');

        if (empty($keyword) && !empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if (($history[$i]['role'] ?? '') === 'user') {
                    $prev = $sparqlService->parseConversationalQuery((string) ($history[$i]['content'] ?? ''));
                    if (!empty($prev['core_keyword'])) {
                        $keyword = $prev['core_keyword'];
                        break;
                    }
                }
            }
        }

        $mentioned = $parsedIntent['mentioned_buildings'] ?? [];
        $facParam = !empty($this->currentMentionedFaculties)
            ? implode(',', $this->currentMentionedFaculties)
            : (!empty($mentioned) ? (string) $mentioned[0]['code'] : '');

        if ($keyword !== '' || ($parsedIntent['day'] ?? null) !== null || $facParam !== '') {
            $toolOut = $this->toolCariJadwalKuliah([
                'keyword'  => $keyword ?: $facParam,
                'fakultas' => $facParam,
                'hari'     => $parsedIntent['day'] ?? null,
            ]);

            $total = (int) ($toolOut['total_ditemukan'] ?? 0);
            if ($total > 0) {
                $cards = array_map(fn ($s) => ['type' => 'schedule', 'data' => $s], array_slice($this->lastSchedules, 0, 8));
                $summaryText = $toolOut['ringkasan_agregasi'] ?? "Ditemukan {$total} jadwal kuliah yang cocok.";

                return [
                    'reply'           => "📚 {$summaryText}",
                    'intent'          => 'schedule_search',
                    'advisory_note'   => null,
                    'cards'           => $cards,
                    'actions'         => [],
                    'map_action'      => $this->lastPrimaryMapAction,
                    'faculty_summary' => $this->lastFacultySummary,
                    'schedules'       => $this->lastSchedules,
                    'quick_replies'   => $this->lastQuickReplies,
                    'map_actions'     => $this->lastMapActions,
                    'grounded_source' => 'sparql_sqlite_grounded',
                    'usage'           => null,
                ];
            }

            if (!empty($mentioned)) {
                return $this->handleGroundedRouteChat($userMessage, $parsedIntent, []);
            }
        }

        // 4. Fallback Ramah Terpandu (zero error)
        return [
            'reply'           => "👋 Halo! Aku **Ucup**, pemandu kampus USU di USUClassMap. Aku siap membantumu mencari:\n• **Jadwal kuliah, ruang kelas & dosen** (contoh: *\"Jadwal Pancasila hari Senin\"*)\n• **Tebak gedung dari patokan sekitar** (contoh: *\"kelas saya di depan FIB di samping FMIPA\"*)\n• **Cari ATK, print, jilid & toko fotokopi termurah/terdekat** (contoh: *\"cari pulpen pink murah terdekat\"*)",
            'intent'          => $primaryIntent,
            'advisory_note'   => null,
            'cards'           => [],
            'actions'         => [],
            'map_action'      => null,
            'faculty_summary' => [],
            'schedules'       => [],
            'quick_replies'   => [
                ['label' => 'Cari pulpen murah terdekat', 'query' => 'cari pulpen pink murah terdekat'],
                ['label' => 'Kelas depan FIB samping FMIPA', 'query' => 'saya ingatnya kelas saya di depan FIB di samping FMIPA'],
                ['label' => 'Jadwal Pancasila Senin', 'query' => 'Jadwal Pancasila hari Senin'],
            ],
            'map_actions'     => [],
            'grounded_source' => 'local_assistant_guide',
            'usage'           => null,
        ];
    }

    /**
     * Membangun System Prompt terperinci dengan persona Ucup (Pemandu Kampus USU),
     * aturan guardrail (100% Read-Only), format tag aksi peta, dan konteks user aktif.
     *
     * @return string
     */
    public function buildSystemPrompt(): string
    {
        $user = Auth::user();
        $isLoggedIn = Auth::check();
        $currentTime = Carbon::now('Asia/Jakarta')->translatedFormat('l, d F Y - H:i') . ' WIB';
        $buildingCatalog = self::getCompactBuildingCatalog();

        $userContext = $isLoggedIn
            ? "Status: Terautentikasi (Login)\n- Nama: {$user->name}\n- NIM: " . ($user->nim ?? '-') . "\n- Semester: " . ($user->semester ?? '-') . "\n- Peran: " . ($user->role ?? 'mahasiswa')
            : "Status: Tamu (Belum Login)";

        return <<<PROMPT
Anda adalah "Ucup", pemandu kampus USU yang ramah, sigap, dan cerdas untuk platform USUClassMap (Universitas Sumatera Utara).

IDENTITAS & KARAKTERISTIK:
1. Anda bersahabat seperti teman penjelajah kampus USU, ringkas, solutif, berbahasa Indonesia natural yang rapi dan sopan.
2. Anda ahli dalam denah kampus USU, lokasi gedung kuliah, jadwal mata kuliah, ruang kelas, dosen pengajar, dan layanan mitra fotokopi sekitar kampus.
3. Selalu prioritaskan data faktual dari pemanggilan alat (tools). Jangan pernah mengarang jadwal atau lokasi gedung yang tidak tercatat.

{$buildingCatalog}

HUMAN SENSITIVITY & DETEKSI SALAH INGAT SINGKATAN (SANGAT PENTING):
1. Mahasiswa sering salah ingat atau tertukar singkatan fakultas karena bunyi atau ejaannya mirip, terutama:
   - FEB ↔ FIB (Fakultas Ilmu Budaya), MM-FEB, FKEP
   - FIB ↔ FEB, FISIP, FH
   - FISIP ↔ FPSI, FIB, FASILKOM
   - FPSI ↔ FISIP, FP, FK
   - FK ↔ FKG, FKM, FKEP, FF
   - FKG ↔ FK, FKM, FKEP
   - FKM ↔ FK, FKG, FKEP
   - FT ↔ FH, FP, FF, FASILKOM-TI
   - GKB ↔ PERPUS-USU, AUDITORIUM
2. SIKAP PROAKTIF & PANTANG PASIF:
   - Jika pengguna menyebut kata "mirip", "kayaknya", "ketukar", "bukan", atau tampak ragu/salah ingat dengan nama gedung (misalnya: "yang mirip feb gitu"), JANGAN PERNAH menjawab pasif atau sekadar bertanya balik ("Saya belum tahu, ada petunjuk lain?").
   - TEBAK SECARA CERDAS & PROAKTIF: Langsung sebutkan kemungkinan terdekat (misal: "Maksud kamu mungkin FIB (Fakultas Ilmu Budaya) yang namanya mirip FEB?"), lalu langsung tawarkan jadwal mata kuliah yang cocok dari hasil pencarian di fakultas mirip tersebut!
3. JANGAN MENELAN KATA LISAN MENTAH-MENTAH:
   - Pahami kalimat utuh pengguna. Kata seperti "dia mikir" adalah ungkapan lisan untuk kata "pemikiran" / "berpikir" / "filsafat", BUKAN mata kuliah bernama "mikir". Begitu pula "ngomong" (speaking/komunikasi), "denger" (listening), "ngoding" (pemrograman), "hitung" (matematika/akuntansi).
4. MULTI-TURN CONTEXT CARRY-OVER:
   - Jika pengguna memberikan sanggahan atau koreksi singkat (contoh: "bukannn maksudnya yang mirip feb gitu", "kalau yang di FIB?"), pertahankan topik mata kuliah dari giliran sebelumnya (misal "literacy") dan hubungkan dengan fakultas kandidat yang mirip (FIB).

PANDUAN EKSTRAKSI PENCARIAN & INTENSI MAHASISWA:
1. Mahasiswa sering menggunakan bahasa sehari-hari, kata berulang (seperti "literacy-literacy"), typo ringan ("atay" untuk "atau"), mengingat nama mata kuliah secara tidak lengkap, atau menyisipkan kata pengisi (contoh: "di fib atay feb yah yang ada literacy-literacy gitu", "perkembangan islam saya ingatnya").
2. Kamus Singkatan Fakultas & Gedung USU:
   - FIB: Fakultas Ilmu Budaya
   - FEB: Fakultas Ekonomi dan Bisnis
   - FASILKOM / FASILKOM-TI / TI / ILKOM: Fakultas Ilmu Komputer dan Teknologi Informasi
   - FT: Fakultas Teknik
   - FH: Fakultas Hukum
   - FK: Fakultas Kedokteran
   - FKG: Fakultas Kedokteran Gigi
   - FKM: Fakultas Kesehatan Masyarakat
   - FP: Fakultas Pertanian
   - FMIPA / MIPA: Fakultas Matematika dan Ilmu Pengetahuan Alam
   - FISIP: Fakultas Ilmu Sosial dan Ilmu Politik
   - FPSI / PSI: Fakultas Psikologi
   - FKEP: Fakultas Keperawatan
   - FF / FARMASI: Fakultas Farmasi
   - FHUT / FKEHUTANAN: Fakultas Kehutanan
   - GKB: Gedung Kuliah Bersama
   - VOKASI: Fakultas Vokasi
3. Saat memanggil tool `cari_jadwal_kuliah`:
   - Ekstrak kata kunci topik mata kuliah inti (misal "literacy", "perkembangan islam", "pemikiran").
   - Jika pengguna menyebutkan fakultas spesifik (misal "FIB", "FEB"), sertakan juga di parameter `fakultas`.
4. ANTI-BLINDSPOT & PANDUAN MULTI-FAKULTAS (SANGAT PENTING):
   - Selalu teliti membaca field `ringkasan_agregasi` dari hasil tool `cari_jadwal_kuliah` sebelum menjawab!
   - DILARANG KERAS mengklaim "tidak ada di fakultas X" kecuali data `ringkasan_agregasi` memang menyatakan 0 jadwal untuk fakultas tersebut.
   - Jika pengguna membandingkan dua atau lebih fakultas (misal: "di fib atay feb yah yang ada literacy-literacy gitu"):
     * Jawab secara faktual dan transparan: Sebutkan bahwa mata kuliah tersebut ADA di Fakultas Ilmu Budaya (FIB) dengan jumlah jadwalnya dan sebutkan contoh mata kuliah (misal Basic Listening Literacy Skills, Basic Reading Comprehension Literacy Skills).
     * Sebutkan secara jelas bahwa di Fakultas Ekonomi dan Bisnis (FEB) TIDAK ADA jadwal mata kuliah tersebut.
     * Informasikan juga gedung lain yang memilikinya (misalnya di Gedung Kuliah Bersama / GKB dan FASILKOM-TI).
     * Tag [MAP_ACTION: ...] wajib menyorot fakultas yang relevan dan memiliki jadwal tersebut (dalam contoh ini, sorot Fakultas Ilmu Budaya / FIB).
5. PANDUAN PERMINTAAN SPESIFIK SATU FAKULTAS / GEDUNG:
   - Jika pengguna meminta jadwal khusus di satu fakultas tertentu (misal: "Lihat jadwal literacy khusus di FIB"), fokuskan jawaban HANYA pada fakultas yang diminta (FIB).
   - JANGAN mencampurkan, menghitung, atau menyebutkan jadwal dari gedung lain (seperti GKB) di jawaban Anda.
6. PANDUAN FORMAT & LARANGAN TABEL MARKDOWN:
   - DILARANG KERAS menggunakan format tabel Markdown (`| kolom | kolom |` atau `|---|---|`) karena tabel Markdown mentah terlalu sempit dan terpotong pada widget chat floating.
   - Gunakan kalimat pengantar singkat (1–2 kalimat) dan daftar poin ringkas (bullet points).
   - Seluruh daftar detail jadwal lengkap (ruangan, lantai, dosen, jam, tombol peta) sudah otomatis disediakan sistem dalam bentuk kartu interaktif, jadi Anda CUKUP memberikan ringkasan dan sorotan 2-3 mata kuliah penting.

ATURAN KEAMANAN & GUARDRAIL KETAT (100% READ-ONLY):
1. Anda HANYA memiliki izin BACA (Read-Only) ke sistem dan database USUClassMap.
2. DILARANG KERAS menyetujui, menjanjikan, atau mencoba aksi mutasi: menambah jadwal baru, menghapus kelas, mengubah jadwal dosen, mengubah koordinat peta, atau menyentuh struktur database.
3. Jika ada permintaan manipulasi data, perubahan KPRS/SIA, atau permintaan password/kredensial sistem, tolak dengan sopan dan tegas: nyatakan bahwa Anda adalah asisten pencarian informasi dengan izin baca saja.

INTEGRASI AKSI PETA LEAFLET [MAP_ACTION]:
Kapan pun Anda menyebutkan atau merekomendasikan gedung, ruangan, atau jadwal kuliah yang relevan ditampilkan pada peta, Anda WAJIB menyertakan SATU tag aksi peta di bagian PALING BAWAH respon Anda dengan format JSON persis:
[MAP_ACTION: {"type": "focus_building", "building_id": <id_gedung_atau_null>, "building_name": "<nama_gedung>", "lat": <latitude_angka>, "lng": <longitude_angka>, "room_id": <id_ruangan_atau_null>, "room_name": "<nama_ruangan_atau_null>", "schedule_id": <id_jadwal_atau_null>, "course_code": "<kode_mk_atau_null>", "course_name": "<nama_mk_atau_null>"}]

Panduan Pengisian Parameter Map Action:
- "building_name": Nama gedung (misal: "Gedung Fasilkom-TI", "Gedung FIB").
- "lat" & "lng": Koordinat geolokasi gedung (diambil dari hasil tool).
- "room_name" & "room_id": Isi jika pertanyaan merujuk pada ruang kelas tertentu.
- "schedule_id", "course_code", & "course_name": Isi jika merujuk pada mata kuliah tertentu agar sistem peta otomatis melakukan double sorting dan menyorot baris jadwal tersebut.
- Jika pengguna hanya bertanya umum tanpa gedung/ruang spesifik, jangan sertakan tag ini.

KONTEKS PENGGUNA SAAT INI:
- Waktu Lokal Kampus: {$currentTime}
- {$userContext}

Jika pengguna belum login dan menanyakan "jadwal saya" atau "jadwal kuliahku", sampaikan dengan ramah bahwa fitur jadwal pribadi membutuhkan login, namun Anda tetap siap mencarikan jadwal umum berdasarkan nama mata kuliah atau dosen.
PROMPT;
    }

    /**
     * Definisi skema JSON Schema untuk 4 tool utama sesuai spesifikasi Function Calling OpenAI.
     *
     * @return array
     */
    public function getToolDefinitions(): array
    {
        return [
            // Tool 1: Pencarian Jadwal Kuliah via SPARQL / Database
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'cari_jadwal_kuliah',
                    'description' => 'Mencari jadwal perkuliahan di USU berdasarkan kata kunci topik/nama mata kuliah/dosen, filter fakultas/gedung, dan/atau hari perkuliahan.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'keyword' => [
                                'type'        => 'string',
                                'description' => 'Kata kunci mata kuliah atau topik (misal: "literacy", "perkembangan islam", "pemrograman web", atau nama dosen). Hindari menyatukan nama fakultas ke dalam keyword jika mencari topik mata kuliah di fakultas tertentu.',
                            ],
                            'fakultas' => [
                                'type'        => 'string',
                                'description' => 'Singkatan atau nama fakultas/gedung target yang ditanyakan pengguna (contoh: "FIB", "FEB", "FASILKOM", "GKB"). Pisahkan dengan koma jika membandingkan beberapa fakultas (contoh: "FIB, FEB").',
                            ],
                            'hari' => [
                                'type'        => 'string',
                                'description' => 'Filter hari kuliah opsional.',
                                'enum'        => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
                            ],
                        ],
                    ],
                ],
            ],

            // Tool 2: Detail Informasi Gedung & Ruangan
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'detail_gedung',
                    'description' => 'Mengambil detail profil gedung kampus USU meliputi nama lengkap, kode, fakultas, koordinat spasial (lat/lng), jumlah lantai, dan daftar ruangan di dalamnya.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nama_atau_kode_gedung' => [
                                'type'        => 'string',
                                'description' => 'Nama atau singkatan kode gedung, misalnya "FASILKOM-TI", "FIB", "Pendopo", "Gedung C".',
                            ],
                        ],
                        'required'   => ['nama_atau_kode_gedung'],
                    ],
                ],
            ],

            // Tool 3: Cari Profil Dosen & Jadwal Mengajarnya
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'cari_dosen',
                    'description' => 'Mencari informasi kontak dosen pengajar beserta daftar mata kuliah dan jadwal kelas yang diampu beliau.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nama_dosen' => [
                                'type'        => 'string',
                                'description' => 'Nama lengkap atau potongan nama dosen, misalnya "Rudy Sofyan", "Rusdi Noor".',
                            ],
                        ],
                        'required'   => ['nama_dosen'],
                    ],
                ],
            ],

            // Tool 4: Ambil Jadwal Pribadi User (Mahasiswa Login)
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'ambil_jadwal_saya',
                    'description' => 'Mengambil seluruh jadwal kuliah pribadi milik mahasiswa yang saat ini sedang login di USUClassMap.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'hari' => [
                                'type'        => 'string',
                                'description' => 'Filter opsional untuk hari tertentu saja.',
                                'enum'        => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
                            ],
                        ],
                    ],
                ],
            ],

            // Tool 5: Cek Kemiripan Singkatan / Nama Fakultas USU
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'cek_kemiripan_fakultas',
                    'description' => 'Mengecek dan merekomendasikan nama fakultas atau gedung USU yang bunyi, ejaan, atau singkatannya mirip atau sering tertukar di kalangan mahasiswa (contoh: "FEB" mirip dengan "FIB" dan "MM-FEB").',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nama_atau_kode' => [
                                'type'        => 'string',
                                'description' => 'Nama atau singkatan kode fakultas/gedung yang dicurigai salah ingat atau ditanyakan kemiripannya (misalnya "FEB", "FISIP", "GKB").',
                            ],
                        ],
                        'required'   => ['nama_atau_kode'],
                    ],
                ],
            ],

            // Tool 6: Pencarian Cerdas Fotokopi & ATK
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'cari_fotokopi_atk',
                    'description' => 'Mencari produk ATK (pulpen, kertas, map, materai) dan layanan fotokopi/print/jilid lintas seluruh mitra toko USU (termasuk USU Press) dengan peringkat harga, jarak, status buka, dan fallback jujur.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Kalimat pencarian produk atau jasa fotokopi/ATK.',
                            ],
                        ],
                        'required'   => ['query'],
                    ],
                ],
            ],

            // Tool 7: Tebak Gedung Spasial dari Patokan Relatif
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'tebak_gedung_spasial',
                    'description' => 'Menebak kandidat gedung kampus USU dari clue posisi relatif seperti depan, samping, belakang, antara, atau dekat gedung patokan.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Deskripsi posisi relatif seperti "depan FIB samping FMIPA".',
                            ],
                        ],
                        'required'   => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Router dispatcher untuk mengeksekusi tool yang dipanggil oleh model.
     *
     * @param string $toolName
     * @param array $input
     * @return array
     */
    protected function executeTool(string $toolName, array $input): array
    {
        return match ($toolName) {
            'cari_jadwal_kuliah'     => $this->toolCariJadwalKuliah($input),
            'detail_gedung'          => $this->toolDetailGedung($input),
            'cari_dosen'             => $this->toolCariDosen($input),
            'ambil_jadwal_saya'      => $this->toolAmbilJadwalSaya($input),
            'cek_kemiripan_fakultas' => $this->toolCekKemiripanFakultas($input),
            'cari_fotokopi_atk'      => $this->toolCariFotokopiAtk($input),
            'tebak_gedung_spasial'   => $this->toolTebakGedungSpasial($input),
            default                  => ['status' => 'error', 'message' => "Tool '{$toolName}' tidak dikenali."],
        };
    }

    /**
     * Wrapper Tool 6: Cari Fotokopi & ATK serta isi lastCards/lastAdvisoryNote.
     */
    protected function toolCariFotokopiAtk(array $input): array
    {
        $res = $this->photocopySearch->search((string) ($input['query'] ?? ''), [
            'lat'   => $this->lastRuntimeContext['user_lat'] ?? null,
            'lng'   => $this->lastRuntimeContext['user_lng'] ?? null,
            'limit' => 6,
        ]);
        $this->lastAdvisoryNote = $res['advisory_note'] ?? $this->lastAdvisoryNote;
        foreach ($res['results'] ?? [] as $item) {
            $this->lastCards[] = ['type' => 'fotocopy_item', 'data' => $item];
        }
        return $res;
    }

    /**
     * Wrapper Tool 7: Tebak Gedung Spasial serta isi lastCards/lastPrimaryMapAction.
     */
    protected function toolTebakGedungSpasial(array $input): array
    {
        $res = $this->spatialResolver->resolve((string) ($input['query'] ?? ''), ['limit' => 5]);
        $this->lastAdvisoryNote = $res['advisory_note'] ?? $this->lastAdvisoryNote;
        foreach ($res['candidates'] ?? [] as $cand) {
            $this->lastCards[] = ['type' => 'spatial_building', 'data' => $cand];
        }
        if (!empty($res['candidates'][0]) && !$this->lastPrimaryMapAction) {
            $top = $res['candidates'][0];
            $this->lastPrimaryMapAction = [
                'type'          => 'focus_building',
                'building_id'   => $top['building_id'],
                'building_name' => $top['name'],
                'lat'           => $top['latitude'],
                'lng'           => $top['longitude'],
            ];
        }
        return $res;
    }

    /**
     * Implementasi Tool 5: Cek Kemiripan Singkatan / Nama Fakultas USU
     */
    protected function toolCekKemiripanFakultas(array $input): array
    {
        $term = trim($input['nama_atau_kode'] ?? '');
        if (empty($term)) {
            return ['status' => 'error', 'message' => 'Parameter nama_atau_kode wajib diisi.'];
        }

        $candidates = self::findSimilarBuildingsOrFaculties($term);
        return [
            'status'          => 'success',
            'input'           => $term,
            'kandidat_serupa' => $candidates,
            'saran_proaktif'  => !empty($candidates) ? "Kandidat terdekat yang paling mirip dengan '{$term}' adalah: " . implode(', ', array_map(fn($c) => "{$c['name']} ({$c['code']})", array_slice($candidates, 0, 3))) : 'Tidak ditemukan kandidat yang cukup mirip.',
        ];
    }

    /**
     * Implementasi Tool 1: Cari Jadwal Kuliah via SparqlService / Fallback Database.
     */
    protected function toolCariJadwalKuliah(array $input): array
    {
        $rawKeyword = trim($input['keyword'] ?? '');
        $fakultasParam = trim($input['fakultas'] ?? '');
        $hari = $input['hari'] ?? null;

        /** @var SparqlService $sparqlService */
        $sparqlService = app(SparqlService::class);

        // 1. Parsing dan normalisasi kueri percakapan (bersihkan reduplikasi & typo percakapan)
        $parsed = $sparqlService->parseConversationalQuery($rawKeyword);
        $searchKeyword = !empty($parsed['core_keyword']) ? $parsed['core_keyword'] : $rawKeyword;

        // Gabungkan deteksi fakultas dari context user, parsing keyword, dan parameter eksplisit
        $paramFaculties = !empty($fakultasParam) ? preg_split('/[\s,]+/', strtoupper($fakultasParam), -1, PREG_SPLIT_NO_EMPTY) : [];
        $mentionedFaculties = array_values(array_unique(array_filter(array_merge(
            $this->currentMentionedFaculties,
            $parsed['detected_faculties'],
            $paramFaculties
        ))));

        $facultyAliases = SparqlService::getFacultyAliases();
        $targetFacultyKey = null;

        // Deteksi apakah user/LLM secara spesifik meminta satu fakultas/gedung target
        if (!empty($fakultasParam)) {
            $paramUpper = strtoupper($fakultasParam);
            if (isset($facultyAliases[$paramUpper])) {
                $targetFacultyKey = $paramUpper;
            } else {
                foreach ($facultyAliases as $fKey => $info) {
                    if (strcasecmp($info['name'], $fakultasParam) === 0 || in_array(mb_strtolower($fakultasParam), $info['patterns'])) {
                        $targetFacultyKey = $fKey;
                        break;
                    }
                }
            }
        }

        if (!$targetFacultyKey && !empty($parsed['is_specific_faculty']) && count($parsed['detected_faculties']) === 1) {
            $targetFacultyKey = $parsed['detected_faculties'][0];
        }

        // 2. Eksekusi pencarian jadwal ke SparqlService
        $result = $sparqlService->searchSchedules($searchKeyword, $hari);
        $allSchedules = $result['data'] ?? [];
        $fuzzyCandidates = [];

        // Fallback jika pencarian menghasilkan 0
        if (empty($allSchedules) && !empty($searchKeyword)) {
            $suggestions = $sparqlService->getFuzzySuggestions($searchKeyword, 3);
            if (!empty($suggestions)) {
                $fuzzyCandidates = $suggestions;
                $topSuggestion = $suggestions[0];
                if (($topSuggestion['score'] ?? 0) >= 35) {
                    $retryResult = $sparqlService->searchSchedules($topSuggestion['name'], $hari);
                    if (!empty($retryResult['data'])) {
                        $allSchedules = $retryResult['data'];
                        $result['source'] = 'SPARQL (Fuzzy Candidate Match: ' . $topSuggestion['name'] . ')';
                    }
                }
            }
        }

        // 3. FULL AGGREGATION SEBELUM SLICING (MENCEGAH BLINDSPOT SALAH KLAIM)
        $buildingsAggregation = [];
        foreach ($allSchedules as $item) {
            $bId = $item['gedung']['id'] ?? null;
            $bKode = $item['gedung']['kode'] ?? 'LAINNYA';
            $bNama = $item['gedung']['nama'] ?? 'Gedung Lainnya';
            $key = $bId ? "id_{$bId}" : "code_{$bKode}";

            if (!isset($buildingsAggregation[$key])) {
                $aliasKey = null;
                foreach ($facultyAliases as $fKey => $info) {
                    if (($bId && in_array($bId, $info['building_ids'])) || in_array($bKode, $info['building_codes'])) {
                        $aliasKey = $fKey;
                        break;
                    }
                }

                $buildingsAggregation[$key] = [
                    'building_id'    => $bId,
                    'building_name'  => $bNama,
                    'building_code'  => $bKode,
                    'faculty_alias'  => $aliasKey,
                    'faculty_name'   => $aliasKey ? $facultyAliases[$aliasKey]['name'] : $bNama,
                    'latitude'       => $item['gedung']['latitude'] ?? null,
                    'longitude'      => $item['gedung']['longitude'] ?? null,
                    'schedule_count' => 0,
                    'courses'        => [],
                    'schedules'      => [],
                ];
            }

            $buildingsAggregation[$key]['schedule_count']++;
            $courseName = $item['mata_kuliah']['nama'] ?? '';
            $courseCode = $item['mata_kuliah']['kode'] ?? '';
            $label = $courseCode ? "{$courseName} ({$courseCode})" : $courseName;
            if (!in_array($label, $buildingsAggregation[$key]['courses'])) {
                $buildingsAggregation[$key]['courses'][] = $label;
            }
            $buildingsAggregation[$key]['schedules'][] = $item;
        }

        // Urutkan agregasi: Gedung/Fakultas yang disebut user didahulukan
        uasort($buildingsAggregation, function ($a, $b) use ($mentionedFaculties) {
            $aMentioned = in_array($a['faculty_alias'], $mentionedFaculties);
            $bMentioned = in_array($b['faculty_alias'], $mentionedFaculties);
            if ($aMentioned && !$bMentioned) return -1;
            if (!$aMentioned && $bMentioned) return 1;
            return $b['schedule_count'] <=> $a['schedule_count'];
        });

        // 4. PENYARINGAN KETAT UNTUK PERMINTAAN FAKULTAS TERTENTU
        $isSpecificQuery = !empty($targetFacultyKey);
        $targetInfo = $targetFacultyKey ? ($facultyAliases[$targetFacultyKey] ?? null) : null;
        $targetBuildingIds = $targetInfo['building_ids'] ?? [];
        $targetBuildingCodes = $targetInfo['building_codes'] ?? [];

        $isScheduleInTargetFaculty = function ($item) use ($targetInfo, $targetFacultyKey, $targetBuildingIds, $targetBuildingCodes) {
            if (!$targetInfo) return true;
            $bId = $item['gedung']['id'] ?? null;
            $bKode = $item['gedung']['kode'] ?? null;
            $bNama = $item['gedung']['nama'] ?? null;

            if ($bId && in_array($bId, $targetBuildingIds, true)) return true;
            if ($bKode && in_array($bKode, $targetBuildingCodes, true)) return true;
            if ($bNama && (stripos($bNama, $targetInfo['name']) !== false || stripos($bNama, $targetFacultyKey) !== false)) return true;
            return false;
        };

        if ($isSpecificQuery) {
            $schedules = array_values(array_filter($allSchedules, $isScheduleInTargetFaculty));
        } else {
            $schedules = $allSchedules;
        }

        // Identifikasi fakultas yang ditanyakan user namun memiliki 0 jadwal
        $zeroMentionedList = [];
        foreach ($mentionedFaculties as $mf) {
            $hasSchedule = false;
            foreach ($buildingsAggregation as $agg) {
                if ($agg['faculty_alias'] === $mf && $agg['schedule_count'] > 0) {
                    $hasSchedule = true;
                    break;
                }
            }
            if (!$hasSchedule && isset($facultyAliases[$mf])) {
                $zeroMentionedList[] = "{$facultyAliases[$mf]['name']} ({$mf})";
            }
        }

        // Susun teks ringkasan untuk LLM
        if ($isSpecificQuery) {
            if (!empty($schedules)) {
                $otherLocations = [];
                foreach ($buildingsAggregation as $agg) {
                    if ($agg['faculty_alias'] !== $targetFacultyKey && $agg['schedule_count'] > 0) {
                        $otherLocations[] = "{$agg['faculty_name']} ({$agg['schedule_count']} jadwal)";
                    }
                }
                $otherNote = !empty($otherLocations) ? " (Catatan: Terdapat juga jadwal di lokasi lain: " . implode(', ', $otherLocations) . ", namun TIDAK disertakan karena user meminta khusus {$targetInfo['name']})" : '';
                $fullSummaryText = "Ditemukan " . count($schedules) . " jadwal untuk kata kunci '{$searchKeyword}' KHUSUS di {$targetInfo['name']} ({$targetFacultyKey}){$otherNote}.";
            } else {
                $otherLocations = [];
                foreach ($buildingsAggregation as $agg) {
                    if ($agg['schedule_count'] > 0) {
                        $otherLocations[] = "{$agg['faculty_name']} ({$agg['schedule_count']} jadwal)";
                    }
                }

                // Cek apakah ada fakultas mirip yang memiliki jadwal tersebut (misal FEB -> FIB)
                $similarFaculties = self::findSimilarBuildingsOrFaculties($targetFacultyKey);
                $alternativeSuggestions = [];
                foreach ($similarFaculties as $sf) {
                    $sfCode = $sf['code'];
                    foreach ($buildingsAggregation as $agg) {
                        if (($agg['faculty_alias'] === $sfCode || $agg['building_code'] === $sfCode) && $agg['schedule_count'] > 0) {
                            $alternativeSuggestions[] = [
                                'code'           => $sfCode,
                                'name'           => $agg['faculty_name'],
                                'schedule_count' => $agg['schedule_count'],
                                'sample_courses' => array_slice($agg['courses'], 0, 3),
                                'reason'         => $sf['reason'],
                            ];
                            break;
                        }
                    }
                }

                if (!empty($alternativeSuggestions)) {
                    $topAlt = $alternativeSuggestions[0];
                    $fullSummaryText = "PENTING: Tidak ditemukan jadwal untuk kata kunci '{$searchKeyword}' di {$targetInfo['name']} ({$targetFacultyKey}) (0 jadwal). " .
                        "NAMUN DI FAKULTAS YANG MIRIP YAITU {$topAlt['name']} ({$topAlt['code']}) DITEMUKAN {$topAlt['schedule_count']} JADWAL! " .
                        "Alasan kemiripan: {$topAlt['reason']}. Contoh mata kuliah: " . implode(', ', $topAlt['sample_courses']) . ". " .
                        "INSTRUKSI PROAKTIF: Anda WAJIB langsung menebak secara proaktif bahwa pengguna kemungkinan maksudnya adalah {$topAlt['name']} ({$topAlt['code']}) dan tawarkan jadwal tersebut!";

                    $this->lastQuickReplies[] = [
                        'label' => "Lihat di {$topAlt['code']} ({$topAlt['schedule_count']})",
                        'query' => "Lihat jadwal {$searchKeyword} khusus di {$topAlt['name']}",
                    ];
                } else {
                    $fullSummaryText = "PENTING: Tidak ditemukan jadwal untuk kata kunci '{$searchKeyword}' di {$targetInfo['name']} ({$targetFacultyKey}) (0 jadwal). " . (!empty($otherLocations) ? "Namun jadwal tersebut tersedia di lokasi lain: " . implode(', ', $otherLocations) : "Tidak ada jadwal serupa di seluruh kampus.");
                }
            }
        } else {
            $summaryLines = ["Total ditemukan " . count($schedules) . " jadwal untuk kata kunci '{$searchKeyword}':"];
            foreach ($buildingsAggregation as $agg) {
                $sample = implode(', ', array_slice($agg['courses'], 0, 3));
                $summaryLines[] = "- {$agg['faculty_name']} ({$agg['building_code']}): {$agg['schedule_count']} jadwal (Contoh MK: {$sample})";
            }
            if (!empty($zeroMentionedList)) {
                foreach ($zeroMentionedList as $zm) {
                    $summaryLines[] = "- {$zm}: 0 jadwal (TIDAK DITEMUKAN / TIDAK ADA)";
                }
            }
            $fullSummaryText = implode("\n", $summaryLines);
        }

        // Susun sample jadwal representatif: Prioritaskan fakultas yang disebut user
        $representativeSchedules = [];
        $sampleMax = 8;
        if ($isSpecificQuery) {
            $representativeSchedules = array_slice($schedules, 0, $sampleMax);
        } else {
            foreach ($buildingsAggregation as $agg) {
                $quota = in_array($agg['faculty_alias'], $mentionedFaculties) ? 4 : 2;
                $items = array_slice($agg['schedules'], 0, $quota);
                foreach ($items as $it) {
                    if (count($representativeSchedules) < $sampleMax) {
                        $representativeSchedules[] = $it;
                    }
                }
            }
            if (count($representativeSchedules) < $sampleMax) {
                foreach ($schedules as $s) {
                    if (!in_array($s, $representativeSchedules, true)) {
                        $representativeSchedules[] = $s;
                        if (count($representativeSchedules) >= $sampleMax) break;
                    }
                }
            }
        }

        // 5. SUSUN DATA LENGKAP UNTUK INTERAKSI KAYA (facultySummary, quickReplies, mapActions, schedules)
        $this->lastFacultySummary = [];
        $this->lastQuickReplies = [];
        $this->lastMapActions = [];
        $this->lastPrimaryMapAction = null;
        $this->lastSchedules = [];

        // Simpan hingga 25 jadwal terstruktur lengkap untuk dirender sebagai kartu interaktif di chat
        $schedulesToStore = array_slice($schedules, 0, 25);
        foreach ($schedulesToStore as $s) {
            $lat = isset($s['gedung']['latitude']) ? (float) $s['gedung']['latitude'] : null;
            $lng = isset($s['gedung']['longitude']) ? (float) $s['gedung']['longitude'] : null;
            $roomId = $s['ruangan']['id'] ?? null;
            $roomName = $s['ruangan']['nama'] ?? null;
            $bId = $s['gedung']['id'] ?? null;
            $bName = $s['gedung']['nama'] ?? null;

            $itemMapAction = [
                'type'          => $roomId ? 'focus_room' : 'focus_building',
                'building_id'   => $bId,
                'building_name' => $bName,
                'lat'           => $lat,
                'lng'           => $lng,
                'room_id'       => $roomId,
                'room_name'     => $roomName,
                'schedule_id'   => $s['id'] ?? null,
                'course_code'   => $s['mata_kuliah']['kode'] ?? null,
                'course_name'   => $s['mata_kuliah']['nama'] ?? null,
            ];

            $this->lastSchedules[] = [
                'schedule_id'   => $s['id'] ?? null,
                'course_name'   => $s['mata_kuliah']['nama'] ?? '-',
                'course_code'   => $s['mata_kuliah']['kode'] ?? '-',
                'sks'           => $s['mata_kuliah']['sks'] ?? null,
                'day'           => $s['hari'] ?? '-',
                'time'          => ($s['jam_mulai'] ?? '--:--') . ' - ' . ($s['jam_selesai'] ?? '--:--'),
                'class_group'   => $s['kelompok_kelas'] ?? '-',
                'room_name'     => $roomName ?: '-',
                'floor'         => $s['ruangan']['lantai'] ?? 1,
                'lecturer'      => $s['dosen']['nama'] ?? '-',
                'building_name' => $bName ?: '-',
                'building_code' => $s['gedung']['kode'] ?? '-',
                'map_action'    => $itemMapAction,
            ];
        }

        if ($isSpecificQuery) {
            if (!empty($schedules)) {
                $first = $schedules[0];
                $this->lastPrimaryMapAction = [
                    'type'          => 'focus_building',
                    'building_id'   => $first['gedung']['id'] ?? null,
                    'building_name' => $first['gedung']['nama'] ?? ($targetInfo['name'] ?? 'Gedung'),
                    'lat'           => isset($first['gedung']['latitude']) ? (float) $first['gedung']['latitude'] : null,
                    'lng'           => isset($first['gedung']['longitude']) ? (float) $first['gedung']['longitude'] : null,
                    'room_id'       => null,
                    'room_name'     => null,
                    'schedule_id'   => null,
                ];
            }
        } else {
            foreach ($buildingsAggregation as $agg) {
                if ($agg['schedule_count'] > 0) {
                    $bAction = [
                        'type'          => 'focus_building',
                        'building_id'   => $agg['building_id'],
                        'building_name' => $agg['building_name'],
                        'lat'           => $agg['latitude'] ? (float) $agg['latitude'] : null,
                        'lng'           => $agg['longitude'] ? (float) $agg['longitude'] : null,
                        'room_id'       => null,
                        'room_name'     => null,
                        'schedule_id'   => null,
                    ];

                    $this->lastFacultySummary[] = [
                        'building_id'     => $agg['building_id'],
                        'building_name'   => $agg['faculty_name'] . ($agg['building_code'] ? " ({$agg['building_code']})" : ''),
                        'building_code'   => $agg['building_code'],
                        'schedule_count'  => $agg['schedule_count'],
                        'sample_courses'  => array_slice($agg['courses'], 0, 4),
                        'follow_up_query' => "Tampilkan detail jadwal {$searchKeyword} di " . $agg['faculty_name'],
                        'map_action'      => $bAction,
                    ];

                    $this->lastMapActions[] = $bAction;

                    $shortName = $agg['building_code'] ?: $agg['faculty_name'];
                    $this->lastQuickReplies[] = [
                        'label' => "Detail di {$shortName} ({$agg['schedule_count']})",
                        'query' => "Lihat jadwal {$searchKeyword} khusus di {$agg['faculty_name']}",
                    ];

                    // Set primary map action ke fakultas yang disebut user jika cocok
                    if (!$this->lastPrimaryMapAction || in_array($agg['faculty_alias'], $mentionedFaculties)) {
                        $this->lastPrimaryMapAction = $bAction;
                    }
                }
            }
        }

        if (!empty($searchKeyword) && count($schedules) > 0) {
            $this->lastQuickReplies[] = [
                'label'          => 'Tampilkan semua di Sidebar Peta',
                'sidebar_search' => $searchKeyword,
            ];
        }

        return [
            'total_ditemukan'    => count($schedules),
            'sumber_data'        => $result['source'] ?? 'Database USUClassMap',
            'ringkasan_agregasi' => $fullSummaryText,
            'kandidat_kemiripan' => $fuzzyCandidates,
            'hasil'              => array_map(function ($item) {
                return [
                    'schedule_id' => $item['id'] ?? null,
                    'mata_kuliah' => $item['mata_kuliah']['nama'] ?? '-',
                    'kode_mk'     => $item['mata_kuliah']['kode'] ?? '-',
                    'sks'         => $item['mata_kuliah']['sks'] ?? 0,
                    'dosen'       => $item['dosen']['nama'] ?? '-',
                    'hari'        => $item['hari'] ?? '-',
                    'jam'         => ($item['jam_mulai'] ?? '--:--') . ' - ' . ($item['jam_selesai'] ?? '--:--'),
                    'kelas'       => $item['kelompok_kelas'] ?? '-',
                    'ruangan'     => [
                        'id'      => $item['ruangan']['id'] ?? null,
                        'nama'    => $item['ruangan']['nama'] ?? '-',
                        'lantai'  => $item['ruangan']['lantai'] ?? 1,
                    ],
                    'gedung'      => [
                        'id'        => $item['gedung']['id'] ?? null,
                        'nama'      => $item['gedung']['nama'] ?? '-',
                        'kode'      => $item['gedung']['kode'] ?? '-',
                        'latitude'  => $item['gedung']['latitude'] ?? null,
                        'longitude' => $item['gedung']['longitude'] ?? null,
                    ],
                ];
            }, $representativeSchedules),
        ];
    }

    /**
     * Implementasi Tool 2: Mengambil Profil Lengkap Gedung dan Daftar Ruangannya.
     */
    protected function toolDetailGedung(array $input): array
    {
        $query = trim($input['nama_atau_kode_gedung'] ?? '');
        if (empty($query)) {
            return ['status' => 'error', 'message' => 'Parameter nama_atau_kode_gedung wajib diisi.'];
        }

        $building = Building::with(['rooms' => function ($q) {
            $q->orderBy('floor')->orderBy('name');
        }])
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('code', 'LIKE', "%{$query}%")
            ->orWhere('faculty', 'LIKE', "%{$query}%")
            ->first();

        if (!$building) {
            return [
                'status'  => 'not_found',
                'message' => "Gedung dengan kata kunci '{$query}' tidak ditemukan di database USUClassMap.",
            ];
        }

        return [
            'status'         => 'success',
            'building_id'    => $building->id,
            'nama_gedung'    => $building->name,
            'kode_gedung'    => $building->code,
            'fakultas'       => $building->faculty ?? 'Universitas Sumatera Utara',
            'deskripsi'      => $building->description ?? '-',
            'jumlah_lantai'  => $building->floor_count ?? 1,
            'latitude'       => (float) $building->latitude,
            'longitude'      => (float) $building->longitude,
            'total_ruangan'  => $building->rooms->count(),
            'daftar_ruangan' => $building->rooms->map(fn($r) => [
                'room_id'      => $r->id,
                'nama'         => $r->name,
                'lantai'       => $r->floor,
                'kapasitas'    => $r->capacity ?? 0,
                'ada_foto_360' => !empty($r->photo_360_path),
            ])->values()->all(),
        ];
    }

    /**
     * Implementasi Tool 3: Cari Informasi Dosen & Jadwal Mengajarnya.
     */
    protected function toolCariDosen(array $input): array
    {
        $nama = trim($input['nama_dosen'] ?? '');
        if (empty($nama)) {
            return ['status' => 'error', 'message' => 'Parameter nama_dosen wajib diisi.'];
        }

        $lecturers = Lecturer::with(['schedules.course', 'schedules.room.building'])
            ->where('name', 'LIKE', "%{$nama}%")
            ->orWhere('nip', 'LIKE', "%{$nama}%")
            ->limit(3)
            ->get();

        if ($lecturers->isEmpty()) {
            return [
                'status'  => 'not_found',
                'message' => "Dosen dengan nama '{$nama}' tidak ditemukan di sistem.",
            ];
        }

        return [
            'status' => 'success',
            'dosen'  => $lecturers->map(function ($lecturer) {
                return [
                    'lecturer_id'     => $lecturer->id,
                    'nama'            => $lecturer->name,
                    'nip'             => $lecturer->nip ?? '-',
                    'email'           => $lecturer->email ?? '-',
                    'total_jadwal'    => $lecturer->schedules->count(),
                    'jadwal_mengajar' => $lecturer->schedules->map(fn($s) => [
                        'schedule_id' => $s->id,
                        'mata_kuliah' => $s->course->name ?? '-',
                        'kode_mk'     => $s->course->code ?? '-',
                        'hari'        => $s->day,
                        'jam'         => substr($s->time_start, 0, 5) . ' - ' . substr($s->time_end, 0, 5),
                        'kelas'       => $s->class_group,
                        'ruangan'     => $s->room->name ?? '-',
                        'gedung'      => $s->room->building->name ?? '-',
                        'latitude'    => $s->room->building->latitude ?? null,
                        'longitude'   => $s->room->building->longitude ?? null,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Implementasi Tool 4: Ambil Jadwal Milik Mahasiswa yang Sedang Login.
     */
    protected function toolAmbilJadwalSaya(array $input): array
    {
        $user = Auth::user();
        if (!$user) {
            return [
                'status'        => 'unauthenticated',
                'authenticated' => false,
                'message'       => 'Pengguna saat ini berstatus Tamu (belum login). Berikan instruksi agar pengguna login melalui menu Masuk di navigasi atas untuk melihat jadwal pribadinya.',
            ];
        }

        $query = Schedule::with(['course', 'lecturer', 'room.building']);

        // Filter berdasarkan semester mahasiswa yang tersimpan di profil
        if (!empty($user->semester)) {
            $query->where('semester', $user->semester);
        }

        if (!empty($input['hari'])) {
            $query->where('day', $input['hari']);
        }

        $schedules = $query->orderBy('day')->orderBy('time_start')->get();

        $this->lastSchedules = [];
        foreach ($schedules->take(25) as $s) {
            $lat = isset($s->room->building->latitude) ? (float) $s->room->building->latitude : null;
            $lng = isset($s->room->building->longitude) ? (float) $s->room->building->longitude : null;
            $this->lastSchedules[] = [
                'schedule_id'   => $s->id,
                'course_name'   => $s->course->name ?? '-',
                'course_code'   => $s->course->code ?? '-',
                'sks'           => $s->course->credits ?? null,
                'day'           => $s->day,
                'time'          => substr($s->time_start, 0, 5) . ' - ' . substr($s->time_end, 0, 5),
                'class_group'   => $s->class_group,
                'room_name'     => $s->room->name ?? '-',
                'floor'         => $s->room->floor ?? 1,
                'lecturer'      => $s->lecturer->name ?? '-',
                'building_name' => $s->room->building->name ?? '-',
                'building_code' => $s->room->building->code ?? '-',
                'map_action'    => [
                    'type'          => 'focus_room',
                    'building_id'   => $s->room->building->id ?? null,
                    'building_name' => $s->room->building->name ?? '-',
                    'lat'           => $lat,
                    'lng'           => $lng,
                    'room_id'       => $s->room->id ?? null,
                    'room_name'     => $s->room->name ?? null,
                    'schedule_id'   => $s->id,
                    'course_code'   => $s->course->code ?? null,
                    'course_name'   => $s->course->name ?? null,
                ],
            ];
        }

        return [
            'status'        => 'success',
            'authenticated' => true,
            'user'          => [
                'nama'     => $user->name,
                'nim'      => $user->nim ?? '-',
                'semester' => $user->semester ?? '-',
            ],
            'total_kelas'   => $schedules->count(),
            'jadwal'        => $schedules->map(fn($s) => [
                'schedule_id' => $s->id,
                'mata_kuliah' => $s->course->name ?? '-',
                'kode_mk'     => $s->course->code ?? '-',
                'sks'         => $s->course->credits ?? 0,
                'dosen'       => $s->lecturer->name ?? '-',
                'hari'        => $s->day,
                'jam'         => substr($s->time_start, 0, 5) . ' - ' . substr($s->time_end, 0, 5),
                'kelas'       => $s->class_group,
                'ruangan'     => $s->room->name ?? '-',
                'gedung'      => $s->room->building->name ?? '-',
                'latitude'    => $s->room->building->latitude ?? null,
                'longitude'   => $s->room->building->longitude ?? null,
            ])->values()->all(),
        ];
    }
}
