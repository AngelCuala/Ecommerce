<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Couriers can register with a truck on both the main /register form and /become-courier.
 */
class CourierVehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_form_offers_and_accepts_truck(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('data-wheel="truck"', false);

        $this->post(route('register'), ['role' => 'courier', 'vehicle_type' => 'truck'])
            ->assertSessionDoesntHaveErrors('vehicle_type');
        $this->post(route('register'), ['role' => 'courier', 'vehicle_type' => 'boat'])
            ->assertSessionHasErrors('vehicle_type');
    }

    public function test_become_courier_form_offers_and_accepts_truck(): void
    {
        $buyer = User::create([
            'name' => 'Buyer', 'email' => 'buyer@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)->get(route('courier.register'))->assertOk()->assertSee('value="Truck"', false);

        $this->actingAs($buyer)->post(route('courier.register.store'), ['vehicle_type' => 'Truck'])
            ->assertSessionDoesntHaveErrors('vehicle_type');
        $this->actingAs($buyer)->post(route('courier.register.store'), ['vehicle_type' => 'Boat'])
            ->assertSessionHasErrors('vehicle_type');
    }
}
