<?php

namespace Tests\Concerns;

use App\Models\Courier;
use App\Models\LinehaulHandover;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Models\Parcel;
use App\Models\User;
use App\Services\Linehaul;
use App\Services\TripHandover;

/**
 * The Courier Modules (C1–C7) test data: Logistics 1's hubs HUB-QC and HUB-STR, Logistics 2's (Agila) HUB-DVO, the Pasig
 * sorting center's HUB-PSG, Couriers 1–4 (Courier 3 suspended, Courier 4 at Agila) and TRIP-1's five parcels from Seller 1
 * waiting at HUB-QC. A trip goes to another sorting center's hub (Linehaul::createTrip), so TRIP-1 runs HUB-QC → HUB-PSG.
 */
trait LinehaulTestData
{
    use RiderTestData;

    protected LogisticsHub $hubQc, $hubPsg, $hubStr, $hubDvo;

    /** The sorting center that owns HUB-PSG and receives TRIP-1. */
    protected User $pasig;
    protected Courier $courier1, $courier2, $courier3, $courier4;

    /** Where the hubs are (Commonwealth QC, Kapitolyo Pasig, Balibago Sta. Rosa, Buhangin Davao). */
    protected const AT_QC = [14.6760, 121.0870];
    protected const AT_PSG = [14.5710, 121.0590];
    protected const AT_STR = [14.3120, 121.1110];

    protected function seedLinehaulTestData(): void
    {
        $this->seedRiderTestData();
        $hub = fn (User $sc, string $code, string $name, string $address, array $at) => LogisticsHub::create([
            'sorting_center_id' => $sc->id, 'code' => $code, 'name' => $name, 'address' => $address, 'latitude' => $at[0], 'longitude' => $at[1]]);
        $this->hubQc = $hub($this->logistics1, 'HUB-QC', 'Bilis Hatid QC First-Mile Hub', '1200 Commonwealth Ave., Brgy. Holy Spirit, Quezon City', self::AT_QC);
        $this->pasig = $this->company('admin@pasighub.example.com', 'Carlo Mendoza', 'Pasig Hub Logistics Inc.', 'Pasig#Hub2026');
        $this->hubPsg = $hub($this->pasig, 'HUB-PSG', 'Bilis Hatid Pasig Main Hub', 'Unit 5B, 88 West Capitol Drive, Brgy. Kapitolyo, Pasig City', self::AT_PSG);
        $this->hubStr = $hub($this->logistics1, 'HUB-STR', 'Bilis Hatid Sta. Rosa Hub', 'Lot 3, Balibago Complex, Brgy. Balibago, Santa Rosa, Laguna', self::AT_STR);
        $this->hubDvo = $hub($this->logistics2, 'HUB-DVO', 'Agila Davao Hub', 'Door 3, 210 Sampaguita St., Brgy. Buhangin, Davao City', [7.1100, 125.6100]);

        $this->courier1 = $this->linehaulCourier($this->logistics1, $this->hubQc, 'ernesto.villareal@example.com', 'Ernesto', 'Villareal', 'Courier@2026',
            '4-Wheeler Closed Van Truck', 'NAB4521', 3000, 'N02-15-345678', 'B, B2, C');
        $this->courier2 = $this->linehaulCourier($this->logistics1, $this->hubStr, 'rogelio.dimaculangan@example.com', 'Rogelio', 'Dimaculangan', 'Linehaul#Rog26',
            'Van', 'CAV7788', 800, 'D10-12-654321', 'B, B2');
        $this->courier3 = $this->linehaulCourier($this->logistics1, $this->hubQc, 'courier.test01@example.com', 'Danilo', 'Ramos', 'Courier@2026',
            'Van', 'DAN3030', 800, 'D11-11-111111', 'B', 'suspended');
        $this->courier4 = $this->linehaulCourier($this->logistics2, $this->hubDvo, 'courier.test02@example.com', 'Joel', 'Abellana', 'Courier@2026',
            'Van', 'AGL5050', 800, 'D12-12-121212', 'B');
    }

    protected function linehaulCourier(User $sc, LogisticsHub $home, string $email, string $first, string $last, string $password,
        string $vehicle, string $plate, int $kg, string $license, string $codes, string $status = 'approved'): Courier
    {
        $user = $this->account('courier', $email, "$first $last", $password);

        return Courier::create([
            'user_id' => $user->id, 'sorting_center_id' => $sc->id, 'home_hub_id' => $home->id, 'first_name' => $first, 'last_name' => $last,
            'contact_no' => '0919' . str_pad((string) (crc32($email) % 10000000), 7, '0', STR_PAD_LEFT),
            'birthday' => '1988-07-15', 'age' => 38, 'vehicle_type' => $vehicle, 'plate_number' => $plate, 'load_capacity_kg' => $kg,
            'license_number' => $license, 'license_type' => 'Professional', 'dl_codes' => $codes, 'license_expiry' => '2029-07-15',
            'id_license_path' => 'courier-documents/license.jpg', 'or_cr_path' => 'courier-documents/orcr.pdf', 'nbi_path' => 'courier-documents/nbi.pdf',
            'status' => $status, 'submitted_at' => now(), 'reviewed_by' => $sc->id,
        ]);
    }

    /** On Duty now, at a hub (checklist passed). */
    protected function onDuty(Courier ...$couriers): void
    {
        foreach ($couriers as $c) {
            $at = match ($c->home_hub_id) { $this->hubPsg->id => self::AT_PSG, $this->hubStr->id => self::AT_STR, default => self::AT_QC };
            $c->forceFill(['on_duty' => true, 'duty_since' => now(), 'checklist_at' => now(), 'last_lat' => $at[0], 'last_lng' => $at[1], 'last_seen_at' => now()])->save();
        }
    }

    /** TRIP-1's parcels: 4 × P-02 (0.8 kg) and 1 × P-01 Natural-M (0.4 kg) to ADDR-2, waiting at HUB-QC. */
    protected function tripParcels(int $count = 5): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->parcel('picked_up', ['weight_kg' => $i === 4 ? 0.4 : 0.8, 'current_hub_id' => $this->hubQc->id,
                'destination_municipality' => 'Makati City']);
        }

        return $out;
    }

    /** TRIP-1: HUB-QC → HUB-PSG with the 5 parcels, created by Logistics 1 and accepted by the Pasig center (optionally assigned to Courier 1). */
    protected function trip1(bool $assign = true, ?array $parcels = null): LinehaulTrip
    {
        $parcels ??= $this->tripParcels();
        $this->actingAs($this->logistics1);
        $trip = app(Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubPsg->id,
            'parcel_ids' => array_map(fn (Parcel $p) => $p->id, $parcels), 'scheduled_departure_at' => now()->addHour()]);
        // The receiving sorting center accepts the trip before it can be loaded.
        app(Linehaul::class)->respondHandover($this->pasig, $trip->fresh(), true);
        if ($assign) {
            $this->onDuty($this->courier1);
            app(Linehaul::class)->assign($this->logistics1, $trip, $this->courier1->id);
        }
        $this->app['auth']->forgetGuards();

        return $trip->fresh();
    }

    /** The driver's current Handover QR text for the trip's handover stage (as the driver's phone shows it). */
    protected function handoverQr(LinehaulTrip $trip): string
    {
        $trip = $trip->fresh(['courier', 'origin', 'destination']);
        $stage = TripHandover::stageOf($trip) ?? $this->fail("{$trip->code} ({$trip->status}) is not waiting for a handover.");

        return TripHandover::qrFor($trip, $stage, intdiv(now()->getTimestamp(), TripHandover::period()));
    }

    /**
     * The origin hub scans the driver's Handover QR and confirms (seals the truck). By default the hub also scans every
     * parcel still not loaded, so the counts match; with $loadAll false it seals what the driver already loaded.
     */
    protected function sealTrip(LinehaulTrip $trip, bool $loadAll = true, ?string $note = null): LinehaulTrip
    {
        $trip = $trip->fresh();
        $sc = User::find($trip->sorting_center_id);
        $this->actingAs($sc);
        $service = app(TripHandover::class);
        $h = $service->verify($sc, (int) $trip->origin_hub_id, $this->handoverQr($trip));
        if ($loadAll) {
            foreach ($trip->fresh()->onBoard()->where('status', 'pending')->with('parcel')->get() as $item) {
                $service->scan($h, $sc, $item->parcel->tracking_number);
            }
        }
        $service->confirm($h, $sc, $note);
        $this->app['auth']->forgetGuards();

        return $trip->fresh();
    }

    /** The destination hub scans the driver's Handover QR after arrival (receiving can then start). */
    protected function arrivalHandover(LinehaulTrip $trip): LinehaulHandover
    {
        $trip = $trip->fresh();
        $sc = User::find($trip->receiverId());
        $this->actingAs($sc);
        $h = app(TripHandover::class)->verify($sc, (int) $trip->destination_hub_id, $this->handoverQr($trip));
        $this->app['auth']->forgetGuards();

        return $h;
    }
}
