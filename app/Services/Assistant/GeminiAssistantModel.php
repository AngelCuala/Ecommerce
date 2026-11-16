<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
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

    /** Cache key prefix: a model that hit its limit or hung is skipped for a minute. */
    private const COOLDOWN = 'assistant-gemini-cooldown:';

    /** The model this reply is on; the rest of its tool loop prefers it. */
    private ?string $activeModel = null;

    /**
     * Google's stand-in thought signature for a function call another model made: lets a back-up model finish a
     * reply when the first one hits its limit mid-way (Gemini 3 otherwise rejects calls without their signature).
     */
    private const FOREIGN_SIGNATURE = 'skip_thought_signature_validator';

    public function respond(string $system, array $messages, array $tools): object
    {
        $body = $this->body($system, $messages, $tools);

        $models = array_values(array_unique(array_filter(array_merge(
            [(string) config('services.assistant.model')],
            array_map('trim', explode(',', (string) config('services.assistant.fallback_models'))),
        ))));
        // Mid-reply (tool results going back): the model that started it goes first.
        if ($this->activeModel && $this->hasModelTurns($messages)) {
            $models = array_values(array_unique([$this->activeModel, ...$models]));
        }
        // Skip models that just hit their limit or hung (for a minute), unless none are left.
        $ready = array_values(array_filter($models, fn ($m) => ! Cache::has(self::COOLDOWN . $m)));
        $models = $ready ?: $models;

        $last = null;
        $started = microtime(true);
        foreach ($models as $model) {
            // A long back-up list must not keep the buyer waiting: after this long, Quick help answers instead.
            if ($last && microtime(true) - $started > (float) config('services.assistant.fallback_budget', 25)) {
                break;
            }
            try {
                $json = $this->call($model, $body);
                $this->activeModel = $model;
                return $this->message($json, $model);
            } catch (GeminiBusy $e) {
                $last = $e;   // overloaded or unknown model: try the next one
            }
        }

        throw new RuntimeException($last?->getMessage() ?? 'Gemini is unavailable.');
    }

    /**
     * One model, with a couple of short retries while Google says it's busy. A 429 (its rate limit) isn't retried:
     * a back-up model answers instead, since retrying only spends more of the limit and adds seconds.
     */
    private function call(string $model, array $body): array
    {
        // Parts another model wrote (earlier in this reply) carry that model's signatures: swap in the stand-in.
        foreach ($body['contents'] as &$content) {
            foreach ($content['parts'] as &$part) {
                $from = $part['_model'] ?? $model;
                unset($part['_model']);
                if ($from !== $model) {
                    if (isset($part['functionCall'])) {
                        $part['thoughtSignature'] = self::FOREIGN_SIGNATURE;
                    } else {
                        unset($part['thoughtSignature']);
                    }
                }
            }
            unset($part);
        }
        unset($content);

        $waits = (array) config('services.assistant.retry_waits_ms', [700, 1500]);
        $timeout = (int) config('services.assistant.attempt_timeout', 15);
        if (str_starts_with($model, 'gemma-')) {
            // Gemma (the last back-up: big daily quota) rejects "low" thinking; "minimal" makes it ~5x faster.
            $body['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'minimal'];
            $timeout = max($timeout, 30);
        }
        for ($try = 0; ; $try++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.assistant.gemini_key')])
                    ->connectTimeout(10)
                    ->timeout($timeout)
                    ->post(self::ENDPOINT . rawurlencode($model) . ':generateContent', $body);
            } catch (ConnectionException $e) {
                // No answer in time: an overloaded model often just hangs. Move on instead of waiting again.
                Cache::put(self::COOLDOWN . $model, true, 60);
                throw new GeminiBusy('Gemini timed out (' . $model . '): ' . $e->getMessage(), 0, $e);
            }

            if ($response->successful()) {
                return (array) $response->json();
            }
            $message = 'Gemini ' . $response->status() . ' (' . $model . '): ' . ($response->json('error.message') ?? $response->body());
            if ($response->status() === 429) {
                Cache::put(self::COOLDOWN . $model, true, 60);
                throw new GeminiBusy($message);
            }
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
                        $part = $b['raw'] ?? ['functionCall' => ['name' => $b['name']]];
                        // args must stay a JSON object: a decoded {} comes back as [] and Gemini rejects a list.
                        $part['functionCall']['args'] = (object) ($part['functionCall']['args'] ?? ($b['input'] ?? []));
                        $parts[] = $part;
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
    private function message(array $json, string $model): object
    {
        $candidate = $json['candidates'][0] ?? null;
        $blocked = ! empty($json['promptFeedback']['blockReason'])
            || in_array($candidate['finishReason'] ?? null, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true);

        $blocks = [];
        foreach ($candidate['content']['parts'] ?? [] as $i => $part) {
            $part['_model'] = $model;   // which model wrote it (see call()); never sent to Google
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
