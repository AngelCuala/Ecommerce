<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Settings → Policies: policies that already have built-in text count as published and open with that text. */
class PolicyStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    public function test_built_in_policies_show_as_published_and_the_rest_as_draft(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.settings.index'))->assertOk();

        foreach (PlatformPolicy::defaultPolicies() as $p) {
            $expected = PlatformPolicy::defaultContent($p['key']) ? 'Published' : 'Draft';
            $page->assertSee('data-policy-status="' . $p['key'] . '">' . $expected, false);
        }
        $page->assertSee('data-policy-status="privacy_policy">Published', false)
            ->assertSee('data-policy-status="terms_of_service">Published', false)   // built-in Terms of Service
            ->assertSee('data-policy-status="return_refund_policy">Draft', false);
    }

    public function test_editing_a_built_in_policy_starts_from_its_text_and_saving_publishes_it(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get(route('admin.settings.policies.show', 'privacy_policy'))->assertOk()
            ->assertSee('data-builtin-note', false)->getContent();
        // The editor holds the built-in text, not an empty box.
        preg_match('/<textarea id="policy-editor"[^>]*>(.*?)<\/textarea>/s', $html, $m);
        $this->assertGreaterThan(1000, strlen(trim($m[1] ?? '')));

        $this->actingAs($admin)->put(route('admin.settings.policies.update', 'privacy_policy'), ['title' => 'Privacy Policy', 'content' => '<p>Our own text.</p>'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertSee('data-policy-status="privacy_policy">Published', false);
        $this->actingAs($admin)->get(route('admin.settings.policies.show', 'privacy_policy'))->assertDontSee('data-builtin-note', false)->assertSee('Our own text.');

        // A policy without built-in text still opens empty.
        $this->actingAs($admin)->get(route('admin.settings.policies.show', 'return_refund_policy'))->assertOk()->assertDontSee('data-builtin-note', false);
    }
}
