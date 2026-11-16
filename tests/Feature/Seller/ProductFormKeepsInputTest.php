<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** The seller's product form: a locked Stock box can't block a save, and a form that needs fixing can be sent again. */
class ProductFormKeepsInputTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function form(array $over = []): array
    {
        return array_merge([
            'title' => 'Buri Placemat Set', 'description' => 'Set of 6 placemats, handwoven buri.',
            'category_id' => $this->homeLiving->id, 'brand' => 'No Brand', 'condition' => 'New',
            'price' => '299.00', 'stock' => '30', 'weight_kg' => '0.6',
            'images' => [UploadedFile::fake()->image('photo.png')],
            'status' => 'active', 'action' => 'publish',
            // The SQLite test schema still has these NOT NULL (MySQL relaxed them in a later migration).
            'language' => 'English', 'format' => 'Standard',
        ], $over);
    }

    private function send(array $form)
    {
        return $this->actingAs($this->seller1)->postJson(route('seller.books.store'), $form);
    }

    public function test_variations_publish_without_a_stock_field_and_stock_is_their_total(): void
    {
        $form = $this->form(['title' => 'Variation Stock Tote', 'variations' => [
            ['name' => 'Red', 'price' => 100, 'stock' => 40],
            ['name' => 'Blue', 'price' => 100, 'stock' => 24],
        ]]);
        unset($form['stock']);   // the browser doesn't send a disabled input

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $form)->assertSessionHasNoErrors();

        $this->assertSame(64, Product::where('title', 'Variation Stock Tote')->firstOrFail()->stock);
    }

    public function test_stock_is_still_required_without_variations(): void
    {
        $this->send($this->form(['stock' => '']))->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_a_blank_variation_stock_is_still_reported(): void
    {
        $this->send($this->form(['variations' => [['name' => 'Red', 'price' => 100, 'stock' => '']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('variations.0.stock');
    }

    public function test_a_form_that_failed_can_be_sent_again_with_the_same_token(): void
    {
        $form = $this->form(['title' => 'Retried Tote', 'form_token' => 'tok-' . uniqid()]);

        $this->send(array_merge($form, ['weight_kg' => '']))->assertUnprocessable()
            ->assertJsonPath('errors.weight_kg.0', 'Please enter the weight.');
        $this->assertSame(0, Product::where('title', 'Retried Tote')->count());

        $this->send($form)->assertOk()->assertJsonPath('redirect', route('seller.books.index'));
        $this->assertSame(1, Product::where('title', 'Retried Tote')->count());
    }

    public function test_a_saved_form_sent_twice_still_makes_one_product(): void
    {
        $form = $this->form(['title' => 'Twice Sent Tote', 'form_token' => 'tok-' . uniqid()]);

        $this->send($form)->assertOk();
        $this->send($form)->assertOk();

        $this->assertSame(1, Product::where('title', 'Twice Sent Tote')->count());
    }

    public function test_the_success_message_waits_for_the_next_page(): void
    {
        $this->send($this->form(['title' => 'Message Tote']))->assertOk()->assertSessionHas('success');
    }
}
