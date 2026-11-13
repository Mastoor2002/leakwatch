<?php

namespace App\Ai;

use App\Models\AiExplanation;
use App\Models\Finding;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Writes a plain-English explanation and fix for a finding using Claude.
 *
 * Only the check name, title, summary and the (already redacted) evidence are
 * sent - Surfacewatch never stores secret values, so there are none to leak.
 * Results are cached so the same kind of finding is only paid for once.
 */
class RemediationAdvisor
{
    public static function isEnabled(): bool
    {
        return filled(config('leakwatch.ai.api_key'));
    }

    public function explain(Finding $finding): AiExplanation
    {
        $site = $finding->site;
        $hash = hash('sha256', implode('|', [
            $finding->check_key,
            $finding->title,
            json_encode($finding->evidence),
            $site->is_laravel ? 'laravel' : 'other',
        ]));

        $cached = AiExplanation::where('context_hash', $hash)->first();

        if ($cached !== null) {
            return $cached;
        }

        if (! self::isEnabled()) {
            throw new RuntimeException('AI explanations are not configured (set ANTHROPIC_API_KEY).');
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('leakwatch.ai.api_key'),
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(45)
            ->acceptJson()
            ->post((string) config('leakwatch.ai.endpoint'), [
                'model' => (string) config('leakwatch.ai.model'),
                'max_tokens' => (int) config('leakwatch.ai.max_tokens', 700),
                'system' => $this->systemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->userPrompt($finding)],
                ],
            ])
            ->throw();

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return AiExplanation::create([
            'check_key' => $finding->check_key,
            'context_hash' => $hash,
            'body' => trim($text) ?: 'No explanation returned.',
            'model' => (string) config('leakwatch.ai.model'),
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are a senior application security engineer helping a small web agency fix a finding from an external security scan.
Write for a developer who is busy but not a security specialist.
Format:
**Why it matters** - two or three sentences, concrete, no fear-mongering.
**How to fix it** - numbered steps with exact commands or config snippets.
**How to confirm** - one or two sentences on how to check the fix worked.
Keep it under 250 words. Do not invent details that are not in the finding. Never suggest exploiting the issue.
TXT;
    }

    private function userPrompt(Finding $finding): string
    {
        $site = $finding->site;

        return implode("\n", [
            'Finding: '.$finding->title,
            'Severity: '.$finding->severity->label(),
            'Check: '.$finding->check_key,
            'Framework: '.($site->is_laravel ? 'Laravel (PHP)' : 'unknown'),
            'Summary: '.$finding->summary,
            'Evidence (redacted, no secret values): '.json_encode($finding->evidence, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
