<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * A form submitted after the 30-minute session ran out (stale CSRF token) must not show
 * the bare "419 Page Expired": the user goes back with what they typed and a clear message.
 */
class PageExpiredTest extends TestCase
{
    use RefreshDatabase;

    /** Tests skip CSRF; put the real check back so a stale token fails like in the browser. */
    private function withCsrf(): void
    {
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests() { return false; }
        });
    }

    public function test_stale_login_form_goes_back_with_a_message_and_the_email(): void
    {
        $this->withCsrf();
        $this->get('/login');
        $this->post('/login', ['_token' => 'stale', 'email' => 'buyer@example.com', 'password' => 'secret123'], ['referer' => url('/login')])
            ->assertRedirect(url('/login'))
            ->assertSessionHas('error', 'Your session expired, so that was not saved. Please try again.')
            ->assertSessionHasInput('email', 'buyer@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->get('/login')->assertOk()->assertSee('Your session expired');
    }

    public function test_stale_logout_just_goes_to_login(): void
    {
        $this->withCsrf();
        $this->post('/logout', ['_token' => 'stale'])->assertRedirect(route('login'));
    }

    public function test_ajax_gets_a_json_419(): void
    {
        $this->withCsrf();
        $this->postJson('/login', ['_token' => 'stale'])->assertStatus(419)->assertJsonPath('message', 'Your session expired. Please reload the page and try again.');
    }

    public function test_session_cookie_is_not_tied_to_another_domain(): void
    {
        $this->assertContains(config('session.domain'), [null, ''], 'SESSION_DOMAIN must be empty so the cookie works on alvy.trade and localhost alike.');
    }
}
