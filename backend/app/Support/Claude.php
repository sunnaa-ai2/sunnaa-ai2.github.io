<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Minimal client for the Claude Messages API. The AI features are off until
 * ANTHROPIC_API_KEY is set in .env; the site then hides their buttons.
 */
class Claude
{
    public const URL = 'https://api.anthropic.com/v1/messages';

    public static function enabled(): bool
    {
        return (bool) config('spark.ai.key');
    }

    public static function model(): string
    {
        return config('spark.ai.model');
    }

    /**
     * @param  string|array  $system  plain text, or Messages API system blocks
     * @param  array  $messages  Messages API messages
     */
    public static function ask(string|array $system, array $messages, int $maxTokens = 1200): string
    {
        abort_unless(self::enabled(), 503, 'AI_DISABLED');

        $res = Http::withHeaders([
            'x-api-key' => config('spark.ai.key'),
            'anthropic-version' => '2023-06-01',
        ])->acceptJson()->timeout(100)->post(self::URL, [
            'model' => self::model(),
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ]);

        if ($res->failed()) {
            Log::warning('Claude API '.$res->status().': '.Str::limit($res->body(), 400));
            abort(in_array($res->status(), [429, 529], true) ? 429 : 502, $res->status() === 429 || $res->status() === 529 ? 'AI_BUSY' : 'AI_UNAVAILABLE');
        }

        return trim(collect($res->json('content', []))->where('type', 'text')->pluck('text')->implode("\n"));
    }
}
