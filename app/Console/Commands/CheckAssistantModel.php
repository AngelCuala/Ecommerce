<?php

namespace App\Console\Commands;

use App\Services\Assistant\GeminiAssistantModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Checks that the ALVY Assistant's Gemini key works and the configured model exists for it. */
class CheckAssistantModel extends Command
{
    protected $signature = 'assistant:check';

    protected $description = 'Check the AI chatbot key and model (lists the Gemini models the key can use)';

    public function handle(): int
    {
        if (config('services.assistant.provider') === 'claude') {
            $this->info('The assistant uses Claude (' . config('services.assistant.model') . '). This check is for Gemini.');
            return self::SUCCESS;
        }
        $key = config('services.assistant.gemini_key');
        $model = (string) config('services.assistant.model');
        if (blank($key)) {
            $this->error('GEMINI_API_KEY is empty in .env. Create a key at https://aistudio.google.com/apikey, then run php artisan config:clear.');
            return self::FAILURE;
        }

        $res = Http::withHeaders(['x-goog-api-key' => $key])->timeout(20)->get(GeminiAssistantModel::ENDPOINT, ['pageSize' => 1000]);
        if (! $res->successful()) {
            $this->error('Google refused the key (' . $res->status() . '): ' . ($res->json('error.message') ?? $res->body()));
            return self::FAILURE;
        }

        $models = collect($res->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => str_replace('models/', '', $m['name']));

        if ($models->contains($model)) {
            $this->info("OK: the key works and \"{$model}\" is available. The chatbot will use it.");
            return self::SUCCESS;
        }

        $this->error("\"{$model}\" is not available for this key.");
        $flash = $models->filter(fn ($m) => str_contains($m, 'flash'))->values();
        $this->line('Flash models this key can use: ' . ($flash->isNotEmpty() ? $flash->implode(', ') : '(none)'));
        $this->line('Set ASSISTANT_MODEL in .env to one of them, then run php artisan config:clear.');
        return self::FAILURE;
    }
}
