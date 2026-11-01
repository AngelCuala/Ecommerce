<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Support\ServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Terms of Service page has its default text, and the sign-up pages link to it. */
class TermsOfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_shows_the_default_terms_with_the_real_rules(): void
    {
        $this->get(route('policies.show', 'terms_of_service'))->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('1. About ALVY and these Terms')->assertSee('17. Contact us')
            ->assertSee(Order::RETURN_DAYS . ' days')
            ->assertSee(ServiceArea::label())
            ->assertSee('proof-of-delivery photo')
            ->assertSee(route('policies.show', 'privacy_policy'), false);
    }

    public function test_sign_up_opens_the_terms_and_privacy_policy_in_a_popup(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('data-policy-popup="terms_of_service"', false)
            ->assertSee('data-policy-popup="privacy_policy"', false)
            ->assertSee('data-policy-dialog="terms_of_service"', false)
            ->assertSee('data-policy-dialog="privacy_policy"', false)
            ->assertSee('1. About ALVY and these Terms')          // the terms text is in the pop-up
            ->assertSee('data-policy-agree', false)
            ->assertDontSee(route('policies.show', 'terms') . '"', false)
            ->assertDontSee(route('policies.show', 'privacy') . '"', false);
    }

    public function test_the_popup_shows_the_admins_published_terms_when_there_are_some(): void
    {
        $admin = \App\Models\User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => bcrypt('Secret@123'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        \App\Models\PlatformPolicy::upsertPolicy('terms_of_service', 'Terms of Service', '<p>Our own published terms.</p>', $admin->id);

        $this->get(route('register'))->assertOk()
            ->assertSee('Our own published terms.', false)
            ->assertDontSee('1. About ALVY and these Terms');
    }
}
