<?php

namespace Tests\Feature;

use App\Mail\CourierApprovedMail;
use App\Mail\CourierRejectedMail;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A courier applies to the sorting center of their choice, and that sorting center
 * (not any other) sees the application with its documents and approves or rejects it.
 */
class ScCourierApplicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Mail::fake();
    }

    private function hub(string $name, string $status = 'approved'): User
    {
        return User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)) . '@hub.test',
            'password' => Hash::make('Secret@123'), 'role' => 'sorting_center', 'approval_status' => $status,
            'municipality' => 'Magdalena', 'province' => 'Laguna',
        ]);
    }

    private function signUp(array $overrides = [])
    {
        return $this->from(route('register'))->post(route('register'), array_merge([
            'role' => 'courier', 'first_name' => 'Mario', 'last_name' => 'Santos', 'email' => 'mario@example.com',
            'sex' => 'male', 'contact_no' => '09181234568', 'birthday' => '1995-05-20',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'house_number' => '12', 'street' => 'Rizal St', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'vehicle_type' => 'motorcycle', 'plate_number' => 'ABC 1234', 'terms' => '1',
            'or_cr_image'       => UploadedFile::fake()->image('license.jpg'),
            'vehicle_reg_image' => UploadedFile::fake()->create('orcr.pdf', 100, 'application/pdf'),
        ], $overrides));
    }

    public function test_registration_lists_hubs_and_saves_the_chosen_one(): void
    {
        $hub = $this->hub('Hub Alpha');
        $this->hub('Hub Pending', 'pending');

        $this->get(route('register', ['role' => 'courier']))->assertOk()
            ->assertSee('Hub Alpha')->assertDontSee('Hub Pending')
            ->assertSee('name="logistics_id" value="' . $hub->id . '"', false);

        $this->signUp(['logistics_id' => $hub->id])->assertSessionHasNoErrors()->assertRedirect(route('otp.show'));
        $this->assertSame($hub->id, Courier::firstOrFail()->sorting_center_id);
    }

    public function test_a_sorting_center_must_be_chosen_and_must_be_approved(): void
    {
        $pending = $this->hub('Hub Pending', 'pending');

        $this->signUp()->assertSessionHasErrors('logistics_id');
        $this->signUp(['logistics_id' => $pending->id])->assertSessionHasErrors('logistics_id');
        $this->assertSame(0, Courier::count());
    }

    public function test_hub_names_are_escaped_on_the_form(): void
    {
        $this->hub('<script>alert(1)</script>');

        $this->get(route('register', ['role' => 'courier']))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_only_the_chosen_sorting_center_sees_and_reviews_the_application(): void
    {
        $alpha = $this->hub('Hub Alpha');
        $beta  = $this->hub('Hub Beta');
        $this->signUp(['logistics_id' => $alpha->id]);
        $courier = Courier::firstOrFail();

        $this->actingAs($alpha)->get(route('sc.courier-applications.index'))->assertOk()
            ->assertSee('Mario Santos')->assertSee('ABC 1234');
        $this->actingAs($alpha)->get(route('sc.courier-applications.show', $courier))->assertOk()
            ->assertSee('09181234568')->assertSee('Rizal St')
            ->assertSee(route('sc.courier-applications.document', [$courier, 'license']), false);
        $this->actingAs($alpha)->get(route('sc.courier-applications.document', [$courier, 'license']))->assertOk();
        $this->actingAs($alpha)->get(route('sc.courier-applications.document', [$courier, 'or-cr']))->assertOk();

        $this->actingAs($beta)->get(route('sc.courier-applications.index'))->assertOk()->assertDontSee('Mario Santos');
        $this->actingAs($beta)->get(route('sc.courier-applications.show', $courier))->assertNotFound();
        $this->actingAs($beta)->get(route('sc.courier-applications.document', [$courier, 'license']))->assertNotFound();
        $this->actingAs($beta)->post(route('sc.courier-applications.approve', $courier))->assertNotFound();
        $this->assertTrue($courier->fresh()->isPending());
    }

    public function test_sorting_center_approves_and_the_courier_can_work(): void
    {
        $hub = $this->hub('Hub Alpha');
        $this->signUp(['logistics_id' => $hub->id]);
        $courier = Courier::firstOrFail();

        $this->actingAs($hub)->post(route('sc.courier-applications.approve', $courier))
            ->assertRedirect(route('sc.courier-applications.index'));

        $courier->refresh();
        $this->assertTrue($courier->isApproved());
        $this->assertSame($hub->id, $courier->reviewed_by);
        $this->assertSame('courier', $courier->user->role);
        $this->assertSame('approved', $courier->user->approval_status);
        Mail::assertSent(CourierApprovedMail::class);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $courier->user_id, 'title' => 'Courier application approved']);
    }

    public function test_sorting_center_rejects_with_a_reason(): void
    {
        $hub = $this->hub('Hub Alpha');
        $this->signUp(['logistics_id' => $hub->id]);
        $courier = Courier::firstOrFail();

        $this->actingAs($hub)->post(route('sc.courier-applications.reject', $courier))
            ->assertSessionHasErrors('rejection_reason');
        $this->assertTrue($courier->fresh()->isPending());

        $this->actingAs($hub)->post(route('sc.courier-applications.reject', $courier), ['rejection_reason' => 'Blurry license'])
            ->assertRedirect(route('sc.courier-applications.index'));
        $courier->refresh();
        $this->assertTrue($courier->isRejected());
        $this->assertSame('Blurry license', $courier->rejection_reason);
        Mail::assertSent(CourierRejectedMail::class);

        // Already decided: no second decision.
        $this->actingAs($hub)->post(route('sc.courier-applications.approve', $courier))->assertSessionHas('error');
        $this->assertTrue($courier->fresh()->isRejected());
    }

    public function test_courier_is_told_to_wait_for_the_sorting_center_they_applied_to(): void
    {
        $hub = $this->hub('Hub Alpha');
        $this->signUp(['logistics_id' => $hub->id]);
        $user = User::where('email', 'mario@example.com')->firstOrFail();

        // After entering the email code.
        $code = null;
        Mail::assertSent(\App\Mail\LoginOtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });
        $this->post(route('otp.verify'), ['otp' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'waiting for approval from Hub Alpha'));

        // Signing in while still waiting.
        $this->post('/login', ['email' => 'mario@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('courier.status'))
            ->assertSessionHas('info', 'Your courier application is still waiting for approval from Hub Alpha.');

        $this->actingAs($user)->get(route('courier.status'))->assertOk()
            ->assertSee('Waiting for Hub Alpha')
            ->assertSee('the sorting center you applied to');
    }

    public function test_become_a_courier_also_goes_to_the_chosen_sorting_center(): void
    {
        $hub   = $this->hub('Hub Alpha');
        $buyer = User::create([
            'name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => Hash::make('secret123'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
        $payload = [
            'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'Female', 'contact_no' => '09170000000',
            'birthday' => '1998-01-01', 'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Truck', 'plate_number' => 'XYZ 987',
            'or_cr'     => UploadedFile::fake()->create('orcr.pdf', 100, 'application/pdf'),
            'id_upload' => UploadedFile::fake()->image('id.jpg'),
        ];

        $this->actingAs($buyer)->get(route('courier.register'))->assertOk()->assertSee('Hub Alpha');
        $this->actingAs($buyer)->post(route('courier.register.store'), $payload)->assertSessionHasErrors('sorting_center_id');

        $this->actingAs($buyer)->post(route('courier.register.store'), $payload + ['sorting_center_id' => $hub->id])
            ->assertRedirect(route('courier.status'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'approval from Hub Alpha'));
        $this->assertSame($hub->id, Courier::where('user_id', $buyer->id)->value('sorting_center_id'));
    }
}
