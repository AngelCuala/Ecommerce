<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Support\AlvyGcash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** The app pays GCash to ALVY like the website: QR + exact amount at checkout, the order's GCash state, and the fix. */
class GcashAppApiTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('gcash-qr/alvy.png', 'png');
        AlvyGcash::save(['qr_path' => 'gcash-qr/alvy.png', 'name' => 'ALVY TRADING', 'number' => null]);
        $this->token = $this->buyer1->createToken('test')->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/v1' . $uri, $data);
    }

    private function place(): Order
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $co = $this->api('GET', '/checkout?' . http_build_query(['items' => [$line->id]]))->assertOk();
        $account = $co->json('gcash_account');
        $this->assertSame(['ALVY TRADING', asset('storage/gcash-qr/alvy.png')], [$account['name'], $account['qr_url']]);
        $this->assertTrue($account['amount'] < $account['orders_total'] && $account['orders_total'] - $account['amount'] < 1);

        $this->withToken($this->token)->post('/api/v1/checkout', [
            'address_id' => $this->addr1->id, 'payment_method' => 'gcash_receipt', 'checkout_token' => $co->json('checkout_token'),
            'prices' => $co->json('prices'), 'payment_reference' => '1012345678901',
            // The vouchers checkout picked (the app sends them back, as the website does).
            'platform_voucher' => $co->json('chosen.platform'), 'shipping_voucher' => $co->json('chosen.shipping'),
            'seller_vouchers' => $co->json('chosen.sellers') ?: [],
            'payment_proof' => UploadedFile::fake()->image('r.png', 400, 800),
        ], ['Accept' => 'application/json'])->assertOk();

        $order = Order::sole();
        $this->assertEqualsWithDelta($account['amount'], (float) $order->payment_expected, 0.001);   // what the app showed is what is stored
        return $order;
    }

    public function test_the_app_pays_alvy_and_sees_the_payment_being_checked(): void
    {
        $order = $this->place();
        $g = $this->api('GET', '/orders/' . $order->id)->assertOk()->json('order.gcash');
        $this->assertSame(['checking', '1012345678901', false], [$g['state'], $g['reference'], $g['can_resubmit']]);
    }

    public function test_a_rejected_payment_shows_why_and_how_and_the_app_sends_the_right_receipt(): void
    {
        $order = $this->place();
        app(\App\Services\GcashChecks::class)->askToFix(Order::where('id', $order->id)->get(), ['not_receipt'], 'reader');

        $g = $this->api('GET', '/orders/' . $order->id)->assertOk()->json('order.gcash');
        $this->assertSame(['rejected', true, 1, 3], [$g['state'], $g['can_resubmit'], $g['try'], $g['max_tries']]);
        $this->assertSame('The picture you uploaded isn\'t a GCash receipt.', $g['problems'][0]['reason']);
        $this->assertNotNull($g['account']['qr_url']);

        $this->withToken($this->token)->post('/api/v1/orders/' . $order->id . '/gcash-receipt', ['payment_reference' => '12ab'], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->withToken($this->token)->post('/api/v1/orders/' . $order->id . '/gcash-receipt', [
            'payment_reference' => '2023456789012', 'payment_proof' => UploadedFile::fake()->image('r2.png', 400, 800),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['For verification', '2023456789012'], [$order->fresh()->payment_status, $order->fresh()->payment_reference]);
    }

    public function test_the_product_page_has_the_video_and_the_seller_sees_violations(): void
    {
        $this->p02->update(['video_path' => 'products/videos/clip.mp4']);
        $this->assertSame(asset('storage/products/videos/clip.mp4'), $this->api('GET', '/products/' . $this->p02->slug)->assertOk()->json('product.video_url'));

        \App\Models\SellerViolation::create(['seller_id' => $this->seller1->id, 'admin_id' => $this->seller2->id, 'type' => 'other', 'action' => 'warning', 'note' => 'Fix your photos.']);
        $seller = $this->seller1->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();   // the buyer's token was used above
        $v = $this->withToken($seller)->getJson('/api/v1/seller/dashboard')->assertOk()->json('violations');
        $this->assertSame('Fix your photos.', $v[0]['note']);
        $this->withToken($seller)->postJson('/api/v1/seller/violations/' . $v[0]['id'] . '/acknowledge')->assertOk();
        $this->assertSame([], $this->withToken($seller)->getJson('/api/v1/seller/dashboard')->json('violations'));
    }
}
