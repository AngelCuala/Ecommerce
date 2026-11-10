<?php

namespace Tests\Feature\Linehaul;

use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\User;
use App\Services\Linehaul;
use Database\Seeders\DemoAccountsAndProductsSeeder;
use Database\Seeders\SortingCenterCouriersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every sorting center gets a 4-wheel rider (a truck) that can sign in, go Online and be assigned hub-to-hub trips. */
class SortingCenterCouriersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_sorting_center_gets_a_working_courier(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seed(DemoAccountsAndProductsSeeder::class);
        $this->seed(SortingCenterCouriersSeeder::class);   // running it again changes nothing

        $companies = User::where('role', 'sorting_center')->get();
        $this->assertCount(6, $companies);
        $this->assertSame(6, Courier::count());
        $this->assertSame(['City of Calamba', 'City of Santa Rosa', 'City of Biñan', 'Los Baños', 'Luisiana', 'Santa Cruz'],
            $companies->pluck('assigned_municipality')->all());
        foreach ($companies as $n => $sc) {
            $courier = Courier::where('sorting_center_id', $sc->id)->with('user', 'homeHub')->sole();
            $this->assertSame('courier' . ($n + 1) . '@alvy.test', $courier->user->email);
            $this->assertSame('approved', $courier->status);
            $this->assertSame($sc->id, $courier->homeHub->sorting_center_id);
            $this->assertSame('Truck', $courier->vehicle_type);
            // Two riders per company: one delivers to buyers, the 4-wheeler goes hub to hub.
            $riders = \App\Models\Rider::where('sorting_center_id', $sc->id)->get();
            $this->assertSame(2, $riders->count());
            $this->assertSame(1, $riders->filter->isTruck()->count());
            $this->assertTrue($riders->firstWhere('user_id', $courier->user_id)->isTruck());
        }

        // Luisiana and Santa Cruz have one account of every role.
        foreach (['Luisiana' => 5, 'Santa Cruz' => 6] as $town => $n) {
            $this->assertSame($town, User::where('email', "logistics{$n}@alvy.test")->value('assigned_municipality'));
            $this->assertSame('Motorcycle', \App\Models\Rider::whereHas('user', fn ($q) => $q->where('email', "rider{$n}@alvy.test"))->value('vehicle_type'));
            $this->assertSame('Truck', \App\Models\Rider::whereHas('user', fn ($q) => $q->where('email', "courier{$n}@alvy.test"))->value('vehicle_type'));
            $seller = User::where('email', "seller{$n}@alvy.test")->first();
            $this->assertSame($town, \App\Models\SellerApplication::where('user_id', $seller->id)->value('municipality'));
            $this->assertSame(6, \App\Models\Product::where('seller_id', $seller->id)->published()->count());
            $buyer = User::where('email', 'buyer' . ($n - 2) . '@alvy.test')->first();
            $this->assertSame($town, \App\Models\UserAddress::where('user_id', $buyer->id)->value('city'));
        }

        // courier1 signs in to the app, passes the checklist and goes On Duty.
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'courier1@alvy.test', 'password' => SortingCenterCouriersSeeder::PASSWORD]);
        $login->assertOk()->assertJsonPath('user.home', 'linehaul');
        $items = array_fill_keys(\App\Models\CourierChecklist::ITEMS, 'ok');
        $this->withToken($login->json('token'))->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $items, 'lat' => 14.21, 'lng' => 121.16])
            ->assertOk()->assertJsonPath('courier.duty_label', 'On Duty');

        // Now Calamba's logistics company can pick them for a trip.
        $calamba = User::where('email', 'logistics1@alvy.test')->first();
        $this->assertSame(['courier1@alvy.test'], app(Linehaul::class)->assignable($calamba)->map(fn ($c) => $c->user->email)->all());
        $this->assertCount(0, app(Linehaul::class)->assignable(User::where('email', 'logistics2@alvy.test')->first()));   // Off Duty
    }
}
