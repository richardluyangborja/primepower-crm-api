<?php

namespace App\Actions\Ai;

use Illuminate\Support\Facades\Http;

class GroqClient
{
    /**
     * Send a composed prompt to the Groq AI service and return the generated text.
     *
     * @param  string  $prompt  The report/action-suggestion prompt authored by Laravel.
     * @param  string|null  $system  Optional system instruction; falls back to the service default.
     */
    public function generate(string $prompt, ?string $system = null): string
    {
        $response = Http::baseUrl(config('groq.service_url'))
            ->acceptJson()
            ->timeout(config('groq.timeout', 120))
            ->post('/generate', [
                'prompt' => $prompt,
                'system' => $system,
            ]);

        $response->throw();

        return $response->json('text');
    }
}
