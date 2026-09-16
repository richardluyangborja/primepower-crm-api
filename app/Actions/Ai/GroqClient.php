<?php

namespace App\Actions\Ai;

use Illuminate\Support\Facades\Http;

class GroqClient
{
    /**
     * Send a chat completion to Groq and return the assistant's text.
     *
     * @param  string  $prompt  The report prompt authored by Laravel.
     * @param  string|null  $system  Optional system instruction.
     */
    public function generate(string $prompt, ?string $system = null): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        if ($system !== null) {
            array_unshift($messages, ['role' => 'system', 'content' => $system]);
        }

        $response = Http::withToken(config('groq.api_key'))
            ->acceptJson()
            ->timeout(config('groq.timeout', 120))
            ->post(config('groq.url'), [
                'messages' => $messages,
                'model' => config('groq.model'),
            ]);

        $response->throw();

        return $response->json('choices.0.message.content', '');
    }
}
