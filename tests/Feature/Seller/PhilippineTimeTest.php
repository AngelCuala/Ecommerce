<?php

namespace Tests\Feature\Seller;

use App\Models\FlashSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Times typed by sellers are Philippine time (stored as UTC), like vouchers and promotions already were. */
class PhilippineTimeTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        // 9:00 AM in Manila.
        Carbon::setTestNow(Carbon::parse('2026-10-28 09:00', 'Asia/Manila'));
    }

    public function test_a_flash_sale_starts_at_the_philippine_time_typed(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), [
            'product_id' => $this->p02->id, 'flash_price' => 300, 'quantity' => 5,
            'starts_at' => '2026-10-28T09:19', 'ends_at' => '2026-10-28T10:19',
        ])->assertSessionHasNoErrors();

        $sale = FlashSale::latest('id')->firstOrFail();
        $this->assertSame('2026-10-28 01:19:00', $sale->starts_at->utc()->toDateTimeString());
        $this->assertSame('Upcoming', $sale->stateLabel());
        $this->actingAs($this->seller1)->get(route('seller.flash-sales.index'))->assertSee('Oct 28, 9:19 AM');

        // At 9:19 AM Manila it is live.
        Carbon::setTestNow(Carbon::parse('2026-10-28 09:19', 'Asia/Manila'));
        $this->assertTrue($sale->fresh()->isRunning());
    }

    public function test_a_start_already_past_in_philippine_time_is_refused_and_the_form_keeps_what_was_typed(): void
    {
        $this->actingAs($this->seller1)->from(route('seller.flash-sales.index'))->post(route('seller.flash-sales.store'), [
            'product_id' => $this->p02->id, 'flash_price' => 300, 'quantity' => 5,
            'starts_at' => '2026-10-28T08:30', 'ends_at' => '2026-10-28T10:00',
        ])->assertSessionHasErrors(['starts_at' => 'The flash sale must start in the future.'])
            ->assertSessionHasInput('starts_at', '2026-10-28T08:30');
    }

    public function test_the_app_gets_the_flash_sale_in_philippine_time(): void
    {
        $token = $this->seller1->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/seller/flash-sales', [
            'product_id' => $this->p02->id, 'flash_price' => 300, 'quantity' => 5,
            'starts_at' => '2026-10-28 09:19', 'ends_at' => '2026-10-28 10:19',
        ])->assertOk();
        $this->app['auth']->forgetGuards();
        $list = $this->withToken($token)->getJson('/api/v1/seller/flash-sales')->assertOk();
        $starts = $list->json('sales.0.starts_at');
        $this->assertSame('2026-10-28 09:19', Carbon::parse($starts)->tz('Asia/Manila')->format('Y-m-d H:i'));
    }
}
