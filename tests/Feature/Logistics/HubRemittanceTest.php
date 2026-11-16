<?php

namespace Tests\Feature\Logistics;

use App\Models\HubRemittance;
use App\Models\ParcelDelivery;
use App\Models\RiderRemittance;
use App\Models\User;
use App\Services\HubRemittances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** PAYMENTS_SPEC §3.1 step 5, §3.2: the hub remits the item price to ALVY with a report; ALVY matches it and confirms or flags. */
class HubRemittanceTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.rider_share_since' => now()->subDay()->toDateTimeString()]);
        $this->seedRiderTestData();
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => bcrypt('x'), 'role' => 'admin',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    /** A delivered job of rider1; the order's shipping fee is ₱100. */
    private function delivered(float $cod = 0, ?float $collected = null): ParcelDelivery
    {
        $p = $this->parcel('delivered');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();

        return ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'attempt' => 1,
            'delivered_at' => now(), 'cod_amount' => $cod, 'cod_collected' => $collected ?? $cod]);
    }

    private function riderRemitsAndHubConfirms(): void
    {
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['request_token' => (string) Str::uuid()]);
        $r = RiderRemittance::latest('id')->first();
        $this->actingAs($this->logistics1)->post(route('sc.remittances.confirm', $r), ['counted' => (float) $r->amount]);
    }

    private function ready()
    {
        return app(HubRemittances::class)->ready($this->logistics1->id);
    }

    public function test_the_report_nets_cod_and_prepaid_orders(): void
    {
        $cod = $this->delivered(1100);
        $this->delivered();                                   // prepaid: ALVY owes the hub ₱100
        $this->assertSame([-100.0], $this->ready()->pluck('amount_due')->all());   // the COD waits for the rider's remittance

        $this->riderRemitsAndHubConfirms();
        $this->assertEqualsCanonicalizing([1000.0, -100.0], $this->ready()->pluck('amount_due')->all());
        $this->actingAs($this->logistics1)->get(route('sc.alvy-remittances'))->assertOk()->assertSee('Total to ALVY: ₱900.00')->assertSee($cod->parcel->tracking_number);

        $this->actingAs($this->logistics1)->post(route('sc.alvy-remittances.submit'))->assertSessionHas('error');   // a deposit reference is needed
        $this->actingAs($this->logistics1)->post(route('sc.alvy-remittances.submit'), ['deposit_reference' => 'BDO-778899'])->assertRedirect();
        $r = HubRemittance::sole();
        $this->assertSame([900.0, 2, 'submitted'], [(float) $r->amount, $r->items()->count(), $r->status]);
        $this->assertCount(0, $this->ready());               // each delivery once

        // ALVY: what arrived matches, every order matches.
        $this->actingAs($this->admin)->get(route('admin.hub-remittances.show', $r))->assertOk()->assertSee('BDO-778899')->assertSee($cod->parcel->tracking_number);
        $this->actingAs($this->admin)->from(route('admin.hub-remittances.show', $r))->post(route('admin.hub-remittances.review', $r), ['received' => 900])
            ->assertSessionHas('success');
        $this->assertSame(['confirmed', $this->admin->id], [$r->fresh()->status, (int) $r->fresh()->confirmed_by]);
    }

    public function test_a_short_deposit_or_a_wrong_cod_is_flagged(): void
    {
        $this->delivered(1100, 1050);                         // collected less than the order's COD
        $this->riderRemitsAndHubConfirms();
        $line = $this->ready()->sole();
        $this->assertTrue($line['mismatch']);
        $this->actingAs($this->logistics1)->post(route('sc.alvy-remittances.submit'), ['deposit_reference' => 'BDO-1']);
        $r = HubRemittance::sole();

        $this->actingAs($this->admin)->from(route('admin.hub-remittances.show', $r))->post(route('admin.hub-remittances.review', $r), ['received' => 900])
            ->assertSessionHas('error');
        $r->refresh();
        $this->assertSame('flagged', $r->status);
        $this->assertStringContainsString('₱50.00 short', $r->note);
        $this->assertStringContainsString("doesn't match the order", $r->note);
        $this->actingAs($this->admin)->from(route('admin.hub-remittances.show', $r))->post(route('admin.hub-remittances.review', $r), ['received' => 950])
            ->assertSessionHas('error');                      // checked once
    }
}
