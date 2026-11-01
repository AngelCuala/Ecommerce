<?php

namespace Tests\Feature\Logistics;

use App\Models\ActivityLog;
use App\Models\Parcel;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Support\WaybillScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * "Scan Waybill" pop-up: the JSON lookups behind it for sorting centers, admins and riders. Each opens the exact
 * parcel this person may see, refuses bad and unknown codes with the pop-up's messages, and records nothing.
 */
class WaybillScannerPopupTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1, $this->rider2);
    }

    private function popup(User $user, string $route, ?string $code)
    {
        return $this->actingAs($user)->postJson(route($route), ['tracking_number' => $code]);
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'approval_status' => 'approved']);
    }

    /** Collected by Logistics 1's rider, on its way to Logistics 1. */
    private function collected(): Parcel
    {
        $parcel = $this->parcel('pickup_approved');
        $pickup = $this->pickupFor($this->rider1, $parcel);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number]);
        return $parcel->fresh();
    }

    // ── Sorting center ─────────────────────────────────────────

    public function test_sorting_center_scan_opens_the_exact_parcel_and_records_nothing(): void
    {
        $parcel = $this->collected();
        $other = $this->collected();   // a second parcel at the same center: the right one must open
        $events = ShipmentEvent::where('parcel_id', $parcel->id)->count();

        $this->popup($this->logistics1, 'sc.parcels.scan', ' ' . strtolower($parcel->waybill_number) . ' ')
            ->assertOk()->assertJson(['ok' => true, 'url' => route('sc.tracking.show', $parcel)])
            ->assertJsonPath('message', fn ($m) => str_contains($m, $parcel->tracking_number));
        $this->assertNotSame(route('sc.tracking.show', $other), route('sc.tracking.show', $parcel));

        // The tracking number works too, and the parcel page shows what was found.
        $this->popup($this->logistics1, 'sc.parcels.scan', $parcel->tracking_number)->assertJsonPath('url', route('sc.tracking.show', $parcel));
        $this->actingAs($this->logistics1)->get(route('sc.tracking.show', $parcel))->assertOk()->assertSee($parcel->waybill_number);

        $this->assertSame($parcel->status, $parcel->fresh()->status);
        $this->assertSame($events, ShipmentEvent::where('parcel_id', $parcel->id)->count());
        $this->assertSame('found', ActivityLog::where('action', 'sc_scan')->latest('id')->first()->status);   // still in the scan history
    }

    public function test_sorting_center_bad_unknown_and_other_centers_codes_get_the_popup_messages(): void
    {
        $this->popup($this->logistics1, 'sc.parcels.scan', 'hello world!')->assertStatus(422)
            ->assertJson(['ok' => false, 'reason' => 'invalid', 'message' => WaybillScan::INVALID]);
        $this->popup($this->logistics1, 'sc.parcels.scan', '')->assertStatus(422)->assertJson(['reason' => 'invalid']);
        $this->popup($this->logistics1, 'sc.parcels.scan', 'PH000000000000000')->assertStatus(404)
            ->assertJson(['ok' => false, 'reason' => 'not_found', 'message' => WaybillScan::NOT_FOUND]);

        // Another company's parcel looks exactly like an unknown one: nothing about it is shown.
        $parcel = $this->collected();
        $this->popup($this->logistics2, 'sc.parcels.scan', $parcel->waybill_number)->assertStatus(404)->assertJsonMissing(['url' => route('sc.tracking.show', $parcel)]);
    }

    public function test_scanning_a_received_parcel_again_still_opens_it_and_records_nothing_new(): void
    {
        $parcel = $this->collected();
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))
            ->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('success');
        $events = ShipmentEvent::where('parcel_id', $parcel->id)->count();

        $this->popup($this->logistics1, 'sc.parcels.scan', $parcel->waybill_number)->assertOk()
            ->assertJson(['ok' => true, 'url' => route('sc.tracking.show', $parcel)])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already received'));
        $this->assertSame($events, ShipmentEvent::where('parcel_id', $parcel->id)->count());
        $this->assertSame('duplicate', ActivityLog::where('action', 'sc_scan')->latest('id')->first()->status);
    }

    // ── Access and where the button is ─────────────────────────

    public function test_only_sorting_center_staff_can_use_the_lookup(): void
    {
        $parcel = $this->collected();
        $this->app['auth']->forgetGuards();   // a guest (the setup signed a rider in)
        $this->postJson(route('sc.parcels.scan'), ['tracking_number' => $parcel->waybill_number])->assertStatus(401);
        foreach ([$this->buyer1, $this->rider1->user, $this->admin()] as $user) {
            $this->popup($user, 'sc.parcels.scan', $parcel->waybill_number)->assertForbidden();
        }
    }

    public function test_scan_qr_is_only_on_the_shipment_board(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.tracking'))->assertOk()
            ->assertSee('Scan QR')->assertSee('data-waybill-scanner', false)->assertSee('data-lookup="' . route('sc.parcels.scan') . '"', false)
            ->assertSee('Position the waybill QR code inside the scanning frame.')->assertSee('Start Scanner')->assertSee('Stop Scanner')
            ->assertSee('js/vendor/zxing.min.js', false);

        foreach ([route('sc.incoming-parcels'), route('sc.parcel-sorting'), route('sc.dashboard')] as $url) {
            $this->actingAs($this->logistics1)->get($url)->assertOk()->assertDontSee('data-waybill-scanner', false);
        }
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-waybill-scanner', false);
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertOk()->assertDontSee('data-waybill-scanner', false);
    }

}
