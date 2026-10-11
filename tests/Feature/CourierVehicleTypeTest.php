<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    private function courierPayload(array $overrides = []): array
    {
        return array_merge([
            'role' => 'courier', 'last_name' => 'Santos', 'first_name' => 'Mario', 'email' => 'mario.courier@example.com',
            'sex' => 'male', 'contact_no' => '09181234568', 'birthday' => '1995-05-20',
            'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
            'house_number' => '12', 'street' => 'Rizal St', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'vehicle_type' => 'truck', 'plate_number' => 'abc 1234', 'terms' => '1',
            'or_cr_image'       => UploadedFile::fake()->create('license.jpg', 300, 'image/jpeg'),
            'vehicle_reg_image' => UploadedFile::fake()->create('or_cr.pdf', 300, 'application/pdf'),
        ], $overrides);
    }

    public function test_courier_signs_up_with_documents_and_real_details(): void
    {
        Storage::fake('public');
        Mail::fake();

        $this->from(route('register'))->post(route('register'), $this->courierPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('otp.show'));

        $courier = Courier::firstOrFail();
        $this->assertSame('truck', $courier->vehicle_type);
        $this->assertSame('ABC 1234', $courier->plate_number);
        $this->assertSame('09181234568', $courier->contact_no);
        $this->assertSame('Quezon City', $courier->municipality);
        $this->assertSame('1995-05-20', \Carbon\Carbon::parse($courier->birthday)->toDateString());
        Storage::disk('public')->assertExists($courier->id_license_path);
        Storage::disk('public')->assertExists($courier->or_cr_path);
        $this->assertStringContainsString('courier-licenses', $courier->id_license_path);
        $this->assertStringContainsString('courier-vehicle-regs', $courier->or_cr_path);
    }

    public function test_courier_without_documents_gets_errors_not_a_crash(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->courierPayload(['or_cr_image' => null, 'vehicle_reg_image' => null]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['or_cr_image', 'vehicle_reg_image']);

        $this->assertSame(0, Courier::count());
    }

    public function test_courier_step_lists_only_approved_sorting_centers(): void
    {
        $make = fn (string $name, string $role, string $status) => User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@alvy.test',
            'password' => Hash::make('Secret@123'), 'role' => $role, 'approval_status' => $status,
            'street' => 'Hub St.', 'barangay' => 'Poblacion', 'municipality' => 'Makati', 'province' => 'Metro Manila',
        ]);
        $hub = $make('Hub Alpha', 'sorting_center', 'approved');
        $make('Hub Pending', 'sorting_center', 'pending');
        $make('Some Seller', 'seller', 'approved');

        $this->getJson('/api/logistics-providers')
            ->assertOk()
            ->assertExactJson([[
                'id' => $hub->id, 'name' => 'Hub Alpha', 'hub_address' => 'Hub St., Poblacion, Makati, Metro Manila',
            ]]);
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
