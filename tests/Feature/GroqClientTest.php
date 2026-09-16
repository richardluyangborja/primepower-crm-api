<?php

use App\Actions\Ai\GroqClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('posts a chat completion to Groq and returns the generated text', function () {
    Config::set('groq.api_key', 'test-key');
    Config::set('groq.url', 'https://api.groq.com/openai/v1/chat/completions');
    Config::set('groq.model', 'openai/gpt-oss-120b');
    Config::set('groq.timeout', 30);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Plain report body.']]],
        ]),
    ]);

    $text = (new GroqClient)->generate('Write a report.', 'Be brief.');

    expect($text)->toBe('Plain report body.');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'openai/gpt-oss-120b'
            && $request['messages'] === [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Write a report.'],
            ];
    });
});

it('sends only the user message when no system instruction is given', function () {
    Config::set('groq.api_key', 'test-key');
    Config::set('groq.url', 'https://api.groq.com/openai/v1/chat/completions');
    Config::set('groq.model', 'openai/gpt-oss-120b');

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Reply.']]],
        ]),
    ]);

    (new GroqClient)->generate('Write a report.');

    Http::assertSent(function ($request) {
        return $request['messages'] === [
            ['role' => 'user', 'content' => 'Write a report.'],
        ];
    });
});

it('throws when Groq fails', function () {
    Config::set('groq.api_key', 'test-key');
    Config::set('groq.url', 'https://api.groq.com/openai/v1/chat/completions');

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response(status: 502),
    ]);

    (new GroqClient)->generate('Write a report.');
})->throws(RequestException::class);
