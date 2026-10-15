<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every account type on the /register wizard ends up as an account the rest of the
 * app understands, and can get in once it is approved.
 */
class RegistrationRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Mail::fake();
    }

    private function base(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mario', 'last_name' => 'Santos', 'email' => 'mario@example.com',
            'sex' => 'male', 'contact_no' => '09181234568', 'birthday' => '1995-05-20',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '0403400000',
            'municipality' => 'Magdalena', 'municipality_code' => '0403415000', 'barangay' => 'Poblacion',
            'house_number' => '12', 'street' => 'Rizal St', 'zip_code' => '4007',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    public function test_buyer_zip_code_is_saved(): void
    {
        $this->post(route('register'), $this->base([
            'role' => 'buyer', 'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ]))->assertRedirect(route('otp.show'));

        $this->assertSame('4007', User::where('email', 'mario@example.com')->value('zip'));
    }

    public function test_courier_waits_on_status_page_then_gets_in_after_approval(): void
    {
        $this->post(route('register'), $this->base([
            'role' => 'courier', 'vehicle_type' => 'motorcycle', 'plate_number' => 'ABC 1234',
            'logistics_id' => User::create([
                'name' => 'Hub', 'email' => 'hub@alvy.test', 'password' => Hash::make('Secret@123'),
                'role' => 'sorting_center', 'approval_status' => 'approved',
            ])->id,
            'or_cr_image'       => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
            'vehicle_reg_image' => UploadedFile::fake()->create('orcr.pdf', 100, 'application/pdf'),
        ]))->assertRedirect(route('otp.show'));

        $user = User::where('email', 'mario@example.com')->firstOrFail();
        $this->assertSame('courier_pending', $user->role);

        // Email confirmed → waiting for the Logistics team: the status page, not a dead end.
        $user->forceFill(['approval_status' => 'pending', 'email_verified_at' => now()])->save();
        $this->post('/login', ['email' => 'mario@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('courier.status'));
        auth()->logout();

        $this->actingAs($this->admin())->patch(route('admin.couriers.approve', Courier::firstOrFail()));
        $user->refresh();
        $this->assertSame('courier', $user->role);
        $this->assertSame('approved', $user->approval_status);
        auth()->logout();

        $this->post('/login', ['email' => 'mario@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('courier.dashboard'));
    }

    public function test_rejected_courier_sign_up_still_sees_its_status_page(): void
    {
        $user = User::create([
            'name' => 'Mario Santos', 'email' => 'mario@example.com', 'password' => Hash::make('secret123'),
            'role' => 'courier_pending', 'approval_status' => 'pending', 'email_verified_at' => now(),
        ]);
        $courier = Courier::create([
            'user_id' => $user->id, 'last_name' => 'Santos', 'first_name' => 'Mario', 'sex' => 'Male',
            'contact_no' => '09181234568', 'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Laguna',
            'municipality' => 'Magdalena', 'barangay' => 'Poblacion', 'vehicle_type' => 'Motorcycle',
            'plate_number' => 'ABC 1234', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'pending',
        ]);

        $this->actingAs($this->admin())->patch(route('admin.couriers.reject', $courier), ['rejection_reason' => 'Blurry ID']);
        $this->assertSame('courier_pending', $user->fresh()->role);
        auth()->logout();

        $this->post('/login', ['email' => 'mario@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('courier.status'));
    }

    public function test_logistics_signs_up_as_a_sorting_center_application(): void
    {
        $this->post(route('register'), $this->base([
            'role' => 'logistics', 'business_name' => 'ALVY Hub Magdalena',
            'government_id'   => UploadedFile::fake()->create('gov.jpg', 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('sc.login'));

        $user = User::where('email', 'mario@example.com')->firstOrFail();
        $this->assertSame('sorting_center_pending', $user->role);
        $this->assertSame('Male', $user->sex);

        $app = SortingCenterApplication::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $app->status);
        $this->assertSame('ALVY Hub Magdalena', $app->business_name);
        $this->assertSame('043415000', $app->municipality_code);   // stored as the 9-digit correspondence code
        $this->assertSame('4007', $app->zip_code);
    }

    public function test_logistics_needs_its_documents(): void
    {
        $this->from(route('register'))->post(route('register'), $this->base(['role' => 'logistics', 'business_name' => 'Hub']))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['government_id', 'business_permit']);
    }

    public function test_old_sorting_center_sign_up_page_opens_the_wizard(): void
    {
        $this->get(route('sc.register'))->assertRedirect(route('register', ['role' => 'logistics']));
    }

    public function test_signed_in_users_cannot_post_a_new_registration(): void
    {
        $this->actingAs($this->admin())
            ->post(route('register'), $this->base(['role' => 'buyer']))
            ->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'mario@example.com']);
    }
}
