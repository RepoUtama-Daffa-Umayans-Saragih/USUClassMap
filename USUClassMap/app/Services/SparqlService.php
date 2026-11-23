<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SparqlService
{
    protected string $queryEndpoint;
    protected int $timeout;
    protected array $namespaces;

    /**
     * Daftar stopword percakapan bahasa Indonesia non-inti yang disaring
     */
    protected array $stopwords = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'ini', 'itu',
        'dengan', 'atau', 'atay', 'adalah', 'yaitu', 'kelas', 'matkul', 'mata', 'kuliah',
        'jadwal', 'cari', 'carikan', 'dimana', 'mana', 'saya', 'aku', 'kami', 'kita',
        'dia', 'mereka', 'kamu', 'anda',
        'ingatnya', 'kayaknya', 'kayak', 'tentang', 'ruang', 'ruangan', 'gedung', 'ada',
        'bisa', 'tolong', 'info', 'jadwalnya', 'kuliahnya', 'dosen', 'pak', 'bu',
        'ibu', 'bapak', 'mohon', 'apa', 'apakah', 'siapa', 'kapan', 'sekarang',
        'yah', 'ya', 'gitu', 'begitu', 'sih', 'dong', 'deh', 'aja', 'saja',
        'nih', 'tuh', 'kok', 'kah', 'oh', 'wah', 'eh', 'kan', 'ngga', 'nggak',
        'ga', 'gak', 'tau', 'tahu', 'yaudah', 'beneran',
        'lihat', 'lihatlah', 'tampilkan', 'tampil', 'tunjukkan', 'khusus', 'khususnya',
        'spesifik', 'daftar', 'semua', 'cek', 'punya',
        'bukan', 'bukannn', 'bukanlah', 'bkn', 'salah', 'maksudnya', 'maksudku',
        'berarti', 'tadi', 'lupa', 'mirip', 'antara', 'seperti', 'ralat',
    ];

    public function __construct()
    {
        $this->queryEndpoint = config('sparql.query_endpoint', 'http://localhost:3030/usuclassmap/query');
        $this->timeout = config('sparql.timeout', 5);
        $this->namespaces = config('sparql.namespaces', [
            'usu' => 'http://usuclassmap.usu.ac.id/ontology#',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        ]);
    }

    /**
     * Kamus Lengkap Alias Fakultas & Gedung Universitas Sumatera Utara (USU)
     *
     * @return array
     */
    public static function getFacultyAliases(): array
    {
        return [
            'FIB' => [
                'name'           => 'Fakultas Ilmu Budaya',
                'code'           => 'FIB',
                'patterns'       => ['fib', 'fakultas ilmu budaya', 'ilmu budaya', 'sastra'],
                'building_ids'   => [27],
                'building_codes' => ['FIB'],
            ],
            'FEB' => [
                'name'           => 'Fakultas Ekonomi dan Bisnis',
                'code'           => 'FEB',
                'patterns'       => ['feb', 'fakultas ekonomi dan bisnis', 'ekonomi dan bisnis', 'fakultas ekonomi', 'fakultas bisnis', 'magister manajemen'],
                'building_ids'   => [21, 22],
                'building_codes' => ['FEB', 'MM-FEB'],
            ],
            'FASILKOM' => [
                'name'           => 'Fakultas Ilmu Komputer dan Teknologi Informasi',
                'code'           => 'FASILKOM-TI',
                'patterns'       => ['fasilkom-ti', 'fasilkom ti', 'fasilkom', 'fasilkomti', 'ilkom', 'ti', 'fikti', 'fakultas ilmu komputer'],
                'building_ids'   => [12, 13, 47],
                'building_codes' => ['FASILKOM-TI', 'FASILKOM-B', 'GD-FASILKOM'],
            ],
            'FT' => [
                'name'           => 'Fakultas Teknik',
                'code'           => 'FT',
                'patterns'       => ['fakultas teknik', 'teknik mesin', 'teknik kimia', 'teknik lingkungan', 'ft'],
                'building_ids'   => [18, 19, 20],
                'building_codes' => ['FT-MESIN', 'FT-KIMIA', 'FT-LINGKUNGAN'],
            ],
            'FH' => [
                'name'           => 'Fakultas Hukum',
                'code'           => 'FH',
                'patterns'       => ['fakultas hukum', 'fh'],
                'building_ids'   => [23],
                'building_codes' => ['FH'],
            ],
            'FK' => [
                'name'           => 'Fakultas Kedokteran',
                'code'           => 'FK',
                'patterns'       => ['fakultas kedokteran', 'kedokteran umum', 'fk'],
                'building_ids'   => [14],
                'building_codes' => ['FK'],
            ],
            'FKG' => [
                'name'           => 'Fakultas Kedokteran Gigi',
                'code'           => 'FKG',
                'patterns'       => ['fakultas kedokteran gigi', 'kedokteran gigi', 'fkg'],
                'building_ids'   => [15],
                'building_codes' => ['FKG'],
            ],
            'FKM' => [
                'name'           => 'Fakultas Kesehatan Masyarakat',
                'code'           => 'FKM',
                'patterns'       => ['fakultas kesehatan masyarakat', 'kesehatan masyarakat', 'fakultas kesehatan', 'fkm'],
                'building_ids'   => [16],
                'building_codes' => ['FKM'],
            ],
            'FP' => [
                'name'           => 'Fakultas Pertanian',
                'code'           => 'FP',
                'patterns'       => ['fakultas pertanian', 'pertanian', 'keteknikan pertanian', 'fp'],
                'building_ids'   => [29],
                'building_codes' => ['FP-TEKNIK'],
            ],
            'FMIPA' => [
                'name'           => 'Fakultas Matematika dan Ilmu Pengetahuan Alam',
                'code'           => 'FMIPA',
                'patterns'       => ['fakultas matematika dan ilmu pengetahuan alam', 'fakultas mipa', 'fmipa', 'mipa'],
                'building_ids'   => [31],
                'building_codes' => ['PERPUS-MIPA'],
            ],
            'FISIP' => [
                'name'           => 'Fakultas Ilmu Sosial dan Ilmu Politik',
                'code'           => 'FISIP',
                'patterns'       => ['fakultas ilmu sosial dan ilmu politik', 'fisip', 'ilmu sosial'],
                'building_ids'   => [24, 25, 26],
                'building_codes' => ['FISIP', 'TAX-CENTER', 'MSP-FISIP'],
            ],
            'FPSI' => [
                'name'           => 'Fakultas Psikologi',
                'code'           => 'FPSI',
                'patterns'       => ['fakultas psikologi', 'psikologi', 'fpsi', 'psi'],
                'building_ids'   => [28],
                'building_codes' => ['FPSI'],
            ],
            'FKEP' => [
                'name'           => 'Fakultas Keperawatan',
                'code'           => 'FKEP',
                'patterns'       => ['fakultas keperawatan', 'keperawatan', 'fkep'],
                'building_ids'   => [],
                'building_codes' => ['FKEP'],
            ],
            'FF' => [
                'name'           => 'Fakultas Farmasi',
                'code'           => 'FF',
                'patterns'       => ['fakultas farmasi', 'farmasi', 'ff'],
                'building_ids'   => [17],
                'building_codes' => ['FF'],
            ],
            'FHUT' => [
                'name'           => 'Fakultas Kehutanan',
                'code'           => 'FHUT',
                'patterns'       => ['fakultas kehutanan', 'kehutanan', 'fhut', 'fkehutanan'],
                'building_ids'   => [],
                'building_codes' => ['FHUT'],
            ],
            'GKB' => [
                'name'           => 'Gedung Kuliah Bersama',
                'code'           => 'GKB',
                'patterns'       => ['gedung kuliah bersama', 'gkb'],
                'building_ids'   => [4],
                'building_codes' => ['GKB'],
            ],
            'VOKASI' => [
                'name'           => 'Fakultas Vokasi',
                'code'           => 'VOKASI',
                'patterns'       => ['fakultas vokasi', 'vokasi'],
                'building_ids'   => [30],
                'building_codes' => ['VOKASI'],
            ],
        ];
    }

    /**
     * Kamus Akar Kata & Sinonim Percakapan Sehari-hari Mahasiswa ke Istilah Akademis
     */
    public static function getColloquialStemMap(): array
    {
        return [
            'mikir'    => ['pemikiran', 'berpikir', 'pikir', 'filsafat', 'logika', 'kritis'],
            'mikiran'  => ['pemikiran', 'berpikir', 'pikir', 'filsafat', 'logika', 'kritis'],
            'pikir'    => ['pemikiran', 'berpikir', 'filsafat', 'logika', 'kritis'],
            'ngomong'  => ['speaking', 'komunikasi', 'percakapan', 'retorika'],
            'bicara'   => ['speaking', 'komunikasi', 'percakapan', 'retorika'],
            'denger'   => ['listening', 'pendengaran'],
            'dengerin' => ['listening', 'pendengaran'],
            'baca'     => ['reading', 'literacy', 'literasi'],
            'nulis'    => ['writing', 'menulis', 'karya'],
            'tulis'    => ['writing', 'menulis', 'karya'],
            'ngoding'  => ['pemrograman', 'algoritma', 'komputer', 'perangkat lunak'],
            'coding'   => ['pemrograman', 'algoritma', 'komputer', 'perangkat lunak'],
            'hitung'   => ['matematika', 'kalkulus', 'statistika', 'akuntansi'],
            'ngitung'  => ['matematika', 'kalkulus', 'statistika', 'akuntansi'],
        ];
    }

    /**
     * Parsing kueri percakapan: Menormalkan reduplikasi kata, mendeteksi singkatan/nama fakultas,
     * serta mengekstrak kata kunci inti perkuliahan.
     *
     * @param string $query
     * @return array ['core_keyword' => string, 'clean_keyword' => string, 'detected_faculties' => array, 'has_doubt' => bool]
     */
    public function parseConversationalQuery(string $query): array
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        // Normalisasi huruf berulang berlebihan (contoh: bukannn -> bukan, sippp -> sip)
        $normalized = preg_replace('/(\w)\1{2,}/u', '$1', $normalized);
        // 1. Reduplikasi kata bertanda hubung atau spasi: literacy-literacy -> literacy
        $normalized = preg_replace('/(\b[\p{L}\p{N}_]+\b)[-\s]+\1\b/u', '$1', $normalized);

        // 2. Koreksi typo percakapan umum
        $replacements = [
            '/\batay\b/u'      => 'atau',
            '/\byah\b/u'       => 'ya',
            '/\bgitu\b/u'      => '',
            '/\bsih\b/u'       => '',
            '/\bdong\b/u'      => '',
            '/\bdeh\b/u'       => '',
            '/\baja\b/u'       => '',
            '/\bbukann+\b/u'   => 'bukan',
            '/\bkayak\b/u'     => 'kayaknya',
        ];
        $normalized = preg_replace(array_keys($replacements), array_values($replacements), $normalized);

        $aliases = self::getFacultyAliases();
        $detected = [];
        $workingText = ' ' . $normalized . ' ';

        foreach ($aliases as $key => $info) {
            foreach ($info['patterns'] as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/iu', $workingText)) {
                    $detected[$key] = $info;
                    break;
                }
            }
        }

        // Hapus nama fakultas dan kata pengisi untuk mendapatkan kata kunci inti mata kuliah
        $cleanText = $workingText;
        foreach ($detected as $info) {
            foreach ($info['patterns'] as $pattern) {
                $cleanText = preg_replace('/\b' . preg_quote($pattern, '/') . '\b/iu', ' ', $cleanText);
            }
        }
        foreach ($this->stopwords as $sw) {
            $cleanText = preg_replace('/\b' . preg_quote($sw, '/') . '\b/iu', ' ', $cleanText);
        }

        $coreKeyword = trim(preg_replace('/\s+/', ' ', $cleanText));
        $isSpecific = (count($detected) === 1 && preg_match('/\b(khusus|spesifik|hanya|cuma|detail|di|pada)\b/iu', $normalized));
        $hasDoubt = (bool) preg_match('/\b(mirip|kayaknya|kayak|ketukar|ketuker|salah|bukan|antara|hampir sama|seperti)\b/iu', $normalized);

        return [
            'original'            => $query,
            'normalized'          => $normalized,
            'core_keyword'        => $coreKeyword ?: $normalized,
            'clean_keyword'       => $coreKeyword,
            'detected_faculties'  => array_keys($detected),
            'is_specific_faculty' => $isSpecific,
            'has_doubt'           => $hasDoubt,
        ];
    }

    /**
     * Normalisasi teks dan pemecahan menjadi token bermakna dengan stopword filtering.
     *
     * @param string|null $keyword
     * @return array
     */
    public function extractTokens(?string $keyword): array
    {
        if (empty($keyword)) {
            return [];
        }

        $cleaned = mb_strtolower(trim($keyword), 'UTF-8');
        // Normalisasi reduplikasi bertanda hubung atau spasi (contoh: literacy-literacy -> literacy)
        $cleaned = preg_replace('/(\b[\p{L}\p{N}_]+\b)[-\s]+\1\b/u', '$1', $cleaned);

        // Koreksi typo percakapan umum
        $replacements = [
            '/\batay\b/u' => 'atau',
            '/\byah\b/u'  => 'ya',
            '/\bgitu\b/u' => '',
        ];
        $cleaned = preg_replace(array_keys($replacements), array_values($replacements), $cleaned);

        // Bersihkan tanda baca selain huruf dan angka
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleaned);
        $rawTokens = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($rawTokens)) {
            return [];
        }

        // Saring stopword dan token yang terlalu pendek (< 2 karakter)
        $filtered = array_values(array_filter($rawTokens, function ($t) {
            return !in_array($t, $this->stopwords, true) && mb_strlen($t, 'UTF-8') >= 2;
        }));

        // Hanya buang stopword jika masih tersisa minimal 1 token bermakna
        return !empty($filtered) ? $filtered : $rawTokens;
    }

    /**
     * Pencarian jadwal bertingkat (Cascading Search):
     * Tier 1: Multi-Token AND (Setiap token wajib cocok di field searchable)
     * Tier 2: Multi-Token OR + Ranking Skor Relevansi (Jika token > 1 dan Tier 1 kosong)
     * Tier 3: Smart Fuzzy Typo Fallback (Levenshtein / similar_text >= 65%)
     *
     * @param string|null $keyword
     * @param string|null $day Filter hari kuliah (Senin - Sabtu)
     * @return array
     */
    public function searchSchedules(?string $keyword = null, ?string $day = null): array
    {
        $tokens = $this->extractTokens($keyword);

        // Jika keyword kosong, langsung query umum
        if (empty($tokens)) {
            return $this->queryFusekiOrFallback([], $day, 'AND', 'SPARQL (Apache Jena Fuseki)');
        }

        // TIER 1: Multi-Token AND (Paling Akurat)
        $tier1 = $this->queryFusekiOrFallback($tokens, $day, 'AND', 'SPARQL (Apache Jena Fuseki)');
        if (!empty($tier1['data'])) {
            return $tier1;
        }

        // TIER 2: Multi-Token OR + Ranking Relevansi (Jika token > 1)
        if (count($tokens) > 1) {
            $tier2 = $this->queryFusekiOrFallback($tokens, $day, 'OR', 'SPARQL (Multi-Token OR)');
            if (!empty($tier2['data'])) {
                $ranked = $this->rankResultsByRelevance($tier2['data'], $tokens, $keyword);
                if (!empty($ranked)) {
                    return [
                        'status' => 'success',
                        'source' => $tier2['source'],
                        'count'  => count($ranked),
                        'data'   => $ranked,
                    ];
                }
            }
        }

        // TIER 3: Smart Fuzzy Typo Fallback
        $correctedTokens = $this->getFuzzyCorrectedTokens($tokens);
        if (!empty($correctedTokens) && $correctedTokens !== $tokens) {
            // Coba AND dengan token yang telah dikoreksi
            $tier3And = $this->queryFusekiOrFallback($correctedTokens, $day, 'AND', 'SPARQL (Fuzzy Typo Fallback)');
            if (!empty($tier3And['data'])) {
                return $tier3And;
            }

            // Jika AND masih kosong dan token > 1, coba OR
            if (count($correctedTokens) > 1) {
                $tier3Or = $this->queryFusekiOrFallback($correctedTokens, $day, 'OR', 'SPARQL (Fuzzy Typo Fallback)');
                if (!empty($tier3Or['data'])) {
                    $ranked = $this->rankResultsByRelevance($tier3Or['data'], $correctedTokens, $keyword);
                    if (!empty($ranked)) {
                        return [
                            'status' => 'success',
                            'source' => $tier3Or['source'],
                            'count'  => count($ranked),
                            'data'   => $ranked,
                        ];
                    }
                }
            }
        }

        // TIER 4: Colloquial & Stem Expansion Fallback (Bahasa Sehari-hari ke Istilah Akademis)
        $stemMap = self::getColloquialStemMap();
        foreach ($tokens as $token) {
            $lowerT = mb_strtolower($token, 'UTF-8');
            if (isset($stemMap[$lowerT])) {
                foreach ($stemMap[$lowerT] as $synonym) {
                    $stemRes = $this->queryFusekiOrFallback([$synonym], $day, 'AND', "SPARQL (Colloquial Stem: {$token} -> {$synonym})");
                    if (!empty($stemRes['data'])) {
                        return $stemRes;
                    }
                }
            }
        }

        return [
            'status' => 'success',
            'source' => 'SPARQL (Apache Jena Fuseki)',
            'count'  => 0,
            'data'   => [],
        ];
    }

    /**
     * Eksekusi query ke Fuseki dengan fallback otomatis ke MySQL/SQLite.
     */
    protected function queryFusekiOrFallback(array $tokens, ?string $day, string $mode = 'AND', string $sourceLabel = 'SPARQL (Apache Jena Fuseki)'): array
    {
        try {
            $sparql = $this->buildSearchQuery($tokens, $day, $mode);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/sparql-results+json',
                ])
                ->asForm()
                ->post($this->queryEndpoint, [
                    'query' => $sparql,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $this->formatSparqlResults($data);

                if (count($results) > 0 || empty($tokens)) {
                    return [
                        'status' => 'success',
                        'source' => $sourceLabel,
                        'count'  => count($results),
                        'data'   => $results,
                    ];
                }
            }

            return $this->fallbackToMysqlWithTokens($tokens, $day, $mode);
        } catch (\Throwable $e) {
            Log::info('SparqlService: Fuseki query unavailable (' . $e->getMessage() . '). Using MySQL fallback.');
            return $this->fallbackToMysqlWithTokens($tokens, $day, $mode);
        }
    }

    /**
     * Membangun SPARQL SELECT query dinamis multi-token (AND atau OR).
     */
    protected function buildSearchQuery(array $tokens = [], ?string $day = null, string $mode = 'AND'): string
    {
        $prefixHeader = '';
        foreach ($this->namespaces as $prefix => $uri) {
            $prefixHeader .= "PREFIX {$prefix}: <{$uri}>\n";
        }

        $filters = [];

        if (!empty($tokens)) {
            $tokenClauses = [];
            foreach ($tokens as $token) {
                $sanitized = addslashes(trim($token));
                $tokenClauses[] = "(" .
                    "CONTAINS(LCASE(STR(?namaMk)), \"{$sanitized}\") || " .
                    "CONTAINS(LCASE(STR(?kodeMk)), \"{$sanitized}\") || " .
                    "CONTAINS(LCASE(STR(?namaDosen)), \"{$sanitized}\") || " .
                    "CONTAINS(LCASE(STR(?namaRuangan)), \"{$sanitized}\") || " .
                    "CONTAINS(LCASE(STR(?namaGedung)), \"{$sanitized}\") || " .
                    "CONTAINS(LCASE(STR(?kodeGedung)), \"{$sanitized}\")" .
                ")";
            }

            $glue = ($mode === 'OR') ? ' || ' : ' && ';
            $filters[] = '(' . implode($glue, $tokenClauses) . ')';
        }

        if (!empty($day)) {
            $sanitizedDay = addslashes(trim($day));
            $filters[] = "(?hari = \"{$sanitizedDay}\")";
        }

        $filterClause = !empty($filters) ? 'FILTER(' . implode(' && ', $filters) . ')' : '';

        return <<<SPARQL
{$prefixHeader}
SELECT ?jadwal ?hari ?jamMulai ?jamSelesai ?semester ?kelompokKelas
       ?kodeMk ?namaMk ?sks ?tipeMk
       ?namaDosen ?nipDosen ?emailDosen
       ?ruangan ?namaRuangan ?lantaiRuangan ?kapasitasRuangan ?pathFoto360
       ?gedung ?namaGedung ?kodeGedung ?latitude ?longitude
WHERE {
    ?jadwal a usu:Jadwal ;
            usu:hari ?hari ;
            usu:jamMulai ?jamMulai ;
            usu:jamSelesai ?jamSelesai ;
            usu:semester ?semester ;
            usu:kelompokKelas ?kelompokKelas .

    OPTIONAL {
        ?jadwal usu:memilikiMataKuliah ?mk .
        ?mk usu:kodeMataKuliah ?kodeMk ;
            usu:namaMataKuliah ?namaMk ;
            usu:sks ?sks ;
            usu:tipeMataKuliah ?tipeMk .
    }

    OPTIONAL {
        ?jadwal usu:diajarOleh ?dosen .
        ?dosen usu:namaDosen ?namaDosen .
        OPTIONAL { ?dosen usu:nipDosen ?nipDosen . }
        OPTIONAL { ?dosen usu:emailDosen ?emailDosen . }
    }

    OPTIONAL {
        ?jadwal usu:berlokasiDi ?ruangan .
        ?ruangan usu:namaRuangan ?namaRuangan ;
                 usu:lantaiRuangan ?lantaiRuangan ;
                 usu:kapasitasRuangan ?kapasitasRuangan .
        OPTIONAL { ?ruangan usu:pathFoto360 ?pathFoto360 . }

        OPTIONAL {
            ?ruangan usu:beradaDiGedung ?gedung .
            ?gedung usu:namaGedung ?namaGedung ;
                    usu:kodeGedung ?kodeGedung ;
                    usu:latitude ?latitude ;
                    usu:longitude ?longitude .
        }
    }

    {$filterClause}
}
ORDER BY ?hari ?jamMulai ?namaMk
SPARQL;
    }

    /**
     * Hitung skor relevansi hasil pencarian OR dan urutkan dari yang paling cocok.
     */
    protected function rankResultsByRelevance(array $results, array $tokens, ?string $rawKeyword): array
    {
        $keywordLower = mb_strtolower($rawKeyword ?? '', 'UTF-8');

        foreach ($results as &$item) {
            $score = 0;
            $mkName = mb_strtolower($item['mata_kuliah']['nama'] ?? '', 'UTF-8');
            $mkCode = mb_strtolower($item['mata_kuliah']['kode'] ?? '', 'UTF-8');
            $dosen = mb_strtolower($item['dosen']['nama'] ?? '', 'UTF-8');
            $ruang = mb_strtolower($item['ruangan']['nama'] ?? '', 'UTF-8');
            $gedung = mb_strtolower($item['gedung']['nama'] ?? '', 'UTF-8');
            $allText = "{$mkName} {$mkCode} {$dosen} {$ruang} {$gedung}";

            // Bonus jika frasa utuh cocok
            if (!empty($keywordLower) && str_contains($allText, $keywordLower)) {
                $score += 50;
            }

            // Hitung kecocokan tiap token
            $matchedTokens = 0;
            foreach ($tokens as $t) {
                if (str_contains($allText, $t)) {
                    $matchedTokens++;
                    $score += 20;
                    if (str_contains($mkName, $t)) $score += 15;
                    if (str_contains($dosen, $t)) $score += 10;
                }
            }

            // Kemiripan teks dengan nama mata kuliah
            if (!empty($keywordLower)) {
                similar_text($keywordLower, $mkName, $sim);
                $score += ($sim * 0.4);
            }

            $item['_score'] = $score;
        }
        unset($item);

        usort($results, fn($a, $b) => ($b['_score'] <=> $a['_score']));

        return array_values(array_map(function ($it) {
            unset($it['_score']);
            return $it;
        }, array_filter($results, fn($r) => ($r['_score'] ?? 0) >= 15)));
    }

    /**
     * Koreksi typo token berdasarkan kemiripan string terhadap kosakata master katalog.
     */
    protected function getFuzzyCorrectedTokens(array $tokens): array
    {
        $vocab = $this->getCatalogVocabulary();
        $corrected = [];
        $hasCorrection = false;

        foreach ($tokens as $token) {
            $tokenLower = mb_strtolower($token, 'UTF-8');
            $bestWord = null;
            $bestSim = 0;

            foreach ($vocab as $word) {
                if ($tokenLower === $word) {
                    $bestWord = $word;
                    $bestSim = 100;
                    break;
                }
                similar_text($tokenLower, $word, $sim);
                if ($sim > $bestSim && $sim >= 65) {
                    $bestSim = $sim;
                    $bestWord = $word;
                }
            }

            if ($bestWord !== null) {
                $corrected[] = $bestWord;
                if ($bestSim < 100) {
                    $hasCorrection = true;
                }
            } else {
                $corrected[] = $token;
            }
        }

        return $hasCorrection ? $corrected : [];
    }

    /**
     * Kosakata unik dari Course, Lecturer, dan Building untuk fuzzy matching.
     */
    protected function getCatalogVocabulary(): array
    {
        static $vocab = null;
        if ($vocab !== null) {
            return $vocab;
        }

        $rawText = [];
        try {
            foreach (Course::select('code', 'name')->get() as $c) {
                $rawText[] = $c->code;
                $rawText[] = $c->name;
            }
            foreach (Lecturer::select('name')->get() as $l) {
                $rawText[] = $l->name;
            }
            foreach (Building::select('code', 'name')->get() as $b) {
                $rawText[] = $b->code;
                $rawText[] = $b->name;
            }
        } catch (\Throwable $e) {
            return [];
        }

        $words = [];
        foreach ($rawText as $text) {
            $cleaned = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text), 'UTF-8');
            foreach (preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                if (mb_strlen($w, 'UTF-8') >= 3 && !in_array($w, $this->stopwords, true)) {
                    $words[$w] = true;
                }
            }
        }

        $vocab = array_keys($words);
        return $vocab;
    }

    /**
     * Dapatkan rekomendasi kandidat mata kuliah terdekat berdasarkan kemiripan nama/kata.
     */
    public function getFuzzySuggestions(string $keyword, int $limit = 5): array
    {
        $tokens = $this->extractTokens($keyword);
        if (empty($tokens)) {
            return [];
        }

        $courses = Course::select('id', 'code', 'name')->get();
        $suggestions = [];

        foreach ($courses as $c) {
            $cNameLower = mb_strtolower($c->name, 'UTF-8');
            $words = preg_split('/\s+/', $cNameLower, -1, PREG_SPLIT_NO_EMPTY);
            $tokenMatches = 0;
            $maxSim = 0;

            foreach ($tokens as $token) {
                $tokenSim = 0;
                foreach ($words as $w) {
                    similar_text($token, $w, $sim);
                    if ($sim > $tokenSim) {
                        $tokenSim = $sim;
                    }
                }
                if ($tokenSim >= 70) {
                    $tokenMatches++;
                }
                if ($tokenSim > $maxSim) {
                    $maxSim = $tokenSim;
                }
            }

            similar_text(implode(' ', $tokens), $cNameLower, $fullSim);
            $score = ($tokenMatches * 30) + ($maxSim * 0.4) + ($fullSim * 0.3);

            if ($tokenMatches > 0 || $maxSim >= 65 || $fullSim >= 45) {
                $suggestions[] = [
                    'id'         => $c->id,
                    'code'       => $c->code,
                    'name'       => $c->name,
                    'similarity' => round($maxSim, 1),
                    'score'      => round($score, 1),
                ];
            }
        }

        usort($suggestions, fn($a, $b) => ($b['score'] <=> $a['score']));
        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Format SPARQL JSON bindings to standardized application array structure.
     */
    protected function formatSparqlResults(array $jsonResponse): array
    {
        $bindings = $jsonResponse['results']['bindings'] ?? [];
        $formatted = [];

        foreach ($bindings as $b) {
            $uri = $b['jadwal']['value'] ?? '';
            $id = (int) basename($uri);

            $ruanganUri = $b['ruangan']['value'] ?? null;
            $ruanganId = $ruanganUri ? (int) basename($ruanganUri) : null;

            $gedungUri = $b['gedung']['value'] ?? null;
            $gedungId = $gedungUri ? (int) basename($gedungUri) : null;

            $photoPath = $b['pathFoto360']['value'] ?? null;
            $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;

            $formatted[] = [
                'uri' => $uri,
                'id' => $id,
                'hari' => $b['hari']['value'] ?? '',
                'jam_mulai' => $b['jamMulai']['value'] ?? '',
                'jam_selesai' => $b['jamSelesai']['value'] ?? '',
                'semester' => $b['semester']['value'] ?? '',
                'kelompok_kelas' => $b['kelompokKelas']['value'] ?? '',
                'mata_kuliah' => [
                    'kode' => $b['kodeMk']['value'] ?? '',
                    'nama' => $b['namaMk']['value'] ?? '',
                    'sks' => isset($b['sks']['value']) ? (int) $b['sks']['value'] : 0,
                    'tipe' => $b['tipeMk']['value'] ?? 'MKWK',
                ],
                'dosen' => [
                    'nama' => $b['namaDosen']['value'] ?? '',
                    'nip' => $b['nipDosen']['value'] ?? null,
                    'email' => $b['emailDosen']['value'] ?? null,
                ],
                'ruangan' => [
                    'id' => $ruanganId,
                    'nama' => $b['namaRuangan']['value'] ?? '',
                    'lantai' => isset($b['lantaiRuangan']['value']) ? (int) $b['lantaiRuangan']['value'] : 1,
                    'kapasitas' => isset($b['kapasitasRuangan']['value']) ? (int) $b['kapasitasRuangan']['value'] : 0,
                    'foto_360' => $photoUrl,
                    'foto_360_raw' => $photoPath,
                ],
                'gedung' => [
                    'id' => $gedungId,
                    'nama' => $b['namaGedung']['value'] ?? '',
                    'kode' => $b['kodeGedung']['value'] ?? '',
                    'latitude' => isset($b['latitude']['value']) ? (float) $b['latitude']['value'] : null,
                    'longitude' => isset($b['longitude']['value']) ? (float) $b['longitude']['value'] : null,
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Fallback to direct MySQL Eloquent query with token filtering.
     */
    public function fallbackToMysqlWithTokens(array $tokens = [], ?string $day = null, string $mode = 'AND'): array
    {
        $query = Schedule::with(['course', 'lecturer', 'room.building']);

        if (!empty($tokens)) {
            $query->where(function ($q) use ($tokens, $mode) {
                foreach ($tokens as $token) {
                    $condition = function ($sub) use ($token) {
                        $sub->whereHas('course', function ($c) use ($token) {
                            $c->where('name', 'like', "%{$token}%")
                              ->orWhere('code', 'like', "%{$token}%");
                        })
                        ->orWhereHas('lecturer', function ($l) use ($token) {
                            $l->where('name', 'like', "%{$token}%");
                        })
                        ->orWhereHas('room', function ($r) use ($token) {
                            $r->where('name', 'like', "%{$token}%")
                              ->orWhereHas('building', function ($b) use ($token) {
                                  $b->where('name', 'like', "%{$token}%")
                                    ->orWhere('code', 'like', "%{$token}%");
                              });
                        });
                    };

                    if ($mode === 'OR') {
                        $q->orWhere($condition);
                    } else {
                        $q->where($condition);
                    }
                }
            });
        }

        if (!empty($day)) {
            $query->where('day', trim($day));
        }

        $schedules = $query->orderBy('day')->orderBy('time_start')->get();
        $formatted = $this->formatEloquentSchedules($schedules);

        $source = ($mode === 'OR') ? 'MySQL (Multi-Token OR Fallback)' : 'MySQL (Fallback)';

        return [
            'status' => 'success',
            'source' => $source,
            'count'  => count($formatted),
            'data'   => $formatted,
        ];
    }

    /**
     * Fallback standard MySQL query (kompatibilitas method lama).
     */
    public function fallbackToMysql(?string $keyword = null, ?string $day = null): array
    {
        $tokens = $this->extractTokens($keyword);
        return $this->fallbackToMysqlWithTokens($tokens, $day, 'AND');
    }

    /**
     * Standardize Eloquent Schedule collection format to match SPARQL array structure.
     */
    protected function formatEloquentSchedules($schedules): array
    {
        return $schedules->map(function ($s) {
            $photoPath = $s->room?->photo_360_path;
            $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;

            return [
                'uri' => 'http://usuclassmap.usu.ac.id/resource/jadwal/' . $s->id,
                'id' => $s->id,
                'hari' => $s->day,
                'jam_mulai' => substr($s->time_start, 0, 5),
                'jam_selesai' => substr($s->time_end, 0, 5),
                'semester' => $s->semester,
                'kelompok_kelas' => $s->class_group,
                'mata_kuliah' => [
                    'kode' => $s->course?->code ?? '',
                    'nama' => $s->course?->name ?? '',
                    'sks' => (int) ($s->course?->credits ?? 0),
                    'tipe' => $s->course?->type ?? 'MKWK',
                ],
                'dosen' => [
                    'nama' => $s->lecturer?->name ?? '',
                    'nip' => $s->lecturer?->nip,
                    'email' => $s->lecturer?->email,
                ],
                'ruangan' => [
                    'id' => $s->room?->id,
                    'nama' => $s->room?->name ?? '',
                    'lantai' => (int) ($s->room?->floor ?? 1),
                    'kapasitas' => (int) ($s->room?->capacity ?? 0),
                    'foto_360' => $photoUrl,
                    'foto_360_raw' => $photoPath,
                ],
                'gedung' => [
                    'id' => $s->room?->building?->id,
                    'nama' => $s->room?->building?->name ?? '',
                    'kode' => $s->room?->building?->code ?? '',
                    'latitude' => (float) ($s->room?->building?->latitude ?? 0),
                    'longitude' => (float) ($s->room?->building?->longitude ?? 0),
                ],
            ];
        })->toArray();
    }
}
