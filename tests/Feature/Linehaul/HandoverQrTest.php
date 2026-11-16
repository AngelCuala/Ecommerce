<?php

namespace Tests\Feature\Linehaul;

use App\Models\LinehaulHandover;
use App\Models\LinehaulTrip;
use App\Services\Linehaul;
use App\Services\TripHandover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/**
 * The linehaul driver's Handover QR: the hub scans it (or types the 6-digit code) at loading and at arrival, sees the
 * driver, truck and expected parcels, scans the parcels on / off with live counts, and confirms. TRIP-1: HUB-QC (Logistics 1)
 * → HUB-PSG (Pasig), Courier 1, 5 parcels (3.6 kg).
 */
class HandoverQrTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    private LinehaulTrip $trip;
    private array $parcels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLinehaulTestData();
        // At the start of a QR period, so a code doesn't change in the middle of a step.
        $p = TripHandover::period();
        $this->travelTo(Carbon::createFromTimestamp(intdiv(now()->getTimestamp(), $p) * $p + 1));
        $this->parcels = $this->tripParcels();
        $this->trip = $this->trip1(true, $this->parcels);
        $this->actingAs($this->courier1->user)->post(route('courier.trips.accept', $this->trip->id))->assertSessionHas('success');
    }

    /** What the driver's phone shows now (QR text, 6-digit code, counts). */
    private function phone(): array
    {
        return $this->actingAs($this->courier1->user)->getJson(route('courier.trips.handover', $this->trip->id))->assertOk()->json();
    }

    private function verify($sc, int $hubId, string $code)
    {
        return $this->actingAs($sc)->from(route('sc.handover'))->post(route('sc.handover.verify'), ['hub_id' => $hubId, 'code' => $code]);
    }

    private function scan(LinehaulHandover $h, string $tracking, $sc = null)
    {
        return $this->actingAs($sc ?? $this->logistics1)->postJson(route('sc.handover.scan', $h), ['tracking_number' => $tracking]);
    }

    public function test_handover_at_the_origin_and_at_the_destination(): void
    {
        // The driver shows the QR: it names the trip, driver and hub, not a person.
        $phone = $this->phone();
        $this->assertSame('load', $phone['stage']);
        $this->assertMatchesRegularExpression('/^HO-[0-9A-Z]+-[0-9A-Z]+-L[0-9A-Z]+-[0-9A-Z]+-[0-9A-F]{16}$/', $phone['qr']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $phone['code']);
        $this->assertStringNotContainsString('Villareal', $phone['qr'] . $phone['svg']);
        $this->assertSame(['expected' => 5, 'loaded' => 0, 'unloaded' => 0], array_intersect_key($phone['counts'], array_flip(['expected', 'loaded', 'unloaded'])));
        $this->assertEquals(3.6, $phone['counts']['weight_kg']);
        $this->assertEquals(3000, $phone['counts']['capacity_kg']);
        $this->actingAs($this->courier1->user)->get(route('courier.trips'))->assertSee('Handover QR')->assertSee('data-load', false);

        // Origin: the hub scans the QR → the driver, truck, trip and expected parcels; loading starts.
        $this->actingAs($this->logistics1)->get(route('sc.handover'))->assertOk()->assertSee('Scan driver QR')->assertSee($this->trip->code);
        $r = $this->verify($this->logistics1, $this->hubQc->id, $phone['qr']);
        $h = LinehaulHandover::sole();
        $r->assertRedirect(route('sc.handover.show', $h));
        $this->assertSame(['load', 'qr', 5, 'NAB4521'], [$h->stage, $h->method, $h->expected, $h->plate_number]);
        $this->assertSame('loading', $this->trip->fresh()->status);
        $this->actingAs($this->logistics1)->get(route('sc.handover.show', $h))->assertOk()
            ->assertSee('Ernesto Villareal')->assertSee('NAB4521')->assertSee($this->trip->code)->assertSee('5 parcels')->assertSee('Confirm handover');
        $this->assertTrue($this->trip->events()->where('type', 'handover_scanned')->exists());

        // The hub scans every parcel on; the driver's counts follow.
        foreach ($this->parcels as $i => $p) {
            $this->scan($h, $p->tracking_number)->assertOk()->assertJson(['ok' => true, 'stay' => true])->assertJsonPath('counts.loaded', $i + 1);
        }
        $this->assertSame(5, $this->phone()['counts']['loaded']);
        $this->actingAs($this->logistics1)->post(route('sc.handover.confirm', $h))->assertSessionHas('success', fn ($m) => str_contains($m, 'sealed'));
        $this->assertNotNull($this->trip->fresh()->sealed_at);
        $this->assertSame(5, $h->fresh()->scanned);
        $this->assertNull($this->phone()['stage']);   // no QR to show until arrival

        // The driver departs and arrives.
        app(Linehaul::class)->depart($this->courier1, $this->trip->id, null, null);
        app(Linehaul::class)->arrived($this->courier1, $this->trip->id, self::AT_PSG[0], self::AT_PSG[1]);

        // Destination: receiving needs the QR first.
        $this->actingAs($this->pasig);
        $this->expectsError(fn () => app(Linehaul::class)->receiveScan($this->pasig, $this->hubPsg->id, $this->parcels[0]->tracking_number), 'Handover QR');
        $this->actingAs($this->pasig)->get(route('sc.trips.show', $this->trip))->assertSee('data-trip-receive-qr', false);

        $phone = $this->phone();
        $this->assertSame('receive', $phone['stage']);
        $this->verify($this->pasig, $this->hubPsg->id, $phone['qr']);
        $in = LinehaulHandover::where('stage', 'receive')->sole();
        // A parcel of another trip is refused and logged as extra.
        $other = $this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id]);
        $this->scan($in, $other->tracking_number, $this->pasig)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'extra'));
        $this->assertTrue($this->trip->events()->where('type', 'extra_scan')->exists());
        foreach ($this->parcels as $i => $p) {
            $this->scan($in, $p->tracking_number, $this->pasig)->assertOk()->assertJsonPath('counts.unloaded', $i + 1);
        }
        $this->actingAs($this->pasig)->post(route('sc.handover.confirm', $in))->assertSessionHas('success');
        $this->assertSame('completed', $this->trip->fresh()->status);
        $this->assertSame($this->hubPsg->id, $this->parcels[0]->fresh()->current_hub_id);
    }

    private function expectsError(callable $do, string $contains): void
    {
        try {
            $do();
            $this->fail('Expected a refusal.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString($contains, collect($e->errors())->flatten()->first());
        }
    }

    public function test_an_expired_or_reused_qr_is_refused(): void
    {
        $qr = $this->phone()['qr'];

        // A screenshot used again: the same QR works once.
        $this->verify($this->logistics1, $this->hubQc->id, $qr)->assertRedirect();
        $this->verify($this->logistics1, $this->hubQc->id, $qr)->assertSessionHas('error', fn ($m) => str_contains($m, 'already used'));

        // Two periods later the old QR has expired (the phone shows a new one).
        $this->travel(TripHandover::period() * 2)->seconds();
        $this->verify($this->logistics1, $this->hubQc->id, $qr)->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));
        $this->assertNotSame($qr, $this->phone()['qr']);
        // One changed character: not valid.
        $fresh = $this->phone()['qr'];
        $forged = substr($fresh, 0, -1) . (substr($fresh, -1) === 'A' ? 'B' : 'A');
        $this->verify($this->logistics1, $this->hubQc->id, $forged)->assertSessionHas('error', fn ($m) => str_contains($m, 'not a valid'));
        $this->assertSame(1, LinehaulHandover::count());
    }

    public function test_the_wrong_hub_or_the_wrong_stage_is_refused(): void
    {
        $qr = $this->phone()['qr'];
        // Logistics 1's other hub, and another company's hub.
        $this->verify($this->logistics1, $this->hubStr->id, $qr)->assertSessionHas('error', fn ($m) => str_contains($m, 'HUB-QC, not HUB-STR'));
        $this->verify($this->pasig, $this->hubPsg->id, $qr)->assertSessionHas('error', fn ($m) => str_contains($m, 'not HUB-PSG'));

        // Sealed: no more loading handover; the destination can't receive before arrival.
        $this->sealTrip($this->trip);
        $loadQr = TripHandover::qrFor($this->trip->fresh(), 'load', intdiv(now()->getTimestamp(), TripHandover::period()));
        $this->verify($this->logistics1, $this->hubQc->id, $loadQr)->assertSessionHas('error', fn ($m) => str_contains($m, 'already sealed'));
        app(Linehaul::class)->depart($this->courier1, $this->trip->id, null, null);
        $early = TripHandover::qrFor($this->trip->fresh(), 'receive', intdiv(now()->getTimestamp(), TripHandover::period()));
        $this->verify($this->pasig, $this->hubPsg->id, $early)->assertSessionHas('error', fn ($m) => str_contains($m, "hasn't arrived"));
        $this->assertSame(1, LinehaulHandover::count());   // only the seal's own handover
    }

    public function test_an_inactive_drivers_qr_is_refused(): void
    {
        $qr = $this->phone()['qr'];
        $this->courier1->forceFill(['status' => 'suspended'])->save();
        $this->verify($this->logistics1, $this->hubQc->id, $qr)->assertSessionHas('error', fn ($m) => str_contains($m, "isn't active"));
        $this->assertSame(0, LinehaulHandover::count());
    }

    public function test_a_count_mismatch_needs_a_note_before_sealing(): void
    {
        $this->verify($this->logistics1, $this->hubQc->id, $this->phone()['qr']);
        $h = LinehaulHandover::sole();
        // Nothing scanned yet: can't seal at all.
        $this->actingAs($this->logistics1)->post(route('sc.handover.confirm', $h))->assertSessionHas('error', fn ($m) => str_contains($m, 'Scan the parcels'));
        foreach (array_slice($this->parcels, 0, 4) as $p) {
            $this->scan($h, $p->tracking_number)->assertOk();
        }
        // 4 of 5: the hub page warns, and sealing needs a note.
        $this->actingAs($this->logistics1)->get(route('sc.handover.show', $h))->assertSee('Expected 5, loaded 4.');
        $this->actingAs($this->logistics1)->getJson(route('sc.handover.live', $h))->assertJsonPath('counts.loaded', 4)->assertJsonPath('counts.load_mismatch', true);
        $this->actingAs($this->logistics1)->post(route('sc.handover.confirm', $h))->assertSessionHas('error', fn ($m) => str_contains($m, '4 of 5') && str_contains($m, 'note'));
        $this->assertNull($this->trip->fresh()->sealed_at);

        $this->actingAs($this->logistics1)->post(route('sc.handover.confirm', $h), ['note' => 'One parcel was not on the shelf; it stays at HUB-QC.'])->assertSessionHas('success');
        $trip = $this->trip->fresh();
        $this->assertNotNull($trip->sealed_at);
        $this->assertSame(4, $trip->onBoard()->count());   // the 5th came off the trip, back at the hub
        $this->assertSame($this->hubQc->id, $this->parcels[4]->fresh()->current_hub_id);
        $this->assertSame('One parcel was not on the shelf; it stays at HUB-QC.', $h->fresh()->note);
        $this->assertTrue($trip->events()->where('type', 'sealed')->where('reason', 'like', '%not on the shelf%')->exists());
        app(Linehaul::class)->depart($this->courier1, $trip->id, null, null);
        $this->assertSame('in_transit', $trip->fresh()->status);
    }

    public function test_the_six_digit_code_works_like_the_qr(): void
    {
        $code = $this->phone()['code'];
        $this->verify($this->logistics1, $this->hubQc->id, '000000' === $code ? '111111' : '000000')->assertSessionHas('error', fn ($m) => str_contains($m, 'No trip at HUB-QC'));
        $this->verify($this->logistics1, $this->hubQc->id, substr($code, 0, 3) . ' ' . substr($code, 3))->assertRedirect();
        $h = LinehaulHandover::sole();
        $this->assertSame('code', $h->method);
        $this->assertSame((int) $this->trip->id, (int) $h->linehaul_trip_id);
        // Used once only; and it expires like the QR.
        $this->verify($this->logistics1, $this->hubQc->id, $code)->assertSessionHas('error', fn ($m) => str_contains($m, 'already used'));
        $this->travel(TripHandover::period() * 3)->seconds();
        $this->verify($this->logistics1, $this->hubQc->id, $code)->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));
    }

    public function test_the_hub_app_can_do_the_handover_too(): void
    {
        $this->app['auth']->forgetGuards();
        $token = $this->logistics1->createToken('t')->plainTextToken;
        $phone = $this->withToken($this->courier1->user->createToken('c')->plainTextToken)->getJson("/api/v1/linehaul/trips/{$this->trip->id}/handover")->assertOk()->json();
        $this->app['auth']->forgetGuards();
        $v = $this->withToken($token)->postJson('/api/v1/logistics/handover', ['hub_id' => $this->hubQc->id, 'code' => $phone['code']])->assertOk()
            ->assertJsonPath('handover.driver.name', 'Ernesto Villareal')->assertJsonPath('handover.counts.expected', 5);
        $id = $v->json('handover.id');
        foreach ($this->parcels as $p) {
            $this->withToken($token)->postJson("/api/v1/logistics/handover/$id/scan", ['tracking' => $p->tracking_number])->assertOk();
        }
        $this->withToken($token)->postJson("/api/v1/logistics/handover/$id/confirm")->assertOk()->assertJsonPath('trip.counts.sealed', true);
    }
}
