<?php

namespace Tests\Feature\Seller;

use App\Mail\PasswordChangeCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Seller > Account > Change password: the new password must meet the rules and is confirmed with an emailed code. */
class SellerPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Tindahan@2026';
    private const NEW = 'Tindahan@2027';

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seller = User::forceCreate(['name' => 'Maria Santos', 'first_name' => 'Maria', 'email' => 'maria@gmail.com', 'password' => Hash::make(self::OLD),
            'role' => 'seller', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function as() { return $this->actingAs($this->seller)->from(route('seller.account.security')); }

    private function request(string $new = self::NEW, string $current = self::OLD)
    {
        return $this->as()->put(route('seller.account.password'), ['current_password' => $current, 'password' => $new, 'password_confirmation' => $new]);
    }

    public function test_the_page_lists_the_password_rules(): void
    {
        $this->actingAs($this->seller)->get(route('seller.account.security'))->assertOk()
            ->assertSee('Step 1 of 2')->assertSee('Your new password needs:')
            ->assertSee('An uppercase letter')->assertSee('A symbol')->assertSee('ma•••@gmail.com');
    }

    public function test_weak_passwords_are_refused_and_no_email_is_sent(): void
    {
        // too short, no uppercase, no lowercase, no number, no symbol
        foreach (['Ab1!xyz', 'tindahan@2027', 'TINDAHAN@2027', 'Tindahan@shop', 'Tindahan2027'] as $weak) {
            $this->request($weak)->assertSessionHasErrors('password');
        }
        $this->request(self::OLD)->assertSessionHasErrors(['password' => 'The new password must be different from your current one.']);
        $this->request(self::NEW, 'Wrong@2026')->assertSessionHasErrors(['current_password' => 'Current password is incorrect.']);

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check(self::OLD, $this->seller->fresh()->password));
    }

    public function test_the_password_changes_only_after_the_emailed_code(): void
    {
        $this->request()->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'We sent a 6-digit code'));
        $this->assertTrue(Hash::check(self::OLD, $this->seller->fresh()->password), 'nothing changes before the code');

        $code = null;
        Mail::assertSent(PasswordChangeCodeMail::class, function ($m) use (&$code) { $code = $m->code; return $m->hasTo('maria@gmail.com'); });

        $this->actingAs($this->seller)->get(route('seller.account.security'))->assertOk()
            ->assertSee('Check your email')->assertSee(route('seller.account.password.confirm'), false);

        $this->as()->post(route('seller.account.password.confirm'), ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors(['code' => 'That code is not correct.']);
        $this->assertTrue(Hash::check(self::OLD, $this->seller->fresh()->password));

        $this->as()->post(route('seller.account.password.confirm'), ['code' => $code])->assertSessionHas('success');
        $this->assertTrue(Hash::check(self::NEW, $this->seller->fresh()->password));
    }

    public function test_cancel_keeps_the_old_password(): void
    {
        $this->request();
        $this->as()->post(route('seller.account.password.cancel'))->assertSessionHas('success');
        $this->assertTrue(Hash::check(self::OLD, $this->seller->fresh()->password));
        $this->actingAs($this->seller)->get(route('seller.account.security'))->assertSee('Step 1 of 2');
    }
}
