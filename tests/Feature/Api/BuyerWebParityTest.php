<?php

namespace Tests\Feature\Api;

use App\Mail\PasswordResetMail;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's buyer side, kept 1:1 with the website: forgot password, shipment tracking, Voucher Center, assistant, shop stores. */
class BuyerWebParityTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
    }

    private function as(?string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $token ? $this->withToken($token) : $this->withHeaders(['Authorization' => '']);
    }

    public function test_forgot_password_emails_a_link_with_the_same_answer_for_any_email(): void
    {
        $sent = \App\Http\Controllers\Auth\PasswordResetLinkController::SENT;
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'juanmiguel.delacruz@example.com'])->assertOk()->assertJsonPath('message', $sent);
        Mail::assertSent(PasswordResetMail::class, 1);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('message', $sent);
        Mail::assertSent(PasswordResetMail::class, 1);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_order_detail_has_the_shipment_tracking_card(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $t = $this->as($buyer)->getJson('/api/v1/orders/' . $parcel->order_id)->assertOk()->json('order.tracking');
        $this->assertSame($parcel->tracking_number, $t['tracking_number']);
        $this->assertNotNull($t['waybill_number']);
        $this->assertSame(['Ordered', 'Collected', 'At sorting center', 'Out for delivery', 'Delivered', 'Received'], $t['milestones']);
        $this->assertSame(0, $t['reached']);
        $this->assertContains('Delivered', $t['expected']);
        $this->as($buyer)->getJson('/api/v1/orders/' . $parcel->order_id . '/payment-proof')->assertNotFound();   // no GCash receipt
    }

    public function test_voucher_center_lists_claimable_vouchers_by_tab(): void
    {
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $all = $this->as($buyer)->getJson('/api/v1/vouchers/center')->assertOk();
        $this->assertSame('all', $all->json('tab'));
        $this->assertSame(Voucher::running()->where('is_claimable', true)->count(), $all->json('counts.all'));
        $this->as($buyer)->getJson('/api/v1/vouchers/center?tab=store')->assertOk()->assertJsonPath('tab', 'store');
    }

    public function test_claim_all_on_a_voucher_center_tab_like_the_website(): void
    {
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $store = Voucher::running()->where('is_claimable', true)->where('scope', Voucher::SCOPE_SELLER)->get()
            ->filter(fn ($v) => $v->stateLabel() === 'Ongoing');
        $this->assertNotEmpty($store);

        $this->as($buyer)->postJson('/api/v1/vouchers/claim-all', ['tab' => 'store'])->assertOk()
            ->assertJsonPath('message', $store->count() . ' ' . \Illuminate\Support\Str::plural('voucher', $store->count()) . ' added to My Vouchers.');
        $this->assertEqualsCanonicalizing($store->pluck('id')->all(),
            \App\Models\VoucherClaim::where('user_id', $this->buyer1->id)->pluck('voucher_id')->all());   // only that tab's vouchers

        // Nothing new left on that tab: said plainly, nothing claimed twice.
        $this->as($buyer)->postJson('/api/v1/vouchers/claim-all', ['tab' => 'store'])->assertStatus(422)
            ->assertJsonPath('message', 'There are no new vouchers to claim here.');
        $this->as($buyer)->getJson('/api/v1/vouchers/center?tab=store')->assertOk()
            ->assertJsonPath('vouchers.0.claimed', true);
    }

    public function test_store_page_has_the_product_count(): void
    {
        $this->getJson('/api/v1/stores/' . $this->seller1->id)->assertOk()
            ->assertJsonPath('store.product_count', \App\Models\Product::published()->where('seller_id', $this->seller1->id)->count());
    }

    public function test_following_shows_each_stores_numbers_and_newest_products(): void
    {
        \App\Models\StoreFollow::create(['user_id' => $this->buyer1->id, 'seller_id' => $this->seller1->id]);
        $buyer = $this->buyer1->createToken('t')->plainTextToken;

        $s = $this->as($buyer)->getJson('/api/v1/following')->assertOk()->json('stores.0');
        $this->assertSame($this->seller1->id, $s['id']);
        $this->assertSame(\App\Models\Product::published()->where('seller_id', $this->seller1->id)->count(), $s['products']);
        $this->assertSame(1, $s['followers']);
        $this->assertArrayHasKey('rating', $s);
        $this->assertArrayHasKey('vouchers', $s);
        $this->assertLessThanOrEqual(4, count($s['latest']));
        $this->assertNotEmpty($s['latest']);
        $this->assertArrayHasKey('thumb_url', $s['latest'][0]);
    }

    public function test_assistant_history_and_shop_stores(): void
    {
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $this->as($buyer)->getJson('/api/v1/assistant/history')->assertOk()->assertJsonStructure(['messages', 'mode']);
        $this->as($this->seller1->createToken('t')->plainTextToken)->getJson('/api/v1/assistant/history')->assertNotFound();   // buyers and guests only

        $shop = $this->as(null)->getJson('/api/v1/shop?search=Maria')->assertOk();
        $this->assertContains('Maria Clara Handicrafts', collect($shop->json('stores'))->pluck('name')->all());
        $this->assertContains('Maria Clara Handicrafts', collect($shop->json('matched_stores'))->pluck('name')->all());
    }
}
