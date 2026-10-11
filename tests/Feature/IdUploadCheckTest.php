<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\IdCheck\IdCheckResult;
use App\Services\IdCheck\IdDocumentChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uploaded IDs must really be IDs; when the check can't run, the upload is flagged instead. */
class IdUploadCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Mail::fake();
        Category::create(['name' => 'Home & Garden']);
    }

    /** Replace the real (API-calling) checker with a fixed verdict. */
    private function checkerSays(string $status, string $type = 'Driver\'s license'): void
    {
        $this->app->instance(IdDocumentChecker::class, new class($status, $type) extends IdDocumentChecker {
            public function __construct(private string $status, private string $type) {}
            public function check(UploadedFile $file): IdCheckResult
            {
                return new IdCheckResult($this->status, $this->type, 'test');
            }
        });
    }

    private function sellerSignup(array $overrides = [])
    {
        return $this->from(route('seller.register'))->post(route('seller.register.store'), array_merge([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com', 'phone' => '09189876543',
            'shop_name' => 'Maria Crafts', 'line_of_business' => 'Home & Garden', 'seller_type' => 'Sole Proprietorship',
            'tin' => '123-456-789-000', 'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
            'barangay' => 'Commonwealth', 'street' => 'Sampaguita St.', 'zip_code' => '1121',
            'government_id'   => UploadedFile::fake()->create('id.jpg', 300, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti.pdf', 300, 'application/pdf'),
            'bir_2303'        => UploadedFile::fake()->create('bir.pdf', 300, 'application/pdf'),
            'password' => 'Tindahan@2026', 'password_confirmation' => 'Tindahan@2026', 'terms' => '1',
        ], $overrides));
    }

    public function test_a_photo_that_is_not_an_id_is_rejected_and_no_account_is_created(): void
    {
        $this->checkerSays(IdCheckResult::REJECTED, 'a photo of a cat');

        $this->sellerSignup()->assertSessionHasErrors('government_id');
        $this->assertStringContainsString('a photo of a cat', session('errors')->first('government_id'));
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    public function test_a_real_id_is_accepted_and_the_result_is_saved(): void
    {
        $this->checkerSays(IdCheckResult::PASSED, 'PhilSys National ID');

        $this->sellerSignup()->assertRedirect(route('otp.show'));
        $app = SellerApplication::first();
        $this->assertSame('passed', $app->id_check_status);
        $this->assertSame('PhilSys National ID', $app->id_check_note);
    }

    public function test_when_the_check_cannot_run_the_upload_is_accepted_and_flagged(): void
    {
        // phpunit.xml turns the check off, so the real checker reports "unchecked".
        $this->sellerSignup()->assertRedirect(route('otp.show'));
        $this->assertSame('unchecked', SellerApplication::first()->id_check_status);
    }

    public function test_the_check_only_runs_after_the_file_passes_basic_validation(): void
    {
        $this->checkerSays(IdCheckResult::REJECTED);

        $this->sellerSignup(['government_id' => UploadedFile::fake()->create('id.docx', 10, 'application/msword')])
            ->assertSessionHasErrors(['government_id' => 'The government-issued ID must be a JPG, PNG or PDF file.']);
    }

    public function test_buyer_id_on_create_account_is_checked_too(): void
    {
        $this->checkerSays(IdCheckResult::REJECTED, 'a screenshot');

        $this->from('/register')->post('/register', [
            'role' => 'buyer', 'first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@gmail.com',
            'password' => 'secret123', 'sex' => 'male', 'contact_no' => '09171234567', 'birthday' => '2000-01-15',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'house_number' => '1', 'street' => 'Rizal', 'terms' => '1',
            'valid_id' => UploadedFile::fake()->create('id.png', 100, 'image/png'),
        ])->assertSessionHasErrors('valid_id');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_checker_without_api_key_reports_unchecked_without_calling_out(): void
    {
        config(['services.id_check.enabled' => true, 'services.id_check.api_key' => null]);

        $result = (new IdDocumentChecker())->check(UploadedFile::fake()->create('id.jpg', 10, 'image/jpeg'));
        $this->assertSame(IdCheckResult::UNCHECKED, $result->status);
    }
}
