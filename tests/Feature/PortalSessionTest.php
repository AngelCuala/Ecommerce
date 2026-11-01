<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Portal;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/**
 * One browser, several tabs, different accounts: each portal (shop, Seller Centre, sorting
 * center, courier, admin) keeps its own login cookie. Requests here go through real database
 * sessions and a cookie jar, like a browser.
 */
class PortalSessionTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    /** @var array<string,string> name => plain value (one browser's cookies) */
    private array $jar = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.per_portal' => true, 'session.driver' => 'database']);
        $this->seedBuyerTestData();
    }

    /** Send a request as the browser: its cookies go out, the response's cookies are kept. */
    private function browse(string $method, string $uri, array $data = [], ?array &$jar = null): TestResponse
    {
        $jar ??= $this->jar;
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');   // it holds the previous request's session
        $this->app['auth']->forgetGuards();
        $this->app['cookie']->flushQueuedCookies();
        Portal::forget();
        $this->defaultCookies = [];

        $this->withCookies($jar);
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                unset($jar[$cookie->getName()]);
            } else {
                $jar[$cookie->getName()] = CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
            }
        }
        if (func_num_args() < 4) {
            $this->jar = $jar;
        }
        return $response;
    }

    /** Follow redirects (hand-offs) like a browser. */
    private function follow(TestResponse $response, int $max = 5): TestResponse
    {
        while ($max-- > 0 && $response->isRedirect()) {
            $response = $this->browse('GET', $response->headers->get('Location'));
        }
        return $response;
    }

    private function login(string $loginPath, string $email, string $password, bool $remember = false): TestResponse
    {
        return $this->browse('POST', $loginPath, ['email' => $email, 'password' => $password] + ($remember ? ['remember' => '1'] : []));
    }

    public function test_a_buyer_and_a_seller_signed_in_side_by_side_in_one_browser(): void
    {
        $this->login('/login', $this->buyer1->email, 'Mamimili@2026')->assertRedirect();
        $this->follow($this->login('/seller/login', $this->seller1->email, 'Tindahan@2026'))->assertOk()->assertSee('Maria Clara Handicrafts');

        // Shop tab: still the buyer. Seller tab: still the seller.
        $this->browse('GET', '/profile/orders')->assertOk()->assertSee('Juan Miguel');
        $this->browse('GET', '/seller')->assertOk()->assertSee('Maria Clara Handicrafts');
        $this->browse('GET', '/seller/messages')->assertOk();
        $this->assertArrayHasKey(Portal::cookieName('shop'), $this->jar);
        $this->assertArrayHasKey(Portal::cookieName('seller'), $this->jar);

        // Signing out of the Seller Centre leaves the shop signed in.
        $this->follow($this->browse('POST', '/seller/logout'))->assertSee('You have been signed out.');
        $this->browse('GET', '/seller')->assertRedirect('/seller/login');
        $this->browse('GET', '/profile/orders')->assertOk();
    }

    public function test_a_seller_using_the_main_login_is_handed_to_the_seller_centre_only(): void
    {
        $first = $this->login('/login', $this->seller1->email, 'Tindahan@2026');
        $this->assertMatchesRegularExpression('#/seller/session/handoff/[A-Za-z0-9]{48}$#', $first->headers->get('Location'));
        $this->follow($first)->assertOk()->assertSee('Maria Clara Handicrafts')->assertSee('Welcome back');   // the message moved with the login

        $this->browse('GET', '/profile/orders')->assertRedirect('/login');   // not signed in to the shop
        $this->browse('GET', '/seller')->assertOk();
    }

    public function test_handoff_links_work_once_and_only_in_the_same_browser(): void
    {
        $link = $this->login('/login', $this->seller1->email, 'Tindahan@2026')->headers->get('Location');

        $other = [];   // another browser (no cookies): refused
        $this->browse('GET', $link, [], $other)->assertRedirect('/seller/login')->assertSessionHas('error');

        // The legitimate browser: the link was used up by the attempt above too (single use).
        $this->browse('GET', $link)->assertRedirect('/seller/login');
        $this->browse('GET', '/seller')->assertRedirect('/seller/login');
    }

    public function test_remember_me_is_kept_per_portal(): void
    {
        $this->follow($this->login('/seller/login', $this->seller1->email, 'Tindahan@2026', remember: true))->assertOk();
        $remember = array_values(array_filter(array_keys($this->jar), fn ($n) => str_starts_with($n, 'remember_web_')));
        $this->assertCount(1, $remember);
        $this->assertStringEndsWith('_seller', $remember[0]);

        // The seller's session is gone (e.g. expired), but "remember me" signs them back in to the Seller Centre only.
        unset($this->jar[Portal::cookieName('seller')]);
        $this->browse('GET', '/seller')->assertOk();
        $this->browse('GET', '/profile/orders')->assertRedirect('/login');
    }

    public function test_each_portal_sends_signed_out_visitors_to_its_own_login(): void
    {
        $this->browse('GET', '/seller')->assertRedirect('/seller/login');
        $this->browse('GET', '/courier')->assertRedirect('/courier/login');
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
        $this->browse('GET', '/sc/dashboard')->assertRedirect('/sc/login');
        $this->browse('GET', '/profile/orders')->assertRedirect('/login');
        $this->browse('GET', '/seller/login')->assertOk()->assertSee('Log in to the ALVY Seller Centre')->assertSee('action="' . route('seller.login.attempt') . '"', false);
    }

    public function test_sorting_center_and_admin_next_to_a_buyer(): void
    {
        $sc = User::forceCreate(['name' => 'Hub', 'email' => 'hub@e.com', 'password' => Hash::make('Hub@2026'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@e.com', 'password' => Hash::make('Admin@2026'), 'role' => 'admin',
            'approval_status' => 'approved', 'email_verified_at' => now()]);

        $this->login('/login', $this->buyer1->email, 'Mamimili@2026');
        $this->follow($this->browse('POST', '/sc/login', ['email' => 'hub@e.com', 'password' => 'Hub@2026']))->assertOk();
        $this->follow($this->login('/admin/login', 'admin@e.com', 'Admin@2026'))->assertOk();

        $this->browse('GET', '/profile/orders')->assertOk();
        $this->browse('GET', '/sc/dashboard')->assertOk();
        $this->browse('GET', '/admin')->assertOk();
        $this->assertCount(3, array_filter(array_keys($this->jar), fn ($n) => str_starts_with($n, (string) config('session.cookie'))));
    }

    public function test_signing_out_of_a_sorting_center_shows_its_login_not_the_buyer_signed_in_next_to_it(): void
    {
        User::forceCreate(['name' => 'Hub', 'email' => 'hub@e.com', 'password' => Hash::make('Hub@2026'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->login('/login', $this->buyer1->email, 'Mamimili@2026');
        $this->follow($this->login('/login', 'hub@e.com', 'Hub@2026'))->assertOk();   // main login, handed to the sorting center portal
        $this->browse('GET', '/sc/dashboard')->assertOk();

        $out = $this->browse('POST', '/sc/logout');
        $out->assertRedirect('/sc/login');
        $this->follow($out)->assertOk()->assertSee('Log in to the ALVY Sorting Center')
            ->assertSee('You have been signed out.')->assertSee('action="' . route('sc.login.attempt') . '"', false);
        $this->browse('GET', '/sc/dashboard')->assertRedirect('/sc/login');

        // Signing in again on that page opens the sorting center, not the shop.
        $this->follow($this->login('/sc/login', 'hub@e.com', 'Hub@2026'))->assertOk();
        $this->browse('GET', '/sc/dashboard')->assertOk();
    }

    public function test_time_limits_apply_to_each_portal_separately(): void
    {
        config(['session.lifetime' => 30]);   // short idle limit so the test can show it per portal
        $this->login('/login', $this->buyer1->email, 'Mamimili@2026');
        $this->follow($this->login('/seller/login', $this->seller1->email, 'Tindahan@2026'));

        // The seller tab is used; the buyer tab sits idle.
        foreach ([20, 20] as $minutes) {
            $this->travel($minutes)->minutes();
            $this->browse('GET', '/seller')->assertOk();
        }
        $this->browse('GET', '/profile/orders')->assertRedirect('/login');   // 40 idle minutes in the shop
        $this->browse('GET', '/seller')->assertOk();
    }
}
