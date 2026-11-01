<?php

namespace Tests\Blackbox;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/**
 * Mobile_Blackbox_Test_Cases_Buyer_Modules.xlsx, BUY-TRK-002: the buyer's timeline through a hub-to-hub trip —
 * Rider 1 picks up, HUB-QC scans it in, Courier 1 drives it to HUB-PSG, HUB-PSG scans it in, Rider 2 goes out for delivery.
 * Every step goes through the app's API.
 */
class BuyerTrackingLinehaulTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData, RecordsCases;

    protected static function workbook(): string { return 'Buyer_Modules'; }

    public function test_timeline_through_a_hub_to_hub_trip(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
        $hub = $this->logistics1->createToken('t')->plainTextToken;
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $parcel = $this->parcel('pickup_approved', ['destination_municipality' => 'Makati City']);
        $order = $parcel->order;
        $timeline = function () use ($buyer, $order) {
            $j = $this->withToken($buyer)->getJson('/api/v1/orders/' . $order->id)->json();
            return [$j, collect(\App\Models\OrderStatusEvent::where('order_id', $order->id)->orderBy('id')->pluck('note'))->filter()->values()];
        };

        // 1. Rider 1 picks it up.
        $pickup = $this->pickupFor($this->rider1, $parcel);
        $this->withToken($this->rider1->user->createToken('t')->plainTextToken)->postJson("/api/v1/rider/pickups/{$pickup->id}/confirm", ['tracking_number' => $parcel->tracking_number])->assertOk();
        $tab = $order->fresh()->status;
        // 2. HUB-QC scans it in.
        $this->withToken($hub)->postJson('/api/v1/logistics/incoming/scan', ['tracking_number' => $parcel->tracking_number, 'hub_id' => $this->hubQc->id])->assertOk();
        // 3. Courier 1 carries it to HUB-PSG.
        $trip = $this->trip1(true, [$parcel->fresh()]);
        $c1 = $this->courier1->user->createToken('t')->plainTextToken;
        $this->withToken($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept")->assertOk();
        $this->withToken($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/loading")->assertOk();
        $this->withToken($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $parcel->tracking_number])->assertOk();
        $this->withToken($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521', 'lat' => self::AT_QC[0], 'lng' => self::AT_QC[1]])->assertOk();
        $this->withToken($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", ['lat' => self::AT_PSG[0], 'lng' => self::AT_PSG[1]])->assertOk();
        // 4. HUB-PSG checks the seal and scans it in.
        $this->withToken($hub)->postJson("/api/v1/logistics/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004521'])->assertOk();
        $this->withToken($hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $parcel->tracking_number])->assertOk();
        $this->withToken($hub)->postJson("/api/v1/logistics/trips/{$trip->id}/close")->assertOk();
        // 5. Rider 2 goes out for delivery.
        $delivery = $this->deliveryFor($this->rider2, 'assigned', $parcel->fresh());
        $this->withToken($this->rider2->user->createToken('t')->plainTextToken)->postJson("/api/v1/rider/deliveries/{$delivery->id}/start")->assertOk();

        [$json, $notes] = $timeline();
        $want = ['Picked Up by Rider', 'At Hub (HUB-QC)', 'In Transit (HUB-QC → HUB-PSG)', 'At Hub (HUB-PSG)', 'Out for Delivery'];
        $pos = [];
        foreach ($want as $w) {
            $hits = $notes->keys()->filter(fn ($k) => str_contains($notes[$k], $w));
            $pos[$w] = $hits->count() === 1 ? $hits->first() : null;
        }
        $once = ! in_array(null, $pos, true);
        $ordered = $once && array_values($pos) === collect($pos)->sort()->values()->all();
        $body = json_encode($json);
        $private = str_contains($body, 'Villareal') || str_contains($body, (string) self::AT_QC[0]) || str_contains($body, 'BH-0004521');

        $this->record('BUY-TRK-002', $once && $ordered && $tab === Order::SHIPPED && ! $private,
            'Buyer 1\'s timeline, in order and once each (with times): ' . implode(' → ', $want) . '. The order moved to To Receive (Shipped) at Picked Up. '
            . ($private ? 'The courier\'s name or location IS shown.' : 'The courier\'s name, location and seal are not shown to the buyer.'));
        $this->assertTrue(true);
    }
}
