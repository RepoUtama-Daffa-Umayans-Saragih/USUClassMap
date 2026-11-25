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
     * Service pengelola orkestrasi Local Grounded Engines & ClassAI LLM.
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
     * dan mengembalikan balasan grounded + interactive cards dari Tanya Ucup.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function chat(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message'           => 'required|string|min:1|max:500',
                'history'           => 'nullable|array|max:10',
                'history.*.role'    => 'required|string|in:user,assistant',
                'history.*.content' => 'required|string|max:4000',
                'user_lat'          => 'nullable|numeric|between:-90,90',
                'user_lng'          => 'nullable|numeric|between:-180,180',
                'current_page'      => 'nullable|string|max:255',
                'user_context'      => 'nullable|array',
            ], [
                'message.required'  => 'Pertanyaan atau pesan tidak boleh kosong.',
                'message.max'       => 'Pesan Anda melebihi batas maksimal 500 karakter.',
                'history.max'       => 'Riwayat percakapan melebihi batas maksimal 10 pertukaran pesan.',
                'history.*.role.in' => 'Role riwayat percakapan tidak valid.',
            ]);

            $userMessage = trim($validated['message']);
            $history = $validated['history'] ?? [];
            $runtimeContext = [
                'user_lat'     => isset($validated['user_lat']) ? (float) $validated['user_lat'] : null,
                'user_lng'     => isset($validated['user_lng']) ? (float) $validated['user_lng'] : null,
                'current_page' => $validated['current_page'] ?? null,
                'user_context' => $validated['user_context'] ?? [],
            ];

            $result = $this->copilotService->chat($userMessage, $history, $runtimeContext);

            return response()->json([
                'status'          => 'success',
                'message'         => $result['reply'],
                'reply'           => $result['reply'],
                'intent'          => $result['intent'] ?? 'general_campus_qa',
                'advisory_note'   => $result['advisory_note'] ?? null,
                'cards'           => $result['cards'] ?? [],
                'actions'         => $result['actions'] ?? [],
                'mapAction'       => $result['map_action'] ?? null,
                'map_action'      => $result['map_action'] ?? null,
                'facultySummary'  => $result['faculty_summary'] ?? [],
                'quickReplies'    => $result['quick_replies'] ?? [],
                'mapActions'      => $result['map_actions'] ?? [],
                'schedules'       => $result['schedules'] ?? [],
                'grounded_source' => $result['grounded_source'] ?? 'local_grounded',
                'usage'           => $result['usage'] ?? null,
            ], 200);
        } catch (ValidationException $ve) {
            return response()->json([
                'status'  => 'error',
                'message' => $ve->getMessage(),
                'errors'  => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('[AiCopilotController] Unexpected error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'user_id'   => optional($request->user())->id,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'status'             => 'error',
                'message'            => 'Maaf, Ucup mengalami gangguan teknis tak terduga. Silakan coba lagi sebentar.',
                'fallback_available' => true,
            ], 500);
        }
    }
}
