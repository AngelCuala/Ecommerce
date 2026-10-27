<?php

namespace Tests\Feature\Logistics;

use App\Models\ActivityLog;
use App\Models\Parcel;
use App\Models\ParcelPickup;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Sorting center waybill scanner: find only, server-side checks, duplicates, history, access. */
class WaybillScannerTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1, $this->rider2);
    }

    private function scan(User $sc, string $code)
    {
        return $this->actingAs($sc)->from(route('sc.incoming-parcels'))->post(route('sc.parcels.scan'), ['tracking_number' => $code]);
    }

    /** Collected by Logistics 1's pickup rider, on its way to Logistics 1. */
    private function collected(): Parcel
    {
        $parcel = $this->parcel('pickup_approved');
        $pickup = $this->pickupFor($this->rider1, $parcel);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        return $parcel->fresh();
    }

    private function lastScan(User $sc): ActivityLog
    {
        return ActivityLog::where('action', 'sc_scan')->where('admin_id', $sc->id)->latest('id')->firstOrFail();
    }

    public function test_valid_scan_finds_the_parcel_changes_nothing_and_is_in_the_history(): void
    {
        $parcel = $this->collected();
        $events = ShipmentEvent::where('parcel_id', $parcel->id)->count();

        // Camera reads come in as typed text; lower case and spaces are fine.
        $this->scan($this->logistics1, '  ' . strtolower($parcel->waybill_number) . ' ')
            ->assertRedirect(route('sc.tracking.show', $parcel))
            ->assertSessionHas('info', fn ($m) => str_contains($m, 'Found ' . $parcel->tracking_number) && str_contains($m, $parcel->statusLabel()));

        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);                       // scanning records nothing
        $this->assertSame($events, ShipmentEvent::where('parcel_id', $parcel->id)->count());
        $log = $this->lastScan($this->logistics1);
        $this->assertSame(['found', $parcel->waybill_number, $parcel->statusLabel()], [$log->status, $log->old_value, $log->new_value]);

        $this->actingAs($this->logistics1)->get(route('sc.incoming-parcels'))->assertOk()
            ->assertSee('data-scan-history', false)->assertSee($parcel->waybill_number)->assertSee('data-scan-row="found"', false)
            ->assertSee('Scan with camera')->assertSee('js/vendor/zxing.min.js', false);
    }

    public function test_invalid_and_unknown_codes_are_refused_and_logged(): void
    {
        $this->scan($this->logistics1, 'hello world!')->assertRedirect(route('sc.incoming-parcels'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'is not a waybill or tracking number'));
        $this->assertSame('invalid', $this->lastScan($this->logistics1)->status);

        $this->scan($this->logistics1, 'PH000000000000000')->assertSessionHas('error', 'Parcel PH000000000000000 not found at your sorting center.');
        $this->assertSame('not_found', $this->lastScan($this->logistics1)->status);

        $this->scan($this->logistics1, '')->assertSessionHasErrors('tracking_number');
    }

    public function test_another_centers_parcel_is_not_shown(): void
    {
        $parcel = $this->collected();   // heading to Logistics 1, not Logistics 2
        $this->scan($this->logistics2, $parcel->waybill_number)
            ->assertSessionHas('error', "Parcel {$parcel->waybill_number} not found at your sorting center.");
        $this->actingAs($this->logistics2)->get(route('sc.tracking.show', $parcel))->assertStatus(403);
        $this->assertNull($this->lastScan($this->logistics2)->subject_id);   // nothing about it is linked
    }

    public function test_duplicate_scan_after_receipt_records_nothing_again(): void
    {
        $parcel = $this->collected();
        $this->scan($this->logistics1, $parcel->waybill_number)->assertRedirect(route('sc.tracking.show', $parcel));
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))
            ->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('success');
        $received = $parcel->fresh()->received_at;
        $events = ShipmentEvent::where('parcel_id', $parcel->id)->count();

        $this->scan($this->logistics1, $parcel->tracking_number)->assertSessionHas('error', fn ($m) => str_contains($m, 'already received'));
        $this->assertSame('duplicate', $this->lastScan($this->logistics1)->status);

        // Confirming receipt again is refused by the workflow too.
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))
            ->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('error');
        $this->assertEquals($received, $parcel->fresh()->received_at);
        $this->assertSame($events, ShipmentEvent::where('parcel_id', $parcel->id)->count());
    }

    public function test_status_changes_only_in_the_allowed_order(): void
    {
        // Pickup not approved yet: it can't be received or sorted at the hub. (An approved one may be received:
        // the seller can drop it off.)
        $waiting = $this->parcel('pending_pickup');
        $this->actingAs($this->logistics1)->from(route('sc.tracking'))
            ->post(route('sc.tracking.receive', $waiting), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('error');
        $this->assertSame('pending_pickup', $waiting->fresh()->status);
        $this->actingAs($this->logistics1)->from(route('sc.tracking'))->post(route('sc.parcels.sort', $waiting), ['area_id' => $this->central->id]);
        $this->assertSame('pending_pickup', $waiting->fresh()->status);

        // Collected: scan → receive → start sorting are allowed, in that order.
        $parcel = $this->collected();
        $this->scan($this->logistics1, $parcel->waybill_number);
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))
            ->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))->post(route('sc.tracking.start-sorting', $parcel))->assertSessionHas('success');
        $this->assertSame('sorting', $parcel->fresh()->status);
    }

    public function test_only_sorting_center_staff_can_scan_and_history_is_per_center(): void
    {
        $parcel = $this->collected();

        auth()->logout();
        $this->assertContains($this->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->waybill_number])->status(), [302, 403]);   // guest
        $this->assertGuest();
        foreach ([$this->buyer1, $this->seller1, $this->rider1->user] as $user) {
            $res = $this->actingAs($user)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->waybill_number]);
            $this->assertNotSame(route('sc.tracking.show', $parcel), $res->headers->get('Location'));
        }
        $this->assertSame(0, ActivityLog::where('action', 'sc_scan')->count());

        $this->scan($this->logistics1, $parcel->waybill_number);
        $this->actingAs($this->logistics2)->get(route('sc.incoming-parcels'))->assertOk()
            ->assertDontSee('data-scan-history', false)->assertDontSee($parcel->waybill_number);
    }
}
