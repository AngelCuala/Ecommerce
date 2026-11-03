<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Order;
use App\Models\SellerViolation;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Product video shown to buyers, everyone can message ALVY, violation warnings reach the seller,
 * used-up vouchers aren't offered, no Google sign-in, Logistics without Incoming Parcels,
 * and riders get Maps directions to the buyer's pin.
 */
class WebFixesNov4Test extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        Storage::fake('public');
        $this->admin = User::forceCreate(['name' => 'ALVY Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    // ── Product video ──

    public function test_the_product_video_plays_on_the_product_page(): void
    {
        $this->p02->update(['video_path' => 'products/videos/clip.mp4']);

        $this->get(route('products.show', $this->p02->slug ?? $this->p02->id))->assertOk()
            ->assertSee('data-product-video', false)->assertSee(asset('storage/products/videos/clip.mp4'), false)
            ->assertSee('Play the product video');
    }

    public function test_the_seller_previews_replaces_and_removes_the_video(): void
    {
        $this->p02->update(['video_path' => 'products/videos/old.mp4']);
        Storage::disk('public')->put('products/videos/old.mp4', 'x');

        $this->actingAs($this->seller1)->get(route('seller.books.edit', $this->p02->id))->assertOk()
            ->assertSee('data-video-preview', false)->assertSee(asset('storage/products/videos/old.mp4'), false)
            ->assertSee('Remove this video');
    }

    // ── Messaging ALVY ──

    public function test_buyers_riders_and_logistics_can_message_the_admin(): void
    {
        foreach ([$this->buyer1, $this->rider1->user, $this->logistics1] as $user) {
            $threads = $this->actingAs($user)->getJson(route('chat.threads'))->assertOk()->json('threads');
            $this->assertContains('ALVY Admin', array_column($threads, 'label'), $user->role . ' sees ALVY: ' . json_encode(array_column($threads, 'label')));

            $key = Message::threadKey($user->id, $this->admin->id);
            $this->actingAs($user)->postJson(route('chat.send', ['direct', $key]), ['body' => 'Hello ALVY'])->assertOk();
            $this->assertDatabaseHas('messages', ['sender_id' => $user->id, 'receiver_id' => $this->admin->id, 'body' => 'Hello ALVY']);
        }
    }

    // ── Violations ──

    public function test_a_violation_notifies_the_seller_and_shows_on_their_dashboard(): void
    {
        $this->actingAs($this->admin)->post(route('admin.compliance.store'), [
            'seller_id' => $this->seller1->id, 'product_id' => $this->p02->id, 'type' => 'misleading_info',
            'action' => 'warning', 'note' => 'The photos show a different basket.',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'They were notified'));

        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seller1->id, 'title' => 'Warning from ALVY: Misleading Information']);
        $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('data-violation-warning', false)->assertSee('The photos show a different basket.');

        $v = SellerViolation::latest('id')->first();
        $this->actingAs($this->seller2)->post(route('seller.violations.acknowledge', $v->id))->assertNotFound();   // not theirs
        $this->actingAs($this->seller1)->post(route('seller.violations.acknowledge', $v->id))->assertSessionHas('success');
        $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertDontSee('data-violation-warning', false);
    }

    // ── Vouchers ──

    public function test_used_up_vouchers_are_not_offered_at_checkout(): void
    {
        $line = $this->cart($this->buyer1, $this->p02, 2);
        $plat = Voucher::where('code', 'MCH50')->first();
        $codes = fn () => collect(app(VoucherService::class)->options(collect([$line->fresh()]), $this->buyer1)['sellers'][$this->seller1->id]['vouchers'])->pluck('code')->all();

        $this->assertContains('MCH50', $codes());
        $this->assertNotContains('MCHMAX', $codes());   // no uses left for anyone

        $order = $this->placeOrder($this->buyer2, [$this->cart($this->buyer2, $this->p02)])->first();
        VoucherRedemption::create(['voucher_id' => $plat->id, 'user_id' => $this->buyer1->id, 'order_id' => $order->id, 'discount_amount' => 50]);
        $this->assertNotContains('MCH50', $codes());   // this buyer already used it
    }

    // ── Sign in ──

    public function test_no_google_sign_in_on_the_login_page(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('Continue with Google');
    }

    // ── Logistics ──

    public function test_incoming_parcels_is_gone_and_the_sidebar_is_grouped(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.incoming-parcels'))->assertRedirect(route('sc.tracking'));
        $this->actingAs($this->logistics1)->get(route('sc.shipments'))->assertOk()
            ->assertDontSee('Incoming Parcels')
            ->assertSee('class="nav-section"', false)->assertSee('Overview')->assertSee('Parcels')
            ->assertSee('class="sh-tabs"', false);
    }

    // ── Rider directions ──

    public function test_the_rider_gets_directions_to_the_buyers_pin(): void
    {
        $delivery = $this->deliveryFor($this->rider1);
        $address = $delivery->parcel->order->deliveryAddress;

        $this->actingAs($this->rider1->user)->get(route('courier.rider.deliveries.show', $delivery->id))->assertOk()
            ->assertSee('data-maps-link', false)->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);

        $address->update(['latitude' => 14.2117, 'longitude' => 121.1653]);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.deliveries.show', $delivery->id))->assertOk()
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=14.2117,121.1653', false)
            ->assertSee('Directions to the buyer');
    }
}
