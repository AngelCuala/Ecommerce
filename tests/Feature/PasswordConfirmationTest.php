<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SellerApplicationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Every sign-up form asks for the password twice; the server checks they match and stores only a hash. */
class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function buyer(array $over = []): array
    {
        return array_merge([
            'role' => 'buyer', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@gmail.com',
            'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'sex' => 'male', 'contact_no' => '09171234567',
            'birthday' => '2000-01-15', 'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000',
            'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'house_number' => '12B', 'street' => 'Rizal Street', 'terms' => '1',
        ], $over);
    }

    private function sc(array $over = []): array
    {
        return array_merge([
            'last_name' => 'Cruz', 'first_name' => 'Maria', 'middle_initial' => 'S', 'sex' => 'Female', 'email' => 'hub@example.com',
            'contact_no' => '09171234567', 'birthday' => '1990-05-15', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'business_name' => 'Luisiana Sorting Center', 'region' => 'Region IV-A (CALABARZON)', 'region_psgc' => '0400000000', 'region_code' => '040000000',
            'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Luisiana', 'municipality_code' => '043412000',
            'barangay' => 'San Antonio', 'barangay_psgc' => '0403412010', 'street' => 'Rizal St', 'house_number' => '12', 'zip_code' => '4032',
            'government_id' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('dti.pdf', 50, 'application/pdf'), 'terms' => '1',
        ], $over);
    }

    public function test_buyer_sign_up_needs_a_matching_confirmation(): void
    {
        $this->from('/register')->post('/register', $this->buyer(['password_confirmation' => 'Secret@124']))
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);
        $this->from('/register')->post('/register', $this->buyer(['password_confirmation' => null]))
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);   // still refused
        $this->assertSame(0, User::count());

        $this->from('/register')->post('/register', $this->buyer())->assertSessionHasNoErrors();
        $user = User::where('email', 'juan@gmail.com')->first();
        $this->assertNotSame('Secret@123', $user->password);
        $this->assertTrue(Hash::check('Secret@123', $user->password));
    }

    public function test_passwords_are_never_sent_back_into_the_form(): void
    {
        $this->from('/register')->post('/register', $this->buyer(['password_confirmation' => 'other-pass', 'first_name' => 'J']));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->assertSame('J', session()->getOldInput('first_name'));      // other fields are kept

        $html = $this->get('/register')->getContent();
        $this->assertMatchesRegularExpression('/<input type="password" name="password_confirmation" id="passwordConfirmation"[^>]*required/', $html);
        $this->assertStringContainsString('togglePasswordConfirmation', $html);
        $this->assertStringNotContainsString("getElementById('passwordConfirmation').value = document.getElementById('password').value", $html);
    }

    public function test_sorting_center_sign_up_needs_a_matching_confirmation(): void
    {
        $this->from(route('sc.register'))->post(route('sc.register.store'), $this->sc(['password_confirmation' => 'nope-nope-1']))
            ->assertSessionHasErrors(['password' => 'The passwords do not match. Please type the same password in both fields.']);
        $this->assertSame(0, User::count());

        // The sign-up page for sorting centers is the shared form (it has the confirm field and its own eye toggle).
        $html = $this->get('/register?role=logistics')->assertOk()->getContent();
        $this->assertStringContainsString('id="togglePasswordConfirmation"', $html);
    }

    public function test_seller_sign_up_rule_requires_confirmation(): void
    {
        $this->assertContains('confirmed', SellerApplicationRules::accountRules()['password']);
        $html = $this->get('/register?role=seller')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input type="password" name="password_confirmation"[^>]*required/', $html);
    }
}
