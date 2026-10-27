<?php

namespace App\Services\Assistant;

use Anthropic\Client;

/**
 * The shop assistant on Claude (config/services.php "assistant"). Short chat answers, so effort is low;
 * if the model declines a request for policy reasons, the API retries it on its default fallback model.
 */
class ClaudeAssistantModel implements AssistantModel
{
    public function available(): bool
    {
        return (bool) config('services.assistant.enabled') && filled(config('services.assistant.api_key'));
    }

    public function respond(string $system, array $messages, array $tools): object
    {
        $client = new Client(apiKey: config('services.assistant.api_key'));

        return $client->beta->messages->create(
            model: config('services.assistant.model'),
            maxTokens: 4096,   // chat replies are short; the system prompt asks for a few sentences
            system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            messages: $messages,
            tools: $tools,
            outputConfig: ['effort' => 'low'],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => (float) config('services.assistant.timeout', 45), 'maxRetries' => 1],
        );
    }
}
