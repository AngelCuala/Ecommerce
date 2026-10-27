<?php

namespace App\Services\Assistant;

/**
 * The language model behind the shop assistant. One call = one Messages API request; the caller runs
 * the tool loop. The real one is ClaudeAssistantModel; tests bind a scripted fake.
 */
interface AssistantModel
{
    /**
     * @param  array<int, array<string, mixed>>  $messages  conversation so far (Messages API shape, camelCase keys)
     * @param  array<int, array<string, mixed>>  $tools     tool definitions
     * @return object  a message with ->content (blocks with ->type, ->text / ->id, ->name, ->input) and ->stopReason
     */
    public function respond(string $system, array $messages, array $tools): object;

    /** False when the assistant can't run (no API key / turned off): the widget is hidden. */
    public function available(): bool;
}
