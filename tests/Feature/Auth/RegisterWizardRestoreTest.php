<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A sign-up the server refuses comes back to the same account type with every error, so the wizard
 * (register-wizard.js) opens the step with the problem instead of "What kind of account do you want?".
 */
class RegisterWizardRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refused_sign_up_keeps_the_account_type_and_hands_the_errors_to_the_wizard(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'role' => 'buyer', 'first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan.test@example.com',
            'password' => 'Secret@2026', 'password_confirmation' => 'Secret@2026', 'contact_no' => '09171234567',
            'sex' => 'male', 'birthday' => now()->subYears(10)->toDateString(),   // too young: no error line on the page for it
            'terms' => '1',
        ])->assertRedirect(route('register'));

        $page = $this->get(route('register'))->assertOk();
        $page->assertSee('id="roleInput" value="buyer"', false);                     // the account type is kept
        $page->assertSee('window.RegisterServerErrors = {', false);
        foreach (['birthday', 'municipality', 'barangay', 'street', 'house_number'] as $field) {
            $page->assertSee('"' . $field . '":[', false);                            // errors the wizard places under their fields
        }
        $page->assertSee('js/register-wizard.js?v=2', false);
    }

    public function test_a_fresh_page_has_no_account_type_and_no_errors(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('id="roleInput" value=""', false)
            ->assertSee('window.RegisterServerErrors = []', false);
    }

    public function test_the_log_in_and_sign_up_tabs_are_links(): void
    {
        // Sign Up goes where "Create account" goes.
        $login = $this->get(route('login'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(route('register'), '#') . '" class="auth-tab\s*"[^>]*data-auth-tab="signup"#', $login);
        $this->assertStringContainsString('href="' . route('register') . '" class="flex', $login);   // "Create account": same page

        $register = $this->get(route('register'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(route('login'), '#') . '" class="auth-tab\s*"[^>]*data-auth-tab="login"#', $register);
        $this->assertMatchesRegularExpression('#class="auth-tab is-active"\s+aria-current="page"\s+data-auth-tab="signup"#', $register);
    }

    public function test_the_role_in_the_link_is_kept_but_unknown_roles_are_not(): void
    {
        $this->get(route('register', ['role' => 'seller']))->assertSee('id="roleInput" value="seller"', false);
        $this->get(route('register', ['role' => 'admin']))->assertSee('id="roleInput" value=""', false);
    }
}
