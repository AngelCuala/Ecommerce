<?php

namespace Tests\Feature\Buyer;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Voucher Center: grouped sections, store vouchers under their store, Claim all, and the empty state. */
class VoucherCenterPageTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::forceCreate(['name' => 'Juan Dela Cruz', 'email' => 'juan@gmail.com', 'password' => Hash::make('Mamimili@2026'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->seller = User::forceCreate(['name' => 'Sarap Laguna', 'email' => 'sarap@gmail.com', 'password' => Hash::make('Tindahan@2026'),
            'role' => 'seller', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function voucher(array $data): Voucher
    {
        return Voucher::create($data + ['name' => 'Promo', 'discount_type' => 'fixed', 'min_spend' => 0, 'per_user_limit' => 1,
            'is_active' => true, 'is_claimable' => true]);
    }

    private function threeKinds(): void
    {
        $this->voucher(['code' => 'FREESHIP', 'name' => 'Free shipping week', 'scope' => 'shipping', 'discount_value' => 60, 'min_spend' => 300]);
        $this->voucher(['code' => 'SALE1111', 'name' => '11.11 Sale', 'scope' => 'platform', 'discount_type' => 'percent', 'discount_value' => 10, 'max_discount' => 200, 'ends_at' => now()->addDay()]);
        $this->voucher(['code' => 'SARAP50', 'name' => 'Pasalubong deal', 'scope' => 'seller', 'seller_id' => $this->seller->id, 'discount_value' => 50, 'min_spend' => 500]);
    }

    public function test_vouchers_are_grouped_and_store_vouchers_sit_under_their_store(): void
    {
        $this->threeKinds();

        $page = $this->actingAs($this->buyer)->get(route('vouchers.center'))->assertOk()
            ->assertSee('data-voucher-hero', false)
            ->assertSeeInOrder(['data-voucher-section="shipping"', 'data-voucher-section="platform"', 'data-voucher-section="seller"'], false)
            ->assertSee('Sarap Laguna')->assertSee(route('stores.show', $this->seller->id), false)
            ->assertSee('Claim all (3)');
        if ($out = env('VOUCHER_CENTER_HTML')) {
            file_put_contents($out, $page->getContent());   // for a screenshot; set only by hand
        }

        $this->actingAs($this->buyer)->get(route('vouchers.center', ['tab' => 'shipping']))->assertOk()
            ->assertSee('data-voucher-section="shipping"', false)->assertDontSee('data-voucher-section="seller"', false);
    }

    public function test_claim_all_claims_every_voucher_on_the_tab_once(): void
    {
        $this->threeKinds();

        $this->actingAs($this->buyer)->from(route('vouchers.center', ['tab' => 'shipping']))
            ->post(route('vouchers.claim-all', ['tab' => 'shipping']))->assertSessionHas('success', '1 voucher added to My Vouchers.');
        $this->assertSame(['FREESHIP'], VoucherClaim::where('user_id', $this->buyer->id)->get()->map(fn ($c) => $c->voucher->code)->all());

        $this->actingAs($this->buyer)->from(route('vouchers.center'))
            ->post(route('vouchers.claim-all'))->assertSessionHas('success', '2 vouchers added to My Vouchers.');
        $this->assertSame(3, VoucherClaim::where('user_id', $this->buyer->id)->count());

        $this->actingAs($this->buyer)->from(route('vouchers.center'))
            ->post(route('vouchers.claim-all'))->assertSessionHas('error', 'There are no new vouchers to claim here.');
        $this->actingAs($this->buyer)->get(route('vouchers.center'))->assertDontSee('data-claim-all', false);
    }

    public function test_empty_tab_explains_itself(): void
    {
        $this->actingAs($this->buyer)->get(route('vouchers.center', ['tab' => 'store']))->assertOk()
            ->assertSee('data-voucher-empty', false)->assertSee('No store vouchers right now')
            ->assertSee(route('stores.following'), false);
    }

    public function test_guests_see_the_page_and_are_asked_to_sign_in(): void
    {
        $this->threeKinds();
        $this->get(route('vouchers.center'))->assertOk()->assertSee('Sign in to claim vouchers')->assertDontSee('data-claim-all', false);
        $this->post(route('vouchers.claim-all'))->assertRedirect();
        $this->assertSame(0, VoucherClaim::count());
    }
}
