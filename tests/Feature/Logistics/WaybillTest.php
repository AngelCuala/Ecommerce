<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shopee-style waybills for sorting centers: unique numbers, printable labels, scanning. */
class WaybillTest extends TestCase
{
    use RefreshDatabase;

    private function sc(string $email): User
    {
        return User::forceCreate([
            'name' => 'SC ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => '043412000',
            'assigned_province' => 'Laguna', 'assigned_province_code' => '043400000',
        ]);
    }

    private function parcel(User $sc, array $over = []): Parcel
    {
        $seller = User::forceCreate(['name' => 'Lucy', 'email' => uniqid('s') . '@e.com', 'password' => bcrypt('x'),
            'role' => 'seller', 'approval_status' => 'approved']);

        return Parcel::create($over + [
            'tracking_number' => Parcel::generateTracking(), 'seller_id' => $seller->id,
            'pickup_address' => '91A Tapat St, San Antonio, Puerto Galera', 'dropoff_address' => '12 Rizal St, Real, Calamba, Laguna',
            'destination_municipality' => 'City of Calamba', 'destination_province' => 'Laguna',
            'receiver_name' => 'Juan Dela Cruz', 'receiver_phone' => '09171234567', 'weight_kg' => 1.2,
            'status' => 'pickup_approved', 'current_sorting_center_id' => $sc->id,
        ]);
    }

    public function test_every_parcel_gets_a_unique_valid_waybill_number(): void
    {
        $sc = $this->sc('a@e.com');
        $numbers = collect(range(1, 25))->map(fn () => $this->parcel($sc)->waybill_number);

        $this->assertCount(25, $numbers->unique());
        $numbers->each(function ($n) {
            $this->assertMatchesRegularExpression('/^PH\d{15}$/', $n);
            $this->assertTrue(Parcel::isValidWaybill($n));
        });
        $this->assertStringStartsWith('PH' . now()->format('ymd'), $numbers->first());

        // A single mistyped digit fails the check digit.
        $n = $numbers->first();
        $typo = substr($n, 0, 8) . (((int) $n[8] + 1) % 10) . substr($n, 9);
        $this->assertFalse(Parcel::isValidWaybill($typo));
    }

    public function test_sorting_center_prints_its_own_waybills_only(): void
    {
        $mine = $this->sc('mine@e.com');
        $other = $this->sc('other@e.com');
        $p1 = $this->parcel($mine);
        $p2 = $this->parcel($mine);
        $theirs = $this->parcel($other);

        $this->actingAs($mine)->get(route('sc.parcels.waybill', $p1))->assertOk()
            ->assertSee($p1->waybill_number)->assertSee('LAG-CAL')->assertSee('Juan Dela Cruz')
            ->assertSee('0917***4567')->assertDontSee('09171234567')      // phone is partly hidden
            ->assertSee('<svg', false)                                    // the barcode
            ->assertSee('data-waybill-qr', false)->assertSee('aria-label="QR code"', false)->assertSee('SCAN ME');   // and the QR code
        $this->actingAs($mine)->get(route('sc.parcels.waybill', $theirs))->assertForbidden();

        $this->actingAs($mine)->get(route('sc.waybills', ['ids' => [$p1->id, $p2->id, $theirs->id]]))->assertOk()
            ->assertSee($p1->waybill_number)->assertSee($p2->waybill_number)->assertDontSee($theirs->waybill_number);
    }

    public function test_scanning_the_waybill_receives_the_parcel(): void
    {
        $sc = $this->sc('scan@e.com');
        $p = $this->parcel($sc);

        $this->actingAs($sc)->post(route('sc.parcels.scan'), ['tracking_number' => strtolower($p->waybill_number)])->assertRedirect(route('sc.tracking.show', $p));
        $this->assertNull($p->fresh()->received_at);   // found, not yet received
        $this->actingAs($sc)->post(route('sc.tracking.receive', $p), ['confirm_received' => '1'])->assertSessionHas('success');
        $this->assertNotNull($p->fresh()->received_at);
        $this->assertSame('picked_up', $p->fresh()->status);
    }
}
