<?php

namespace Tests\Feature;

use App\Models\ContactStrike;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Rules\NoContactDetails;
use App\Support\ContactGuard;
use App\Support\TrustedSeller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Keeping deals on ALVY: contact details hidden in chats and refused in listings, strikes, masked buyer phone, badge. */
class ContactGuardTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    public function test_contact_details_are_found_and_ordinary_numbers_are_left_alone(): void
    {
        $hidden = [
            'Text me 09171234567' => 'phone', '+63 917 123 4567' => 'phone', '0917-123-4567' => 'phone',
            'zero nine one seven one two three four five six seven' => 'phone', 'sero siyam isa pito isa dalawa tatlo apat lima anim pito' => 'phone',
            'juan.dc@gmail.com' => 'email', 'www.myshop.ph' => 'link', 'facebook.com/juanstore' => 'link',
            'fb: juanstore' => 'social', 'IG @juanstore' => 'social',
        ];
        foreach ($hidden as $text => $kind) {
            $r = ContactGuard::scrub($text);
            $this->assertContains($kind, $r['kinds'], $text);
            $this->assertStringContainsString(ContactGuard::MASK, $r['text'], $text);
        }
        foreach (['Order #000107 waybill PH261110372554197 ALVY-F3406B4B4C', 'Price ₱1,200 for 2 pcs, 3,000,000 total',
                  'https://alvy.trade/products/12', 'Nov 10, 2026 11:52 PM', 'isa lang po, salamat', 'pwede gcash?'] as $text) {
            $this->assertSame(['text' => $text, 'kinds' => []], ContactGuard::scrub($text), $text);
        }
        $this->assertSame(['mention'], ContactGuard::scrub('add me sa facebook')['kinds']);
        $this->assertSame('0917•••4567', ContactGuard::maskPhone('09171234567'));
    }

    public function test_a_seller_sharing_a_number_is_hidden_warned_and_reviewed_at_three_strikes(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-guard@test.local', 'password' => 'secret-Pass1', 'role' => 'admin']);
        $thread = Message::threadKey($this->seller1->id, $this->buyer1->id);

        $this->actingAs($this->seller1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'Text me 09171234567, cheaper there'])
            ->assertOk()->assertJson(['contact_hidden' => true]);
        $m = Message::latest('id')->first();
        $this->assertStringNotContainsString('09171234567', $m->body);   // never stored
        $this->assertSame('hidden', $m->safety_flag);
        $this->assertSame(1, ContactStrike::where('user_id', $this->seller1->id)->count());

        // Both people see the notice; a burst in the same chat is one strike a day.
        $this->actingAs($this->buyer1)->getJson(route('chat.thread', ['type' => 'direct', 'id' => $thread]))->assertOk()
            ->assertJsonPath('messages.0.notice', Message::SAFETY_NOTES['hidden'])->assertJsonPath('protection', Message::PROTECTION);
        $this->actingAs($this->seller1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'juan@gmail.com'])->assertOk();
        $this->assertSame(1, ContactStrike::count());

        // Buyers are never given strikes; a mention only brings a reminder.
        $this->actingAs($this->buyer1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'my number 09181234567'])->assertOk();
        $this->assertSame(1, ContactStrike::count());
        $this->actingAs($this->buyer1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'meron ka sa shopee?'])->assertOk();
        $this->assertSame('reminder', Message::latest('id')->first()->safety_flag);

        // Strikes 2 and 3 (other days): the 3rd tells the administrators; nothing is suspended automatically.
        $this->travel(2)->days();
        $this->actingAs($this->seller1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'viber 09171234567'])->assertOk();
        $this->travel(2)->days();
        $this->actingAs($this->seller1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'fb: juanstore'])->assertOk();
        $this->assertSame(3, ContactStrike::count());
        $this->assertSame('seller', $this->seller1->fresh()->role);
        $this->assertTrue(UserNotification::where('user_id', $admin->id)->where('title', 'Seller to review: off-platform contact')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Your store is under review')->exists());

        $this->actingAs($admin)->get(route('admin.compliance.index'))->assertOk()->assertSee('Off-platform contact strikes')->assertSee('3 / 3');
        $this->actingAs($admin)->post(route('admin.compliance.strikes-reviewed', $this->seller1->id))->assertSessionHas('success');
        $this->assertSame(0, ContactStrike::whereNull('reviewed_at')->count());
    }

    public function test_a_seller_sees_their_strikes_appeals_and_an_accepted_appeal_stops_counting(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-appeal@test.local', 'password' => 'secret-Pass1', 'role' => 'admin']);
        $thread = Message::threadKey($this->seller1->id, $this->buyer1->id);
        $this->actingAs($this->seller1)->postJson(route('chat.send', ['type' => 'direct', 'id' => $thread]), ['body' => 'Model no. 09171234567'])->assertOk();
        $strike = ContactStrike::sole();
        $counter = app(\App\Services\ContactStrikes::class);
        $this->assertSame(1, $counter->count($this->seller1));

        $this->actingAs($this->seller1)->get(route('seller.strikes'))->assertOk()
            ->assertSee('1 / 3')->assertSee('Phone number')->assertSee('This was a mistake');
        $this->actingAs($this->seller1)->from(route('seller.strikes'))->post(route('seller.strikes.appeal', $strike), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->seller1)->from(route('seller.strikes'))
            ->post(route('seller.strikes.appeal', $strike), ['reason' => 'It is the model number of the item, not my phone.'])->assertSessionHas('success');
        $this->assertSame('pending', $strike->fresh()->appeal_status);
        $this->actingAs($this->seller1)->from(route('seller.strikes'))
            ->post(route('seller.strikes.appeal', $strike), ['reason' => 'Appealing a second time, please.'])->assertSessionHas('error');
        // Another seller can't appeal it.
        $this->actingAs($this->seller2)->post(route('seller.strikes.appeal', $strike), ['reason' => 'Not mine but trying anyway.'])->assertNotFound();

        $this->actingAs($admin)->get(route('admin.compliance.index'))->assertOk()->assertSee('Appeals to decide (1)')->assertSee('model number of the item');
        $this->actingAs($admin)->from(route('admin.compliance.index'))
            ->post(route('admin.compliance.strike-appeal', $strike), ['decision' => 'accept', 'note' => 'Agreed, it is a model number.'])->assertSessionHas('success');
        $this->assertSame('accepted', $strike->fresh()->appeal_status);
        $this->assertSame(0, $counter->count($this->seller1));
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Strike appeal accepted')->exists());
    }

    public function test_strikes_are_deleted_after_a_year(): void
    {
        $old = ContactStrike::create(['user_id' => $this->seller1->id, 'kinds' => 'phone']);
        $old->forceFill(['created_at' => now()->subDays(ContactStrike::RETENTION_DAYS + 1)])->saveQuietly();
        $recent = ContactStrike::create(['user_id' => $this->seller1->id, 'kinds' => 'email']);

        $this->artisan('contact-strikes:prune')->assertSuccessful();
        $this->assertNull(ContactStrike::find($old->id));
        $this->assertNotNull(ContactStrike::find($recent->id));
    }

    public function test_chats_between_other_people_are_not_touched(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-guard2@test.local', 'password' => 'secret-Pass1', 'role' => 'admin']);
        $m = Message::create(['thread_key' => Message::threadKey($admin->id, $this->seller1->id), 'sender_id' => $admin->id,
            'receiver_id' => $this->seller1->id, 'body' => 'Call ALVY support at 09171234567']);
        $this->assertSame('Call ALVY support at 09171234567', $m->body);
        $this->assertNull($m->safety_flag);
    }

    public function test_listings_refuse_contact_details_and_sellers_see_a_partial_buyer_number(): void
    {
        $rule = ['description' => [new NoContactDetails]];
        $this->assertTrue(Validator::make(['description' => 'Message me on viber 09171234567 for a discount'], $rule)->fails());
        $this->assertTrue(Validator::make(['description' => 'Visit www.myshop.ph'], $rule)->fails());
        $this->assertFalse(Validator::make(['description' => 'Cotton shirt, 2 colours, size S to XL. ₱350 each.'], $rule)->fails());

        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['phone' => '09171234567']);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('0917•••4567')->assertDontSee('09171234567');
    }

    public function test_trusted_seller_badge_needs_completed_orders_and_no_strikes(): void
    {
        $this->assertFalse(TrustedSeller::is($this->seller1));
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        for ($i = 0; $i < TrustedSeller::MIN_ORDERS; $i++) {
            $copy = $order->replicate();
            $copy->forceFill(['status' => Order::COMPLETED, 'tracking_number' => 'TRUST-' . $i])->saveQuietly();
        }
        TrustedSeller::flush();
        $this->assertTrue(TrustedSeller::is($this->seller1));
        $this->get(route('stores.show', $this->seller1->id))->assertOk()->assertSee('data-trusted-seller', false);

        ContactStrike::create(['user_id' => $this->seller1->id, 'kinds' => 'phone']);
        TrustedSeller::flush();
        $this->assertFalse(TrustedSeller::is($this->seller1));
    }
}
