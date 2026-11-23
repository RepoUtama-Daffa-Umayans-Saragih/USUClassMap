<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fitur Saklar Copilot (Feature Flag)
    |--------------------------------------------------------------------------
    */
    'enabled' => env('COPILOT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | ClassAI API Configuration (OpenAI-Compatible Format)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi integrasi ClassAI Router menggunakan format standar OpenAI.
    | Endpoint default: 'https://router.classai.id/v1/chat/completions'
    | Model default: 'claude-haiku-4.5'
    |
    */
    'endpoint' => env('CLASSAI_ENDPOINT', 'https://router.classai.id/v1/chat/completions'),
    'api_key'  => env('CLASSAI_API_KEY', ''),
    'model_id' => env('AI_MODEL_ID', 'claude-haiku-4.5'),

    'max_tokens'  => (int) env('COPILOT_MAX_TOKENS', 1024),
    'temperature' => (float) env('COPILOT_TEMPERATURE', 0.2), // Rendah untuk presisi faktual jadwal

    /*
    |--------------------------------------------------------------------------
    | Orchestration & Safety Guardrails (100% Read-Only)
    |--------------------------------------------------------------------------
    |
    | Batasan perulangan panggilan tool dan proteksi token context window.
    | Sesuai arsitektur, maksimal 4 putaran tool calling sebelum mengembalikan jawaban final.
    |
    */
    'max_turns'     => (int) env('COPILOT_MAX_TURNS', 4),
    'history_limit' => (int) env('COPILOT_HISTORY_LIMIT', 8), // Maksimal 8 pesan terakhir dikirim ke LLM
    'rate_limit'    => (int) env('COPILOT_RATE_LIMIT', 20),    // 20 requests per menit per user

    /*
    |--------------------------------------------------------------------------
    | Network Timeout
    |--------------------------------------------------------------------------
    |
    | Batas waktu pemanggilan HTTP REST API dalam satuan detik (default: 120 detik / 2 menit).
    |
    */
    'timeout' => (int) env('COPILOT_TIMEOUT', 120),
];
