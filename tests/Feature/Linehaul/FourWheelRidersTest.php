<?php

namespace Tests\Feature\Linehaul;

use App\Models\Courier;
use App\Models\CourierChecklist;
use App\Models\Rider;
use App\Models\User;
use App\Services\Linehaul;
use App\Services\RiderDispatch;
use Database\Seeders\DemoAccountsAndProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riders and couriers are one account. A 4-wheel rider (car, van, truck) does hub-to-hub trips;
 * a motorcycle or tricycle rider does pickups and deliveries to buyers. The sorting center manages both on Riders.
 */
class FourWheelRidersTest extends TestCase
{
    use RefreshDatabase;

    private function assignable(string $company): array
    {
        return app(Linehaul::class)->assignable(User::where('email', $company)->first())->map(fn ($c) => $c->user->email)->all();
    }

    private function goOnline(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->from(route('courier.trips'))->post(route('courier.trips.duty'), [
            'on' => 1, 'items' => array_fill_keys(CourierChecklist::ITEMS, 'ok'), 'lat' => 14.28, 'lng' => 121.41,
        ]);
    }

    public function test_the_vehicle_decides_hub_to_hub_or_buyer_deliveries(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seed(DemoAccountsAndProductsSeeder::class);
        $sc = User::where('email', 'logistics6@alvy.test')->first();
        $truckUser = User::where('email', 'courier6@alvy.test')->first();
        $truckRider = Rider::where('user_id', $truckUser->id)->first();
        $bike = Rider::whereHas('user', fn ($q) => $q->where('email', 'rider6@alvy.test'))->first();

        // Both are riders on the Riders page; the 4-wheeler is marked hub to hub with its hub.
        $this->actingAs($sc)->get(route('sc.riders'))->assertOk()
            ->assertSee('Jericho Manalo')->assertSee('Jesus Ramos')->assertSee('4-wheel · hub to hub')->assertSee('HUB-SCZ')
            ->assertSee('data-truck-rider="' . $truckRider->id . '"', false)->assertDontSee('data-courier-row', false)->assertDontSee('CUR-');
        $this->assertTrue($truckRider->isTruck());
        $this->assertFalse($bike->isTruck());

        // Online: the 4-wheeler goes in the trip list, never in the buyer delivery list.
        $this->goOnline($truckUser)->assertSessionHas('success');
        $bike->forceFill(['is_online' => true, 'last_seen_at' => now()])->save();
        $truckRider->forceFill(['is_online' => true, 'last_seen_at' => now()])->save();
        $this->assertSame(['courier6@alvy.test'], $this->assignable('logistics6@alvy.test'));
        $delivery = app(RiderDispatch::class)->eligibleRiders($sc->id)->pluck('id')->all();
        $this->assertContains($bike->id, $delivery);
        $this->assertNotContains($truckRider->id, $delivery);
        $this->assertStringContainsString('hub-to-hub', app(RiderDispatch::class)->ineligibleReason($truckRider, $sc->id));

        // Suspending the rider on Riders takes the truck off duty and out of the trip list; reactivating lets them go Online.
        $this->actingAs($sc)->from(route('sc.riders'))->post(route('sc.riders.toggle', $truckRider), ['reason' => 'Truck failed the brake inspection.'])
            ->assertSessionHas('success');
        $this->assertSame('suspended', Courier::where('user_id', $truckUser->id)->value('status'));
        $this->assertSame([], $this->assignable('logistics6@alvy.test'));
        $this->goOnline($truckUser)->assertSessionHas('error', fn ($m) => str_contains($m, 'suspended'));
        $this->actingAs($sc)->from(route('sc.riders'))->post(route('sc.riders.toggle', $truckRider->fresh()))->assertSessionHas('success');
        $this->assertSame('approved', Courier::where('user_id', $truckUser->id)->value('status'));

        // Switching the motorcycle rider to a van makes them hub to hub; back to a motorcycle turns the truck off.
        $bike->update(['vehicle_type' => 'Van']);
        $truck = Courier::where('user_id', $bike->user_id)->first();
        $this->assertSame(['approved', 'Van', 1000], [$truck->status, $truck->vehicle_type, $truck->load_capacity_kg]);
        $this->assertSame(\App\Models\LogisticsHub::where('code', 'HUB-SCZ')->value('id'), $truck->home_hub_id);
        $this->actingAs($bike->user)->get(route('courier.rider'))->assertRedirect(route('courier.trips'));
        $bike->update(['vehicle_type' => 'Tricycle']);
        $this->assertSame('suspended', $truck->fresh()->status);
        $this->actingAs($bike->user)->get(route('courier.rider'))->assertOk()->assertSee('Hub Jobs');

        // The sorting center's Add Rider form offers the vehicles by the work they do.
        $this->actingAs($sc)->get(route('sc.riders'))->assertSee('Deliveries to buyers')->assertSee('4-wheel · hub to hub');
    }

    public function test_old_courier_accounts_become_riders(): void
    {
        $this->seed(DemoAccountsAndProductsSeeder::class);
        // A courier made before riders and couriers were one: only the truck record, no rider.
        $user = User::where('email', 'courier5@alvy.test')->first();
        Rider::where('user_id', $user->id)->delete();
        Courier::where('user_id', $user->id)->update(['vehicle_type' => '4-Wheeler Closed Van Truck']);

        $rider = app(\App\Services\TruckRiders::class)->adopt(Courier::where('user_id', $user->id)->first());

        $this->assertSame(['Truck', 'approved', true], [$rider->vehicle_type, $rider->application_status, $rider->is_active]);
        $this->assertTrue($rider->isTruck());
        $this->assertNull(app(\App\Services\TruckRiders::class)->adopt(Courier::where('user_id', $user->id)->first()));   // only once
    }
}
