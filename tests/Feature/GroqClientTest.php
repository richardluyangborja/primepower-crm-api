<?php

use App\Actions\Ai\GroqClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('posts a prompt to the AI service and returns the generated text', function () {
    Config::set('groq.service_url', 'http://ai-service.test');
    Config::set('groq.timeout', 30);

    Http::fake([
        'ai-service.test/generate' => Http::response(['text' => 'Plain report body.']),
    ]);

    $text = (new GroqClient)->generate('Write a report.', 'Be brief.');

    expect($text)->toBe('Plain report body.');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://ai-service.test/generate'
            && $request['prompt'] === 'Write a report.'
            && $request['system'] === 'Be brief.';
    });
});

it('throws when the AI service fails', function () {
    Config::set('groq.service_url', 'http://ai-service.test');

    Http::fake([
        'ai-service.test/generate' => Http::response(status: 502),
    ]);

    (new GroqClient)->generate('Write a report.');
})->throws(RequestException::class);
