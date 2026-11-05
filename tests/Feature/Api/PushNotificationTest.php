<?php

namespace Tests\Feature\Api;

use App\Models\PushDevice;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Every in-app notification is pushed to the user's phones (Firebase); riders with push stay Online in the background. */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();

        // A throwaway service account (a real RSA key, a fake project).
        // (On Windows PHP needs its openssl.cnf to make a key.)
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        $opts = is_file($cnf) ? ['config' => $cnf] : [];
        $key = openssl_pkey_new($opts + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem, null, $opts);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->keyFile, json_encode(['project_id' => 'alvy-test', 'private_key' => $pem,
            'client_email' => 'push@alvy-test.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['services.fcm.credentials' => $this->keyFile]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        parent::tearDown();
    }

    private function register(string $token = 'phone-token-1'): void
    {
        $api = $this->buyer1->createToken('app')->plainTextToken;
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => $token])->assertOk();
    }

    public function test_the_app_registers_its_phone(): void
    {
        $this->register();
        $this->assertSame($this->buyer1->id, PushDevice::where('token', 'phone-token-1')->sole()->user_id);
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/v1/devices', ['token' => 'x'])->assertUnauthorized();
    }

    public function test_a_notification_is_pushed_to_the_phone(): void
    {
        $this->register();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*'    => Http::response(['name' => 'projects/alvy-test/messages/1']),
        ]);

        UserNotification::create(['user_id' => $this->buyer1->id, 'title' => 'Order shipped', 'body' => 'Order #000123 is on its way.', 'type' => 'info', 'link' => '/profile/orders/123']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/alvy-test/messages:send')
            && $r['message']['token'] === 'phone-token-1' && $r['message']['notification']['title'] === 'Order shipped'
            && $r['message']['data']['link'] === '/profile/orders/123' && $r->hasHeader('Authorization', 'Bearer ya29.test'));
    }

    public function test_a_phone_firebase_no_longer_knows_is_removed(): void
    {
        $this->register();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*'    => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);
        UserNotification::create(['user_id' => $this->buyer1->id, 'title' => 'Hi', 'body' => 'x', 'type' => 'info']);
        $this->assertSame(0, PushDevice::count());
    }

    public function test_nothing_is_sent_without_firebase_or_without_a_phone(): void
    {
        Http::fake();
        UserNotification::create(['user_id' => $this->buyer1->id, 'title' => 'No phone', 'body' => 'x', 'type' => 'info']);
        config(['services.fcm.credentials' => '/nope.json']);
        $this->register();
        UserNotification::create(['user_id' => $this->buyer1->id, 'title' => 'No key', 'body' => 'x', 'type' => 'info']);
        Http::assertNothingSent();
    }

    public function test_logging_out_of_the_app_stops_the_pushes(): void
    {
        $api = $this->buyer1->createToken('app')->plainTextToken;
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'phone-token-1'])->assertOk();
        $this->withToken($api)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, PushDevice::count());
    }

    public function test_a_rider_with_push_stays_online_in_the_background(): void
    {
        $this->rider1->update(['is_online' => true, 'last_seen_at' => now()->subHours(2)]);
        $this->assertFalse($this->rider1->fresh()->isOnlineNow());   // no push: offline after 30 minutes unseen

        PushDevice::create(['user_id' => $this->rider1->user_id, 'token' => 'rider-phone']);
        $this->assertTrue($this->rider1->fresh()->isOnlineNow());     // offers reach the phone anyway

        $this->rider1->update(['last_seen_at' => now()->subHours(13)]);
        $this->assertFalse($this->rider1->fresh()->isOnlineNow());   // 12 hours unseen
    }
}
