<?php

namespace App\Actions\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiAiClient
{
    /**
     * Whether the integration is ready to call (an API key is set).
     */
    public function configured(): bool
    {
        return filled(config('ai.api_key'));
    }

    /**
     * Send a chat completion to Gemini and return the assistant's text.
     *
     * @param  array{system?: string, temperature?: float, max_tokens?: int, timeout?: int}  $options
     */
    public function chat(string $user, array $options = []): string
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'AI is not configured. Set AI_API_KEY (and if needed AI_API_URL) in your environment.'
            );
        }

        $response = Http::withToken(config('ai.api_key'))
            ->acceptJson()
            ->timeout($options['timeout'] ?? 120)
            ->post(config('ai.api_url').'chat/completions', [
                'model' => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $options['system']
                        ?? 'You are a concise assistant for a sales CRM.'],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => $options['temperature'] ?? 0.3,
                'max_tokens' => $options['max_tokens'] ?? 4096,
            ]);

        $response->throw();

        return $response->json('choices.0.message.content', '');
    }
}
