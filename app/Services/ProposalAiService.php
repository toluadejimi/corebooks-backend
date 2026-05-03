<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ProposalAiService
{
    /**
     * @return array{title: string, body_html: string}
     */
    public function generateDraft(Business $business, string $prompt): array
    {
        $key = config('openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('OpenAI is not configured. Set OPENAI_API_KEY in the server environment.');
        }

        $model = (string) config('openai.model', 'gpt-4o-mini');
        $base = rtrim((string) config('openai.base_url', 'https://api.openai.com/v1'), '/');
        $org = config('openai.organization');

        $system = <<<'TXT'
You write concise, professional business proposals for small and medium businesses.
Respond with a single JSON object only (no markdown fences) using exactly these keys:
- "title": short document title (string)
- "body_html": HTML fragment only (no <html> or <body> wrappers). Use <h2>, <h3>, <p>, <ul>, <li>, <strong> for structure. Tone: clear and confident. Keep length reasonable (roughly 400–1200 words of visible text unless the user explicitly asks for more).
Do not include script tags or external resources.
TXT;

        $user = "Business name: {$business->name}. Country: ".($business->country ?? 'NG').". Currency: ".($business->currency ?? 'NGN').".\n\nUser instructions / context:\n".$prompt;

        $headers = [
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ];
        if (is_string($org) && $org !== '') {
            $headers['OpenAI-Organization'] = $org;
        }

        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->post($base.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.65,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Empty response from OpenAI.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Could not parse proposal JSON from model.');
        }

        $title = isset($decoded['title']) ? trim((string) $decoded['title']) : '';
        $bodyHtml = isset($decoded['body_html']) ? trim((string) $decoded['body_html']) : '';

        if ($title === '') {
            $title = 'Proposal';
        }
        if ($bodyHtml === '') {
            throw new RuntimeException('Model returned no body_html.');
        }

        $bodyHtml = $this->sanitizeHtmlFragment($bodyHtml);

        return ['title' => Str::limit($title, 180), 'body_html' => $bodyHtml];
    }

    private function sanitizeHtmlFragment(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*([\'"]).*?\1#i', '', $html) ?? $html;

        return Str::limit($html, 200_000);
    }
}
