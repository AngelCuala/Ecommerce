<?php

namespace Tests\Feature\Logistics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Sorting center › Account Management and Security: profile, and changing the password with the same rules and emailed code as everyone. */
class ScAccountPageTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function security()
    {
        return $this->actingAs($this->logistics1)->from(route('sc.account.security'));
    }

    private function request(string $new = 'NewHub@2027', string $current = 'Hatid@2026')
    {
        return $this->security()->put(route('sc.account.password'), ['current_password' => $current, 'password' => $new, 'password_confirmation' => $new]);
    }

    public function test_account_page_links_to_security_and_has_no_password_fields(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.account'))->assertOk()
            ->assertSee('data-account-head', false)->assertSee('Sorting center')->assertDontSee('Sorting_center')
            ->assertSee('data-account-profile', false)->assertSee(route('sc.account.security'), false)
            ->assertDontSee('name="current_password"', false)->assertSee(route('sc.logout'), false);
    }

    public function test_profile_card_saves_name_and_email_and_ignores_any_password(): void
    {
        $this->actingAs($this->logistics1)->from(route('sc.account'))->put(route('sc.account.update'), [
            'name' => 'Bilis Hatid Hub', 'email' => 'hub@bilishatid.example.com',
            'current_password' => 'Hatid@2026', 'password' => 'weak', 'password_confirmation' => 'weak',   // not a way around the Security page
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $u = $this->logistics1->fresh();
        $this->assertSame(['Bilis Hatid Hub', 'hub@bilishatid.example.com'], [$u->name, $u->email]);
        $this->assertTrue(Hash::check('Hatid@2026', $u->password));
    }

    public function test_security_page_shows_the_same_rules_as_everyone(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.account.security'))->assertOk()
            ->assertSee('Step 1 of 2')->assertSee('Your new password needs:')
            ->assertSee('An uppercase letter')->assertSee('A symbol')->assertSee('Different from your current password');
    }

    public function test_weak_or_wrong_passwords_are_refused_and_no_email_is_sent(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        foreach (['Ab1!xyz', 'newhub@2027', 'NEWHUB@2027', 'NewHub@hub', 'NewHub2027'] as $weak) {   // short, no upper, no lower, no number, no symbol
            $this->request($weak)->assertSessionHasErrors('password');
        }
        $this->request('Hatid@2026')->assertSessionHasErrors(['password' => 'The new password must be different from your current one.']);
        $this->request('NewHub@2027', 'Wrong@2026')->assertSessionHasErrors(['current_password' => 'Current password is incorrect.']);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $this->assertTrue(Hash::check('Hatid@2026', $this->logistics1->fresh()->password));
    }

    public function test_the_password_changes_only_after_the_emailed_code(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->request()->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'We sent a 6-digit code'));
        $this->assertTrue(Hash::check('Hatid@2026', $this->logistics1->fresh()->password), 'nothing changes before the code');

        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordChangeCodeMail::class, function ($m) use (&$code) { $code = $m->code; return $m->hasTo($this->logistics1->email); });
        $this->actingAs($this->logistics1)->get(route('sc.account.security'))->assertSee('Check your email')->assertSee(route('sc.account.password.confirm'), false);

        $this->security()->post(route('sc.account.password.confirm'), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors(['code' => 'That code is not correct.']);
        $this->security()->post(route('sc.account.password.confirm'), ['code' => $code])->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewHub@2027', $this->logistics1->fresh()->password));
    }

    public function test_cancel_keeps_the_old_password(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->request();
        $this->security()->post(route('sc.account.password.cancel'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('Hatid@2026', $this->logistics1->fresh()->password));
        $this->actingAs($this->logistics1)->get(route('sc.account.security'))->assertSee('Step 1 of 2');
    }
}
