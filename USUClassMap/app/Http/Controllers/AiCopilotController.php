<?php

namespace App\Http\Controllers;

use App\Services\AiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AiCopilotController extends Controller
{
    /**
     * Service pengelola orkestrasi LLM Bedrock & Tools.
     */
    protected AiCopilotService $copilotService;

    /**
     * Konstruktor dengan Dependency Injection.
     */
    public function __construct(AiCopilotService $copilotService)
    {
        $this->copilotService = $copilotService;
    }

    /**
     * Endpoint utama POST /copilot/chat untuk menerima input pengguna
     * dan mengembalikan balasan cerdas dari Rexa Copilot.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function chat(Request $request): JsonResponse
    {
        try {
            // 1. Validasi Input Ketat (Mencegah Prompt Injection & Token Flooding)
            $validated = $request->validate([
                'message'            => 'required|string|min:1|max:500',
                'history'            => 'nullable|array|max:10',
                'history.*.role'    => 'required|string|in:user,assistant',
                'history.*.content' => 'required|string|max:1500',
            ], [
                'message.required'   => 'Pertanyaan atau pesan tidak boleh kosong.',
                'message.max'        => 'Pesan Anda melebihi batas maksimal 500 karakter.',
                'history.max'        => 'Riwayat percakapan melebihi batas maksimal 10 pertukaran pesan.',
                'history.*.role.in'  => 'Role riwayat percakapan tidak valid.',
            ]);

            $userMessage = trim($validated['message']);
            $history = $validated['history'] ?? [];

            // 2. Eksekusi Bedrock Converse Loop via AiCopilotService
            $result = $this->copilotService->chat($userMessage, $history);

            return response()->json([
                'status'         => 'success',
                'message'        => $result['reply'],
                'reply'          => $result['reply'],
                'mapAction'      => $result['map_action'],
                'map_action'     => $result['map_action'],
                'facultySummary' => $result['faculty_summary'] ?? [],
                'quickReplies'   => $result['quick_replies'] ?? [],
                'mapActions'     => $result['map_actions'] ?? [],
                'schedules'      => $result['schedules'] ?? [],
                'usage'          => $result['usage'] ?? null,
            ], 200);

        } catch (ValidationException $ve) {
            return response()->json([
                'status'  => 'error',
                'message' => $ve->getMessage(),
                'errors'  => $ve->errors(),
            ], 422);

        } catch (\Throwable $e) {
            // Catat log untuk investigasi developer
            Log::error('[AiCopilotController] Gagal memproses percakapan: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'user_id'   => optional($request->user())->id,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'status'             => 'error',
                'message'            => 'Maaf, Rexa sedang mengalami sedikit kendala koneksi ke server AI (' . $e->getMessage() . '). Mengaktifkan mode pencarian lokal...',
                'fallback_available' => true,
            ], 500);
        }
    }
}
