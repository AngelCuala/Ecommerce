<?php

namespace Tests\Feature\Rider;

use App\Models\ParcelDelivery;
use App\Models\RiderRemittance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** COD remittance receipt: shown after sending, matches the saved record, pending until the hub confirms. */
class RemittanceReceiptTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        config(['app.business_timezone' => 'Asia/Manila']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deliveredCod(float $cod, $at = null): ParcelDelivery
    {
        $parcel = $this->parcel('delivered');
        return ParcelDelivery::create(['parcel_id' => $parcel->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered',
            'delivered_at' => $at ?? now(), 'cod_amount' => $cod, 'cod_collected' => $cod]);
    }

    private function remit(string $amount, ?string $token = null)
    {
        return $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => $amount, 'request_token' => $token ?? (string) Str::uuid()]);
    }

    public function test_sending_a_remittance_shows_its_receipt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:48', 'Asia/Manila'));
        $a = $this->deliveredCod(500);
        $b = $this->deliveredCod(1300);

        $res = $this->remit('1800.00');
        $remit = RiderRemittance::sole();
        $res->assertRedirect(route('courier.rider.cod.receipt', $remit));

        $number = 'RR-261026-' . str_pad((string) $remit->id, 6, '0', STR_PAD_LEFT);
        $this->assertSame($number, $remit->receiptNumber());

        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod.receipt', $remit))->assertOk()
            ->assertSee('COD Remittance Receipt')->assertSee($number)->assertSee('images/logo-mark.png', false)
            ->assertSee($this->rider1->full_name)
            ->assertSee('Oct 26, 2026 · 3:48 PM')                                         // Philippine time
            ->assertSee('data-receipt-amount>₱1,800.00', false)                            // = the saved amount
            ->assertSee('data-receipt-line="' . $a->parcel->tracking_number . '"', false)
            ->assertSee('data-receipt-line="' . $b->parcel->tracking_number . '"', false)
            ->assertSee($a->parcel->order->number())
            ->assertSee('data-status="pending"', false)->assertSee('PENDING CONFIRMATION')
            ->assertSee('not proof of payment', false)
            ->assertSee('data-print', false)->assertSee('window.print()', false);
        $this->assertEquals(1800, (float) $remit->amount);
    }

    public function test_sending_the_same_form_twice_makes_one_remittance_and_one_receipt(): void
    {
        $this->deliveredCod(250);
        $token = (string) Str::uuid();
        $this->remit('250.00', $token);
        $remit = RiderRemittance::sole();

        $this->remit('250.00', $token)->assertRedirect(route('courier.rider.cod.receipt', $remit))
            ->assertSessionHas('success', 'Your remittance was already sent.');
        $this->assertSame(1, RiderRemittance::count());

        // A new form while one is still pending is refused.
        $this->deliveredCod(100);
        $this->remit('100.00')->assertSessionHasErrors('amount');
        $this->assertSame(1, RiderRemittance::count());
    }

    public function test_confirmation_changes_the_receipt_and_history_keeps_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:48', 'Asia/Manila'));
        $this->deliveredCod(955);
        $this->remit('955.00');
        $remit = RiderRemittance::sole();

        Carbon::setTestNow(Carbon::parse('2026-10-26 15:52', 'Asia/Manila'));
        $this->actingAs($this->logistics1)->post(route('sc.remittances.confirm', $remit))->assertSessionHas('success', 'Remittance confirmed.');

        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod.receipt', $remit))->assertOk()
            ->assertSee('data-status="confirmed"', false)->assertSee('CONFIRMED')->assertDontSee('PENDING CONFIRMATION')
            ->assertSee('Oct 26, 2026 · 3:52 PM');

        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertOk()
            ->assertSee(route('courier.rider.cod.receipt', $remit), false)->assertSee($remit->receiptNumber());
    }

    public function test_the_receipt_shows_the_pay_the_rider_kept(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 09:00', 'Asia/Manila'));
        $d = $this->deliveredCod(500, now()->subHour());
        \App\Models\ParcelDelivery::whereKey($d->id)->update(['status' => 'delivered']);
        $this->remit('500.00');
        $r = RiderRemittance::sole();

        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod.receipt', $r))->assertOk()
            ->assertSee('data-receipt-pay-kept>− ₱' . number_format($r->pay_kept, 2), false)
            ->assertSee('data-receipt-amount>₱' . number_format(500 - $r->pay_kept, 2), false);
        $this->assertGreaterThan(0, (float) $r->pay_kept);
    }

    public function test_only_the_rider_who_sent_it_can_open_the_receipt(): void
    {
        $this->deliveredCod(200);
        $this->remit('200.00');
        $remit = RiderRemittance::sole();

        $this->actingAs($this->rider2->user)->get(route('courier.rider.cod.receipt', $remit))->assertNotFound();
        $this->actingAs($this->buyer1)->get(route('courier.rider.cod.receipt', $remit))->assertStatus(403);
        auth()->logout();
        $this->get(route('courier.rider.cod.receipt', $remit))->assertRedirect();
    }
}
