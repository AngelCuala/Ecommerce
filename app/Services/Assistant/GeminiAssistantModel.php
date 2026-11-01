<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The shop assistant on Google Gemini (config/services.php "assistant", provider "gemini").
 *
 * ShopAssistant speaks the Messages-API shape (text / tool_use / tool_result blocks). This class turns
 * that into a Gemini generateContent request and turns Gemini's reply back into the same shape:
 *   - assistant turns  → role "model"; each block is sent back as the exact part Gemini returned
 *                        (Gemini 3 needs its thought signatures returned with function calls),
 *   - tool_result      → functionResponse parts, named after the call they answer,
 *   - functionCall     → a tool_use block, and stopReason "tool_use" so the tool loop runs.
 */
class GeminiAssistantModel implements AssistantModel
{
    public const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function available(): bool
    {
        return (bool) config('services.assistant.enabled') && filled(config('services.assistant.gemini_key'));
    }

    /** Busy / overloaded answers from Google: worth a retry, then the back-up model. */
    private const BUSY = [429, 500, 503, 504];

    /** The model this reply started on; a reply's tool loop stays on it (thought signatures are per model). */
    private ?string $activeModel = null;

    public function respond(string $system, array $messages, array $tools): object
    {
        $body = $this->body($system, $messages, $tools);

        // Mid-reply (tool results going back): stay on the model that started it.
        if ($this->activeModel && $this->hasModelTurns($messages)) {
            return $this->message($this->call($this->activeModel, $body));
        }

        $models = array_values(array_unique(array_filter(array_merge(
            [(string) config('services.assistant.model')],
            array_map('trim', explode(',', (string) config('services.assistant.fallback_models'))),
        ))));

        $last = null;
        foreach ($models as $model) {
            try {
                $json = $this->call($model, $body);
                $this->activeModel = $model;
                return $this->message($json);
            } catch (GeminiBusy $e) {
                $last = $e;   // overloaded or unknown model: try the next one
            }
        }

        throw new RuntimeException($last?->getMessage() ?? 'Gemini is unavailable.');
    }

    /** One model, with a couple of short retries while Google says it's busy. */
    private function call(string $model, array $body): array
    {
        $waits = (array) config('services.assistant.retry_waits_ms', [700, 1500]);
        for ($try = 0; ; $try++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.assistant.gemini_key')])
                    ->connectTimeout(10)
                    ->timeout((int) config('services.assistant.attempt_timeout', 15))
                    ->post(self::ENDPOINT . rawurlencode($model) . ':generateContent', $body);
            } catch (ConnectionException $e) {
                // No answer in time: an overloaded model often just hangs. Move on instead of waiting again.
                throw new GeminiBusy('Gemini timed out (' . $model . '): ' . $e->getMessage(), 0, $e);
            }

            if ($response->successful()) {
                return (array) $response->json();
            }
            $message = 'Gemini ' . $response->status() . ' (' . $model . '): ' . ($response->json('error.message') ?? $response->body());
            if (in_array($response->status(), self::BUSY, true) && $try < count($waits)) {
                usleep((int) $waits[$try] * 1000);
                continue;
            }
            if (in_array($response->status(), [...self::BUSY, 404], true)) {
                throw new GeminiBusy($message);
            }
            throw new RuntimeException($message);   // a bad key or request: another model won't help
        }
    }

    private function hasModelTurns(array $messages): bool
    {
        return collect($messages)->contains(fn ($m) => ($m['role'] ?? null) === 'assistant' && is_array($m['content'] ?? null));
    }

    private function body(string $system, array $messages, array $tools): array
    {
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $this->contents($messages),
            'generationConfig'  => array_filter([
                'maxOutputTokens' => 4096,   // chat replies are short; the system prompt asks for a few sentences
                'thinkingConfig'  => filled($level = config('services.assistant.thinking')) ? ['thinkingLevel' => $level] : null,
            ]),
        ];
        if ($tools) {
            $body['tools'] = [['functionDeclarations' => array_map(fn ($t) => array_filter([
                'name'                 => $t['name'],
                'description'          => $t['description'] ?? null,
                'parametersJsonSchema' => $t['inputSchema'] ?? null,
            ]), $tools)]];
        }

        return $body;
    }

    /** Messages-API turns → Gemini contents. */
    private function contents(array $messages): array
    {
        $callNames = [];   // tool_use id → function name, for the functionResponse that answers it
        $contents = [];

        foreach ($messages as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $content = $m['content'] ?? '';

            if (is_string($content)) {
                $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
                continue;
            }

            $parts = [];
            foreach ($content as $block) {
                $b = (array) $block;
                switch ($b['type'] ?? null) {
                    case 'text':
                        $parts[] = $b['raw'] ?? ['text' => (string) $b['text']];
                        break;
                    case 'tool_use':
                        $callNames[$b['id']] = $b['name'];
                        $parts[] = $b['raw'] ?? ['functionCall' => ['name' => $b['name'], 'args' => (object) ($b['input'] ?? [])]];
                        break;
                    case 'tool_result':
                        $id = $b['toolUseID'] ?? ($b['tool_use_id'] ?? '');
                        $result = is_string($b['content'] ?? null) ? json_decode($b['content'], true) : ($b['content'] ?? null);
                        $result = is_array($result) && ! array_is_list($result) ? $result : ['result' => $result ?? ($b['content'] ?? null)];
                        $parts[] = ['functionResponse' => array_filter([
                            'id'       => str_starts_with((string) $id, 'gemini-') ? null : $id,   // only Gemini's own call ids
                            'name'     => $callNames[$id] ?? 'tool',
                            'response' => $result,
                        ], fn ($v) => $v !== null)];
                        break;
                }
            }
            if ($parts) {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $contents;
    }

    /** Gemini reply → an object shaped like a Messages-API message (->content blocks, ->stopReason). */
    private function message(array $json): object
    {
        $candidate = $json['candidates'][0] ?? null;
        $blocked = ! empty($json['promptFeedback']['blockReason'])
            || in_array($candidate['finishReason'] ?? null, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true);

        $blocks = [];
        foreach ($candidate['content']['parts'] ?? [] as $i => $part) {
            if (isset($part['functionCall'])) {
                $blocks[] = (object) [
                    'type'  => 'tool_use',
                    'id'    => $part['functionCall']['id'] ?? ('gemini-' . $i . '-' . bin2hex(random_bytes(3))),
                    'name'  => (string) $part['functionCall']['name'],
                    'input' => (array) ($part['functionCall']['args'] ?? []),
                    'raw'   => $part,
                ];
            } elseif (isset($part['text']) && empty($part['thought'])) {
                $blocks[] = (object) ['type' => 'text', 'text' => (string) $part['text'], 'raw' => $part];
            }
        }

        $stop = match (true) {
            $blocked && ! $blocks                                         => 'refusal',
            collect($blocks)->contains(fn ($b) => $b->type === 'tool_use') => 'tool_use',
            ($candidate['finishReason'] ?? '') === 'MAX_TOKENS'           => 'max_tokens',
            default                                                        => 'end_turn',
        };

        return (object) ['content' => $blocks, 'stopReason' => $stop];
    }
}
