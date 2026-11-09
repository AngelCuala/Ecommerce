<?php

namespace Tests\Feature\Linehaul;

use App\Models\Courier;
use App\Models\CourierChecklist;
use App\Models\User;
use App\Services\Linehaul;
use Database\Seeders\DemoAccountsAndProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A truck courier goes On Duty on the website and shows up in the company's courier list for trips. */
class CourierWebDutyTest extends TestCase
{
    use RefreshDatabase;

    private function names(string $company): array
    {
        return app(Linehaul::class)->assignable(User::where('email', $company)->first())->map(fn ($c) => $c->user->email)->all();
    }

    public function test_on_duty_on_the_website_makes_the_courier_assignable(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seed(DemoAccountsAndProductsSeeder::class);
        $user = User::where('email', 'courier6@alvy.test')->first();
        $courier = Courier::where('user_id', $user->id)->first();

        // The courier portal opens on Hub Trips, Off Duty, with the Go On Duty button.
        $this->actingAs($user)->get(route('courier.dashboard'))->assertRedirect(route('courier.trips'));
        $this->actingAs($user)->get(route('courier.trips'))->assertOk()
            ->assertSee('Go Online')->assertSee("You're Offline", false)->assertSee('Pre-trip checklist')->assertSee('Hub Jobs')->assertSee('Job History')->assertSee('Rider Pay')->assertSee('HUB-SCZ')->assertSee('data-duty-pill', false)
            ->assertDontSee('Delivery History');
        $this->assertSame([], $this->names('logistics6@alvy.test'));

        // The checklist must be complete.
        $this->actingAs($user)->from(route('courier.trips'))->post(route('courier.trips.duty'), ['on' => 1, 'items' => ['Tires' => 'ok'], 'lat' => 14.28, 'lng' => 121.41])
            ->assertRedirect(route('courier.trips'))->assertSessionHas('error');
        $this->assertFalse($courier->fresh()->on_duty);

        $ok = array_fill_keys(CourierChecklist::ITEMS, 'ok');
        $this->actingAs($user)->from(route('courier.trips'))->post(route('courier.trips.duty'), ['on' => 1, 'items' => $ok, 'lat' => 14.28, 'lng' => 121.41])
            ->assertSessionHas('success');
        $this->assertTrue($courier->fresh()->isOnDutyNow());
        $this->assertSame(['courier6@alvy.test'], $this->names('logistics6@alvy.test'));   // shows up in the trip's Courier list
        $this->assertSame([], $this->names('logistics5@alvy.test'));                       // only for their own company

        // With every page closed they drop off after 5 minutes; an open page's ping brings them back.
        $this->travel(6)->minutes();
        $this->assertSame([], $this->names('logistics6@alvy.test'));
        $this->actingAs($user)->postJson(route('courier.trips.ping'))->assertOk()->assertJson(['on_duty' => true, 'duty_label' => 'On Duty']);
        $this->assertSame(['courier6@alvy.test'], $this->names('logistics6@alvy.test'));

        // Off Duty.
        $this->actingAs($user)->from(route('courier.trips'))->post(route('courier.trips.duty'), ['on' => 0])->assertSessionHas('success');
        $this->assertFalse($courier->fresh()->on_duty);
        $this->assertSame([], $this->names('logistics6@alvy.test'));

        // Riders don't have Hub Trips.
        $this->actingAs(User::where('email', 'rider6@alvy.test')->first())->get(route('courier.trips'))->assertForbidden();
    }
}
