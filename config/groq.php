<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Groq
    |--------------------------------------------------------------------------
    |
    | The AI Reports module calls Groq's OpenAI-compatible chat completions
    | endpoint directly from Laravel. The API key is never committed — set
    | GROQ_API_KEY in your environment.
    |
    */

    'api_key' => env('GROQ_API_KEY'),

    'url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions'),

    'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),

    'timeout' => (int) env('GROQ_TIMEOUT', 120),
];
