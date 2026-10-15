<?php

namespace Tests\Feature\Buyer;

use App\Mail\ContactChangeMail;
use App\Models\Order;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyer workbook B1 (Account & Security) and B2 (Address Book). */
class B01AccountAndAddressTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedBuyerTestData();
    }

    // ── B1 Account & Security ─────────────────────────────────

    /** BUY-ACCT-001 */
    public function test_edit_profile_with_name_and_photo(): void
    {
        $this->actingAs($this->buyer1)->patch(route('profile.update'), [
            'name' => 'Juan Miguel Dela Cruz Jr.', 'avatar' => UploadedFile::fake()->image('review_photo.jpg')->size(1500),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Juan Miguel Dela Cruz Jr.', $this->buyer1->fresh()->name);
        Storage::disk('public')->assertExists($this->buyer1->fresh()->profile_photo_path);
        $this->actingAs($this->buyer1)->get(route('profile.show'))->assertSee('Juan Miguel Dela Cruz Jr.');

        // A16: JPG/PNG only, up to 5 MB.
        $this->actingAs($this->buyer1)->patch(route('profile.update'), ['name' => 'X', 'avatar' => UploadedFile::fake()->image('big.jpg')->size(5121)])
            ->assertSessionHasErrors('avatar');
        $this->actingAs($this->buyer1)->patch(route('profile.update'), ['name' => 'X', 'avatar' => UploadedFile::fake()->create('price_list.docx', 320)])
            ->assertSessionHasErrors('avatar');
    }

    /** BUY-ACCT-002, 003, 004 */
    public function test_change_password_rules(): void
    {
        $change = fn ($old, $new, $confirm) => $this->actingAs($this->buyer1)->from(route('profile.security'))
            ->patch(route('profile.change-password'), ['current_password' => $old, 'password' => $new, 'password_confirmation' => $confirm]);

        // 003: wrong current password.
        $change('Wrong@2026', 'Mamimili@2027', 'Mamimili@2027')->assertSessionHasErrors(['current_password' => 'Current password is incorrect.']);
        // 004: each rule has its own message.
        $change('Mamimili@2026', 'short1!', 'short1!')->assertSessionHasErrors('password');
        $this->assertStringContainsString('8 characters', session('errors')->first('password'));
        $change('Mamimili@2026', 'mamimili@2027', 'mamimili@2027')->assertSessionHasErrors('password');
        $this->assertStringContainsString('uppercase', session('errors')->first('password'));
        $change('Mamimili@2026', 'Mamimili@2027', 'Mamimili@2028')->assertSessionHasErrors(['password' => 'The new password and its confirmation do not match.']);
        $this->assertTrue(Hash::check('Mamimili@2026', $this->buyer1->fresh()->password));

        // 002: the new password works, the old one doesn't.
        $change('Mamimili@2026', 'Mamimili@2027', 'Mamimili@2027')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Mamimili@2027', $this->buyer1->fresh()->password));
        $this->assertFalse(Hash::check('Mamimili@2026', $this->buyer1->fresh()->password));
    }

    /** BUY-ACCT-005, 006 */
    public function test_change_email_only_after_the_code(): void
    {
        Mail::fake();

        // 006: an email someone else uses.
        $this->actingAs($this->buyer1)->post(route('profile.email.change'), ['email' => 'anamarie.reyes@example.com'])
            ->assertSessionHasErrors(['email' => 'That email address is already in use by another account.']);

        // 005: code goes to the new address; the old email stays until it is entered.
        $this->actingAs($this->buyer1)->post(route('profile.email.change'), ['email' => 'buyer.test01@example.com'])->assertSessionHas('success');
        $this->assertSame('juanmiguel.delacruz@example.com', $this->buyer1->fresh()->email);
        $code = null;
        Mail::assertSent(ContactChangeMail::class, function ($m) use (&$code) {
            $code = $m->code;
            return $m->hasTo('buyer.test01@example.com');
        });

        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors(['code' => 'That code is not correct.']);
        $this->assertSame('juanmiguel.delacruz@example.com', $this->buyer1->fresh()->email);

        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code])->assertSessionHas('success');
        $this->assertSame('buyer.test01@example.com', $this->buyer1->fresh()->email);
        $this->assertNull($this->buyer1->fresh()->pending_email);
    }

    /** BUY-ACCT-007 */
    public function test_change_phone_after_verification(): void
    {
        Mail::fake();

        $this->actingAs($this->buyer1)->post(route('profile.phone.change'), ['phone' => '0917123'])->assertSessionHasErrors('phone');

        $this->actingAs($this->buyer1)->post(route('profile.phone.change'), ['phone' => '09170000001'])->assertSessionHas('success');
        $this->assertSame('09171234567', $this->buyer1->fresh()->phone);   // unchanged until verified
        $code = null;
        Mail::assertSent(ContactChangeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code])->assertSessionHas('success');
        $this->assertSame('09170000001', $this->buyer1->fresh()->phone);
    }

    // ── B2 Address Book ───────────────────────────────────────

    private function addressForm(array $over = []): array
    {
        return $over + ['label' => 'Office', 'full_name' => 'Juan Miguel Dela Cruz', 'phone' => '09171234567',
            'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'City of Makati', 'barangay' => 'San Lorenzo',
            'address_line' => '12F Pacific Tower, 6750 Ayala Ave.', 'zip' => '1223',
            'municipality_code' => '137602000', 'barangay_code' => '137602025'];
    }

    /** BUY-ADDR-001, 002, 003 (002 is checked on the server here, the dropdowns in the browser) */
    public function test_add_address_and_validation(): void
    {
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm())->assertSessionHasNoErrors();
        $this->actingAs($this->buyer2)->get(route('profile.addresses'))->assertOk()->assertSee('City of Makati')->assertSee('address-modal', false);
        $this->assertDatabaseHas('user_addresses', ['user_id' => $this->buyer2->id, 'city' => 'City of Makati', 'municipality_code' => '137602000']);

        // 002: the city must come from the region/province/city lists (typed text alone is not enough).
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['municipality_code' => '']))
            ->assertSessionHasErrors('municipality_code');

        // 003: phone formats and required fields.
        foreach (['0917123', '091712345678'] as $bad) {
            $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['phone' => $bad]))->assertSessionHasErrors('phone');
        }
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['phone' => '+639171234567']))->assertSessionHasNoErrors();
        foreach (['full_name', 'phone', 'region', 'province', 'city', 'barangay', 'address_line'] as $field) {
            $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm([$field => '']))->assertSessionHasErrors($field);
        }
    }

    /** BUY-ADDR-004 */
    public function test_set_default_and_checkout_preselects_it(): void
    {
        $this->actingAs($this->buyer1)->patch(route('addresses.set-default', $this->addr2))->assertSessionHas('success');
        $this->assertSame([$this->addr2->id], $this->buyer1->addresses()->where('is_default', true)->pluck('id')->all());

        $line = $this->cart($this->buyer1, $this->p02);
        $this->assertSame($this->addr2->id, $this->openCheckout($this->buyer1, [$line])->assertOk()->viewData('address')->id);
    }

    /** BUY-ADDR-005 (A30: the default can't be deleted until another address is the default) */
    public function test_default_address_is_deleted_only_after_another_is_default(): void
    {
        $this->assertTrue($this->addr1->is_default);
        $this->actingAs($this->buyer1)->delete(route('addresses.destroy', $this->addr1))
            ->assertSessionHas('error', 'This is your default address. Make another address your default first, then delete this one.');
        $this->assertNotNull($this->addr1->fresh());

        $this->actingAs($this->buyer1)->patch(route('addresses.set-default', $this->addr4));
        $this->actingAs($this->buyer1)->delete(route('addresses.destroy', $this->addr1))->assertSessionHas('success');
        $this->assertSame([$this->addr4->id], $this->buyer1->addresses()->where('is_default', true)->pluck('id')->all());
    }

    /** A30: up to 10 addresses per buyer. */
    public function test_up_to_ten_addresses(): void
    {
        foreach (range(1, 10 - $this->buyer1->addresses()->count()) as $i) {
            $this->addressFor($this->buyer1, self::QUEZON_CITY, ['label' => 'Extra ' . $i]);
        }
        $this->assertSame(10, $this->buyer1->addresses()->count());
        $this->actingAs($this->buyer1)->post(route('addresses.store'), $this->addressForm(['label' => 'Eleventh']))
            ->assertSessionHas('error', 'You can save up to 10 addresses. Delete one to add another.');
        $this->assertSame(10, $this->buyer1->addresses()->count());
    }

    /** BUY-ADDR-006, 007 */
    public function test_address_in_use_and_edits_do_not_touch_orders(): void
    {
        $order = Order::create(['user_id' => $this->buyer1->id, 'address_id' => $this->addr2->id, 'status' => 'Pending',
            'full_name' => 'Juan', 'phone' => '0917', 'email' => 'j@x.test', 'address_line' => $this->addr2->full_address,
            'city' => 'Quezon City', 'zip_code' => '1100', 'shipping_address' => $this->addr2->full_address,
            'total_price' => 500, 'payment_method' => 'COD', 'payment_status' => 'Pending']);

        // 006: blocked while the order is on its way; the order keeps its copy.
        $this->actingAs($this->buyer1)->delete(route('addresses.destroy', $this->addr2))->assertSessionHas('error');
        $this->assertNotNull(UserAddress::find($this->addr2->id));

        // 007: the new street is saved; the order keeps the old address.
        $this->actingAs($this->buyer1)->patch(route('addresses.update', $this->addr2), $this->addressForm(['address_line' => '14F Pacific Tower']))
            ->assertSessionHasNoErrors();
        $this->assertSame('14F Pacific Tower', $this->addr2->fresh()->address_line);
        $this->assertStringContainsString('12F Pacific Tower', $order->fresh()->shipping_address);
    }
}
