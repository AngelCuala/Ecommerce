<?php

namespace Tests\Feature\Buyer;

use App\Models\Product;
use App\Services\Assistant\AssistantTools;
use App\Services\ProductColorTagger;
use App\Support\ProductColors;
use App\Support\ProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Colors read from product pictures, so "red" / "pula" finds red items in the chatbot and the shop search. */
class ProductColorsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        config(['services.assistant.gemini_key' => 'test-key']);
    }

    private function gemini(array $colors): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode(['colors' => $colors])]]]]]];
    }

    public function test_color_words_in_english_and_tagalog(): void
    {
        $this->assertSame('red', ProductColors::fromWord('pula'));
        $this->assertSame('red', ProductColors::fromWord('Pulang'));
        $this->assertSame('black', ProductColors::fromWord('itim'));
        $this->assertSame('gray', ProductColors::fromWord('grey'));
        $this->assertNull(ProductColors::fromWord('bag'));
        $this->assertSame(['red', 'white'], ProductColors::clean(['Red', 'white', 'red', 'rainbow']));
    }

    public function test_the_picture_is_read_once_and_a_busy_model_hands_over_to_the_next(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/bag.jpg', 'fake-jpeg');
        $product = Product::first();
        $product->forceFill(['image' => 'products/bag.jpg', 'colors' => null, 'colors_image' => null])->saveQuietly();

        $first = ProductColorTagger::MODELS[0];
        Http::fake([
            "*{$first}:generateContent" => Http::response(['error' => ['code' => 429]], 429),
            '*' => Http::response($this->gemini(['red', 'white'])),
        ]);

        $this->artisan('products:tag-colors', ['--pause' => 0])->assertSuccessful();
        $this->assertSame(['red', 'white'], $product->fresh()->colors);
        $this->assertSame('products/bag.jpg', $product->fresh()->colors_image);

        // Only the picture and the product name go to the AI.
        Http::assertSent(fn ($r) => str_contains($r->url(), ProductColorTagger::MODELS[1])
            && $r['contents'][0]['parts'][0]['inlineData']['data'] === base64_encode('fake-jpeg'));

        // Read once: a second run does not ask again; a new picture is read again.
        $this->assertFalse(ProductColorTagger::pending()->whereKey($product->id)->exists());
        $product->forceFill(['image' => 'products/bag2.jpg'])->saveQuietly();
        $this->assertTrue(ProductColorTagger::pending()->whereKey($product->id)->exists());
    }

    public function test_the_chatbot_lists_red_items_for_red_or_pula(): void
    {
        $red = Product::published()->take(2)->get();
        $red->each(fn ($p) => $p->forceFill(['colors' => ['red', 'black']])->saveQuietly());
        Product::published()->whereNotIn('id', $red->pluck('id'))->get()->each(fn ($p) => $p->forceFill(['colors' => ['blue']])->saveQuietly());

        foreach (['red', 'pula', 'may pulang items ba kayo'] as $q) {
            $r = app(AssistantTools::class)->run('search_products', ['query' => $q], null);
            $this->assertEqualsCanonicalizing($red->pluck('title')->all(), array_column($r['products'], 'name'), $q);
            $this->assertSame('red, black', $r['products'][0]['colors']);
        }

        // Color and product word together: only red items that are also that product.
        $word = strtok($red[0]->title, ' ');
        $r = app(AssistantTools::class)->run('search_products', ['query' => 'pulang ' . $word], null);
        $this->assertContains($red[0]->title, array_column($r['products'], 'name'));
        $this->assertNotContains(null, array_column($r['products'], 'colors'));
    }

    public function test_the_shop_search_finds_colors_too_but_not_red_inside_other_words(): void
    {
        $red = Product::published()->first();
        $red->forceFill(['colors' => ['red']])->saveQuietly();
        $other = Product::published()->whereKeyNot($red->id)->first();
        $other->forceFill(['colors' => ['white'], 'title' => 'Paper Shredder Mini'])->saveQuietly();

        [$query] = (new ProductSearch())->apply(Request::create('/products'), 'pula');
        $titles = $query->pluck('title')->all();
        $this->assertContains($red->title, $titles);
        $this->assertNotContains('Paper Shredder Mini', $titles);   // "red" inside "Shredder" is not the color
    }
}
