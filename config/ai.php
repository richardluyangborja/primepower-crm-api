<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | The AI module talks to an OpenAI-compatible chat completions endpoint.
    | Default is Google Gemini (free tier) via its OpenAI-compatible surface.
    | The key is never committed — set AI_API_KEY in your environment.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    'api_key' => env('AI_API_KEY'),

    'api_url' => env('AI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/openai/'),

    'model' => env('AI_MODEL', 'gemini-3.8-flash'),
];
