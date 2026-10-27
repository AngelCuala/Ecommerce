<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use App\Services\LoginOtp;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Rules from the mobile black-box workbooks (Testcases/*.xlsx), phase 1: codes last 5 minutes, a buyer is active
 * after the code, couriers are not riders and couriers/riders don't shop, and Add Rider checks names and numbers.
 */
class WorkbookRulesTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        config(['services.id_check.enabled' => false]);
        $this->seedRiderTestData();
    }

    public function test_email_codes_last_five_minutes(): void
    {
        $this->assertSame(5, LoginOtp::TTL_MINUTES);
    }

    public function test_an_approved_rider_sign_up_takes_hub_jobs_and_riders_do_not_shop(): void
    {
        $u = User::create(['name' => 'Ernesto Villareal', 'email' => 'ernesto@example.com', 'password' => bcrypt('Kurier@2026'), 'role' => 'courier_pending',
            'approval_status' => 'pending', 'email_verified_at' => now()]);
        $c = Courier::create(['user_id' => $u->id, 'sorting_center_id' => $this->logistics1->id, 'last_name' => 'Villareal', 'first_name' => 'Ernesto', 'sex' => 'Male',
            'contact_no' => '09276543210', 'birthday' => '1990-07-15', 'age' => 36, 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Truck', 'plate_number' => 'NAB4521', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'pending']);
        app(\App\Services\CourierReview::class)->approve($c, $this->logistics1);

        // Someone who signed up as a rider becomes a rider of the approving hub (Hub Jobs).
        $this->assertTrue(Rider::where('user_id', $u->id)->where('sorting_center_id', $this->logistics1->id)->where('application_status', 'approved')->exists());
        $t = $u->createToken('t')->plainTextToken;
        $this->withToken($t)->getJson('/api/v1/rider/jobs')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($t)->getJson('/api/v1/cart')->assertForbidden()->assertJsonPath('message', 'Courier and rider accounts are for deliveries. Please sign in with a buyer account to shop.');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->rider1->user->createToken('t')->plainTextToken)->getJson('/api/v1/cart')->assertForbidden();
        $this->app['auth']->forgetGuards();

        // On the website the shop sends them to their own pages.
        $this->actingAs($u->fresh())->get(route('home'))->assertRedirect(route('courier.dashboard'));
        $this->actingAs($this->rider1->user)->get(route('cart.index'))->assertRedirect(route('courier.rider'));
    }

    public function test_add_rider_checks_the_name_and_a_number_used_elsewhere(): void
    {
        $this->logistics1->forceFill(['assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => '043412000',
            'assigned_province' => 'Laguna', 'assigned_province_code' => '043400000'])->save();
        $brgy = collect(app(PsgcDirectory::class)->barangaysByMunicipality('043412000'))->firstWhere('name', 'San Isidro')['psgc'];
        $add = fn (array $d) => $this->actingAs($this->logistics1)->from(route('sc.riders'))->post(route('sc.riders.store'), $d + ['barangay_psgc' => $brgy]);

        $add(['full_name' => 'Pedro Jose99 Garcia@#'])->assertSessionHasErrors('full_name');
        $add(['full_name' => 'P'])->assertSessionHasErrors(['full_name' => 'Full name must be at least 2 characters.']);
        $add(['full_name' => str_repeat('A', 51)])->assertSessionHasErrors('full_name');
        $add(['full_name' => "Ramon Dela Cruz-O'Neil", 'phone' => '09451230001'])->assertSessionHasNoErrors();
        // The same number again (a double-tapped Save, or another rider): refused, one rider only.
        $add(['full_name' => "Ramon Dela Cruz-O'Neil", 'phone' => '09451230001'])->assertSessionHasErrors(['phone' => 'This mobile number is already in use by another account.']);
        $add(['full_name' => 'Other Rider', 'phone' => '+639451230001'])->assertSessionHasErrors('phone');
        $this->assertSame(1, Rider::where('phone', '09451230001')->count());
    }

    public function test_courier_sign_up_refuses_a_plate_or_number_used_by_a_rider(): void
    {
        $form = fn (array $over) => array_merge([
            'role' => 'courier', 'first_name' => 'Ernesto', 'last_name' => 'Villareal', 'email' => 'ernesto.villareal@example.com', 'contact_no' => '09276543210',
            'birthday' => '1990-07-15', 'password' => 'Kurier@2026', 'password_confirmation' => 'Kurier@2026', 'terms' => '1', 'sex' => 'male',
            'region' => 'NCR', 'municipality' => 'Quezon City', 'municipality_code' => '137404000', 'barangay' => 'Central', 'house_number' => '8', 'street' => 'Malakas St.',
            'vehicle_type' => 'truck', 'plate_number' => 'NAB4521', 'logistics_id' => $this->logistics1->id,
            'or_cr_image' => UploadedFile::fake()->image('license.jpg', 800, 500), 'vehicle_reg_image' => UploadedFile::fake()->create('or_cr.pdf', 300, 'application/pdf'),
        ], $over);
        $this->logistics1->forceFill(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => '137404000'])->save();

        $this->from(route('register'))->post(route('register'), $form(['plate_number' => '123 abc']))   // Pedro's plate, typed differently
            ->assertSessionHasErrors(['plate_number' => 'Plate number 123 ABC is already registered to another courier or rider.']);
        $this->from(route('register'))->post(route('register'), $form(['contact_no' => '09170000011']))   // Pedro's rider number
            ->assertSessionHasErrors(['contact_no' => 'This mobile number is already in use by another account.']);
        $this->from(route('register'))->post(route('register'), $form([]))->assertSessionHasNoErrors();
    }
}
