<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A logistics company's sign-up confirms its email with a 6-digit code, like every other sign-up. */
class LogisticsSignupOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
    }

    private function form(string $email): array
    {
        return [
            'role' => 'logistics', 'last_name' => 'Cruz', 'first_name' => 'Maria', 'middle_initial' => 'S',
            'sex' => 'Female', 'email' => $email, 'contact_no' => '09171234567',
            'birthday' => '1990-05-15', 'password' => 'Hatid@2026x', 'password_confirmation' => 'Hatid@2026x',
            'business_name' => 'Luisiana Sorting Center',
            'region' => 'Region IV-A (CALABARZON)', 'region_psgc' => '0400000000', 'region_code' => '040000000',
            'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Luisiana', 'municipality_code' => '043412000',
            'barangay' => 'San Antonio', 'barangay_psgc' => '0403412010', 'street' => 'Rizal St', 'house_number' => '12', 'zip_code' => '4032',
            'government_id'   => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('dti.pdf', 50, 'application/pdf'),
            'terms' => '1',
        ];
    }

    private function codeSentTo(string $email): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use ($email, &$code) {
            if ($m->user->email !== $email) {
                return false;
            }
            $code = $m->code;
            return true;
        });
        return $code;
    }

    private function adminSees(string $business): bool
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin' . uniqid() . '@e.com', 'password' => bcrypt('x'), 'role' => 'admin', 'approval_status' => 'approved']);
        return str_contains($this->actingAs($admin)->get(route('admin.sc-applications.index'))->getContent(), $business);
    }

    public function test_web_sign_up_needs_the_email_code_before_the_admin_sees_it(): void
    {
        $this->from(route('register'))->post(route('register'), $this->form('hub.web@example.com'))
            ->assertSessionHasNoErrors()->assertRedirect(route('otp.show'));

        $user = User::where('email', 'hub.web@example.com')->firstOrFail();
        $this->assertSame('unverified', $user->approval_status);
        $code = $this->codeSentTo('hub.web@example.com');
        $this->assertSame(6, strlen($code));
        $this->assertFalse($this->adminSees('Luisiana Sorting Center'));

        // Logging in before the code: the code is asked again.
        auth()->logout();
        $this->post(route('login'), ['email' => 'hub.web@example.com', 'password' => 'Hatid@2026x'])->assertRedirect(route('otp.show'));

        $this->post(route('otp.verify'), ['otp' => $code])->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertSame('pending', $user->fresh()->approval_status);
        $this->assertSame('pending', SortingCenterApplication::where('user_id', $user->id)->value('status'));
        $this->assertTrue($this->adminSees('Luisiana Sorting Center'));
    }

    public function test_app_sign_up_gets_the_code_step_too(): void
    {
        $res = $this->post('/api/v1/auth/register', $this->form('hub.app@example.com'), ['Accept' => 'application/json'])->assertStatus(202)
            ->assertJsonPath('status', 'otp_required');
        $code = $this->codeSentTo('hub.app@example.com');

        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $res->json('otp_token'), 'otp' => '000000'])->assertStatus(422);
        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $res->json('otp_token'), 'otp' => $code])->assertOk()
            ->assertJsonPath('status', 'pending_approval');
        $this->assertSame('pending', User::where('email', 'hub.app@example.com')->value('approval_status'));
    }
}
