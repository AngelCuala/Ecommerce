<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sellers sign up on the main /register form ("Seller" role chip) instead of a separate page.
 */
class UnifiedSellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        Category::create(['name' => 'Home & Garden']);
    }

    /** The fields exactly as the /register form sends them for a seller. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role'             => 'seller',
            'first_name'       => 'Maria Clara',
            'last_name'        => 'Santos',
            'email'            => 'mariaclara.santos@example.com',
            'sex'              => 'female',
            'contact_no'       => '09189876543',
            'birthday'         => '1995-05-20',
            'region'           => 'National Capital Region (NCR)',
            'province'         => 'Metro Manila',
            'municipality'     => 'Quezon City',
            'barangay'         => 'Commonwealth',
            'house_number'     => 'Blk 12 Lot 8',
            'street'           => 'Sampaguita St.',
            'zip_code'         => '1121',
            'shop_name'        => 'Maria Clara Handicrafts',
            'line_of_business' => 'Home & Garden',
            'seller_type'      => 'Sole Proprietorship',
            'tin'              => '123-456-789-000',
            'government_id'    => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_certificate'  => UploadedFile::fake()->create('dti_certificate.pdf', 850, 'application/pdf'),
            'bir_2303'         => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'password'         => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2026',
            'terms'            => '1',
        ], $overrides);
    }

    public function test_old_seller_sign_up_page_opens_the_main_form_with_seller_selected(): void
    {
        $this->get(route('seller.register'))->assertRedirect(route('register', ['role' => 'seller']));

        $this->get(route('register', ['role' => 'seller']))
            ->assertOk()
            ->assertSee('name="shop_name"', false)
            ->assertSee('name="bir_2303"', false)
            ->assertSee('Home &amp; Garden', false);
    }

    public function test_seller_signs_up_from_the_main_form_and_gets_an_otp(): void
    {
        $this->from(route('register'))->post(route('register'), $this->payload())
            ->assertRedirect(route('otp.show'));

        $user = User::where('email', 'mariaclara.santos@example.com')->firstOrFail();
        $this->assertSame('seller', $user->role);
        $this->assertSame('unverified', $user->approval_status);
        $this->assertSame('09189876543', $user->contact_no);

        $app = SellerApplication::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('unverified', $app->status);
        $this->assertSame('Maria Clara Handicrafts', $app->shop_name);
        $this->assertSame('09189876543', $app->phone);
        $this->assertSame('Blk 12 Lot 8 Sampaguita St.', $app->street);

        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('mariaclara.santos@example.com'));
    }

    public function test_seller_errors_return_to_the_main_form_with_seller_still_selected(): void
    {
        $this->from(route('register'))->post(route('register'), $this->payload(['contact_no' => '123', 'tin' => '']))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['phone', 'tin']);

        $this->assertDatabaseMissing('users', ['email' => 'mariaclara.santos@example.com']);

        // The form re-opens on the Seller role with the typed values kept.
        $this->get(route('register'))->assertOk()->assertSee('"seller"', false);
    }
}
