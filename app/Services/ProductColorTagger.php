<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Assistant\GeminiAssistantModel;
use App\Support\ProductColors;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the main colors of a product from its picture with Gemini (the chatbot's API key), once per
 * picture. Only the picture is sent: no buyer or seller data. Uses the high-volume "lite" models
 * (500 pictures a day on the free tier); a model at its limit rests for a minute and the next one is tried.
 */
class ProductColorTagger
{
    public const MODELS = ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest'];
    private const REST = 'product-colors-model-rest:';

    /** True when the picture was read (colors saved, maybe none); false when no model could do it now. */
    public function tag(Product $product): bool
    {
        if (! $product->image || ! Storage::disk('public')->exists($product->image)) {
            $product->forceFill(['colors' => [], 'colors_image' => $product->image])->saveQuietly();   // nothing to look at
            return true;
        }
        $bytes = Storage::disk('public')->get($product->image);
        $mime = Storage::disk('public')->mimeType($product->image) ?: 'image/jpeg';

        $body = [
            'contents' => [['role' => 'user', 'parts' => [
                ['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($bytes)]],
                ['text' => "This is a product photo from an online shop. The product is \"{$product->title}\". "
                    . 'List the main colors of the product itself (not the background, packaging or props), most visible first. '
                    . 'Use only these names: ' . implode(', ', ProductColors::PALETTE) . '. At most 3.'],
            ]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => ['type' => 'object', 'properties' => [
                    'colors' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ProductColors::PALETTE]],
                ], 'required' => ['colors']],
                'temperature' => 0,
            ],
        ];

        foreach (self::MODELS as $model) {
            if (Cache::has(self::REST . $model)) {
                continue;
            }
            try {
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.assistant.gemini_key')])
                    ->timeout(30)->post(GeminiAssistantModel::ENDPOINT . rawurlencode($model) . ':generateContent', $body);
            } catch (ConnectionException) {
                Cache::put(self::REST . $model, true, 60);
                continue;
            }
            if (in_array($response->status(), [429, 500, 503, 504, 404], true)) {
                Cache::put(self::REST . $model, true, 60);
                continue;
            }
            if (! $response->successful()) {
                report(new \RuntimeException("Product colors ({$model}): HTTP {$response->status()} " . mb_substr($response->body(), 0, 300)));
                return false;
            }
            $text = $response->json('candidates.0.content.parts.0.text');
            $colors = ProductColors::clean((array) (json_decode((string) $text, true)['colors'] ?? []));
            $product->forceFill(['colors' => array_slice($colors, 0, 3), 'colors_image' => $product->image])->saveQuietly();
            return true;
        }

        return false;
    }

    /** Products whose current picture hasn't been read yet (new products, changed pictures). */
    public static function pending()
    {
        return Product::query()->whereNotNull('image')
            ->where(fn ($q) => $q->whereNull('colors_image')->orWhereColumn('colors_image', '!=', 'image'));
    }
}
