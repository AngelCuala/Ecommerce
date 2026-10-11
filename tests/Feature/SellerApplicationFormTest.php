<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Become a Seller" application: the fields asked for and how they're validated. */
class SellerApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Category::create(['name' => 'Home & Garden']);
        Category::create(['name' => 'Pet Supplies']);

        $this->buyer = User::create([
            'name' => 'Ana Reyes', 'first_name' => 'Ana', 'last_name' => 'Reyes',
            'email' => 'ana@gmail.com', 'password' => Hash::make('secret123'),
            'role' => 'buyer', 'contact_no' => '09170000001',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'       => 'Ana',
            'last_name'        => 'Reyes',
            'phone'            => '09189876543',
            'shop_name'        => 'Ana Handicrafts',
            'line_of_business' => 'Home & Garden',
            'seller_type'      => 'Sole Proprietorship',
            'tin'              => '123-456-789-000',
            'region'           => 'National Capital Region (NCR)',
            'province'         => 'Metro Manila',
            'municipality'     => 'Quezon City',
            'barangay'         => 'Commonwealth',
            'street'           => 'Blk 12 Lot 8, Sampaguita St.',
            'zip_code'         => '1121',
            'government_id'    => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_certificate'  => UploadedFile::fake()->create('dti_certificate.pdf', 850, 'application/pdf'),
            'bir_2303'         => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'password'         => 'secret123',
            'password_confirmation' => 'secret123',
            'terms'            => '1',
        ], $overrides);
    }

    private function apply(array $overrides = [])
    {
        return $this->actingAs($this->buyer)->from(route('seller.apply'))
            ->post(route('seller.apply.store'), $this->payload($overrides));
    }

    public function test_form_shows_every_requested_field(): void
    {
        $html = $this->actingAs($this->buyer)->get(route('seller.apply'))->assertOk()->getContent();

        foreach (['first_name', 'last_name', 'phone', 'shop_name', 'line_of_business', 'seller_type', 'tin',
                  'region', 'province', 'municipality', 'barangay', 'street', 'zip_code',
                  'government_id', 'dti_certificate', 'bir_2303', 'password', 'password_confirmation', 'terms'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $html, "Missing field {$name}");
        }
        foreach (['Email', 'Mobile Number', 'Shop Category', 'Seller Type', 'TIN', 'Postal Code', 'Street Address',
                  'Government-issued ID', 'DTI Certificate', 'BIR Form 2303', 'Confirm Password'] as $label) {
            $this->assertStringContainsString($label, $html, "Missing label {$label}");
        }
        $this->assertStringContainsString('ana@gmail.com', $html);
        $this->assertStringContainsString('Sole Proprietorship', $html);
        $this->assertStringContainsString('Home &amp; Garden', $html);
    }

    public function test_valid_application_is_saved_with_all_details_and_documents(): void
    {
        $this->apply(['tin' => '123456789000'])->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();

        $app = SellerApplication::where('user_id', $this->buyer->id)->first();
        $this->assertNotNull($app);
        $this->assertSame('pending', $app->status);
        $this->assertSame('Ana Reyes', $app->full_name);
        $this->assertSame('09189876543', $app->phone);
        $this->assertSame('Ana Handicrafts', $app->shop_name);
        $this->assertSame('Home & Garden', $app->line_of_business);
        $this->assertSame('Sole Proprietorship', $app->seller_type);
        $this->assertSame('123-456-789-000', $app->tin, 'TIN is stored in dashed format.');
        $this->assertSame('National Capital Region (NCR)', $app->region);
        $this->assertSame('Quezon City', $app->municipality);
        $this->assertSame('1121', $app->zip_code);
        Storage::disk('public')->assertExists([$app->government_id_path, $app->dti_certificate_path, $app->bir_2303_path]);
    }

    public function test_wrong_account_password_is_rejected(): void
    {
        $this->apply(['password' => 'wrongpass1', 'password_confirmation' => 'wrongpass1'])
            ->assertSessionHasErrors(['password' => 'The password is incorrect. Enter your ALVY account password.']);
        $this->assertDatabaseCount('seller_applications', 0);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->apply(['password_confirmation' => 'secret124'])
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);
    }

    public function test_invalid_tin_is_rejected(): void
    {
        foreach (['12345', '123-456-78', '123-456-789-0', 'ABC-DEF-GHI', '1234567890123'] as $bad) {
            $this->apply(['tin' => $bad])->assertSessionHasErrors('tin');
        }
        $this->assertDatabaseCount('seller_applications', 0);
    }

    public function test_invalid_mobile_number_and_postal_code_are_rejected(): void
    {
        $this->apply(['phone' => '0918987654'])->assertSessionHasErrors('phone');
        $this->apply(['zip_code' => '112'])->assertSessionHasErrors(['zip_code' => 'Please enter a 4-digit postal code.']);
        $this->apply(['zip_code' => '11A1'])->assertSessionHasErrors('zip_code');
    }

    public function test_names_with_numbers_or_symbols_are_rejected(): void
    {
        $this->apply(['first_name' => 'Ana2'])->assertSessionHasErrors('first_name');
        $this->apply(['last_name' => 'R@yes'])->assertSessionHasErrors('last_name');
    }

    public function test_category_and_seller_type_must_come_from_the_list(): void
    {
        $this->apply(['line_of_business' => 'Weapons'])->assertSessionHasErrors('line_of_business');
        $this->apply(['seller_type' => 'Freelancer'])->assertSessionHasErrors('seller_type');
    }

    public function test_all_three_documents_are_required_and_must_be_images_or_pdf(): void
    {
        $this->apply(['government_id' => null, 'dti_certificate' => null, 'bir_2303' => null])
            ->assertSessionHasErrors(['government_id', 'dti_certificate', 'bir_2303']);
        $this->apply(['bir_2303' => UploadedFile::fake()->create('bir.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertSessionHasErrors('bir_2303');
        $this->apply(['dti_certificate' => UploadedFile::fake()->create('dti.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors('dti_certificate');
        $this->assertDatabaseCount('seller_applications', 0);
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->apply(['terms' => null])->assertSessionHasErrors('terms');
    }

    public function test_cannot_apply_twice_while_pending(): void
    {
        $this->apply()->assertSessionHasNoErrors();
        $this->apply()->assertSessionHas('error');
        $this->assertDatabaseCount('seller_applications', 1);
    }

    public function test_admin_sees_the_new_details_and_documents(): void
    {
        $this->apply()->assertSessionHasNoErrors();
        $app = SellerApplication::first();

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('secret123'),
            'role' => 'admin', 'approval_status' => 'approved',
        ]);

        $this->actingAs($admin)->get(route('admin.seller-applications.show', $app))
            ->assertOk()
            ->assertSee('Sole Proprietorship')
            ->assertSee('123-456-789-000')
            ->assertSee('1121')
            ->assertSee('DTI Certificate')
            ->assertSee('BIR Form 2303');
    }
}
