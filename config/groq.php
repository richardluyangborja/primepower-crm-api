<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Groq AI Service
    |--------------------------------------------------------------------------
    |
    | A separate minimal Python service (ai-service/) makes Groq text
    | completions on our behalf. Laravel never holds the Groq API key; it
    | only POSTs a composed prompt to the service and gets plain text back.
    |
    */

    'service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),

    'timeout' => (int) env('AI_SERVICE_TIMEOUT', 120),
];
