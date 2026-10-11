<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Mail\SellerApprovedMail;
use App\Mail\SellerRejectedMail;
use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Seller sign-up: field rules, email OTP (Unverified → Pending Approval),
 * admin approve / reject / resubmit, and role-based access.
 */
class SellerSignupTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED = [
        'first_name', 'last_name', 'email', 'phone', 'shop_name', 'line_of_business', 'seller_type', 'tin',
        'region', 'province', 'municipality', 'barangay', 'street', 'zip_code',
        'government_id', 'dti_certificate', 'bir_2303', 'password', 'terms',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        Category::create(['name' => 'Home & Garden']);
        Category::create(['name' => 'Pet Supplies']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'       => 'Maria Clara',
            'last_name'        => 'Santos',
            'email'            => 'mariaclara.santos@example.com',
            'phone'            => '09189876543',
            'shop_name'        => 'Maria Clara Handicrafts',
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
            'password'         => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2026',
            'terms'            => '1',
        ], $overrides);
    }

    private function signUp(array $overrides = [])
    {
        return $this->from(route('seller.register'))->post(route('seller.register.store'), $this->payload($overrides));
    }

    private function existingUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Existing User', 'email' => 'taken@example.com', 'password' => Hash::make('Secret@123'),
            'role' => 'buyer', 'contact_no' => '09170000000', 'approval_status' => 'approved',
            'email_verified_at' => now(),
        ], $attrs));
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        return $code;
    }

    /** Full sign-up + OTP verification → pending seller (logged out afterwards). */
    private function pendingSeller(): User
    {
        $this->signUp()->assertRedirect(route('otp.show'));
        $this->post(route('otp.verify'), ['otp' => $this->sentCode()]);
        $this->post(route('logout'));
        return User::where('email', 'mariaclara.santos@example.com')->firstOrFail();
    }

    private function admin(): User
    {
        return User::firstWhere('email', 'admin@alvy.test') ?? User::create(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function login(string $email = 'mariaclara.santos@example.com', string $password = 'Tindahan@2026')
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    // ── Required fields ─────────────────────────────────────

    public function test_blank_form_shows_a_required_message_for_every_mandatory_field(): void
    {
        $blank = array_fill_keys(array_keys($this->payload()), '');
        $this->from(route('seller.register'))->post(route('seller.register.store'), $blank)
            ->assertSessionHasErrors(self::REQUIRED);

        $errors = session('errors')->getBag('default');
        $this->assertSame('The first name field is required.', $errors->first('first_name'));
        $this->assertSame('The DTI Certificate is required.', $errors->first('dti_certificate'));
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    public function test_each_blank_field_is_flagged_alone_and_other_values_are_kept(): void
    {
        foreach (self::REQUIRED as $field) {
            $this->signUp([$field => ''])
                ->assertSessionHasErrors([$field])
                ->assertSessionDoesntHaveErrors(array_values(array_diff(self::REQUIRED, [$field, 'password'])));

            if ($field !== 'shop_name') {
                $this->assertSame('Maria Clara Handicrafts', session()->getOldInput('shop_name'), "Kept values when {$field} is blank");
            }
            $this->assertNull(session()->getOldInput('password'), 'Passwords are never kept');
            $this->assertNull(session()->getOldInput('password_confirmation'));
        }
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    // ── Names ───────────────────────────────────────────────

    public function test_names_with_numbers_or_symbols_are_rejected_on_each_name_field(): void
    {
        $this->signUp(['first_name' => 'Maria2', 'last_name' => 'S@ntos'])->assertSessionHasErrors([
            'first_name' => 'First name may only contain letters (and spaces between words).',
            'last_name'  => 'Last name may only contain letters (and spaces between words).',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_names_are_saved_exactly_as_typed_including_enye(): void
    {
        $this->signUp(['first_name' => 'Niña Joy', 'last_name' => 'Peñaflorida'])->assertSessionHasNoErrors();

        $user = User::where('email', 'mariaclara.santos@example.com')->first();
        $this->assertSame('Niña Joy', $user->first_name);
        $this->assertSame('Peñaflorida', $user->last_name);
        $this->assertSame('Niña Joy', $user->sellerApplication->first_name);
    }

    public function test_name_length_1_and_51_rejected_2_and_50_accepted(): void
    {
        $this->signUp(['first_name' => 'M'])->assertSessionHasErrors(['first_name' => 'First name must be at least 2 characters.']);
        $this->signUp(['last_name' => str_repeat('a', 51)])->assertSessionHasErrors(['last_name' => 'Last name must not be longer than 50 characters.']);
        $this->signUp(['first_name' => 'Mo', 'last_name' => str_repeat('a', 50), 'email' => 'x'])
            ->assertSessionDoesntHaveErrors(['first_name', 'last_name']);
    }

    // ── Email ───────────────────────────────────────────────

    public function test_invalid_emails_are_rejected(): void
    {
        foreach (['seller', 'seller@', '@example.com', 'seller@example', 'seller@@example.com', 'seller#example.com'] as $bad) {
            $this->signUp(['email' => $bad])
                ->assertSessionHasErrors(['email' => 'Please enter a valid email address (e.g. juan@gmail.com).']);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_already_in_use_blocks_registration(): void
    {
        $this->existingUser(['email' => 'mariaclara.santos@example.com']);

        $this->signUp()->assertSessionHasErrors([
            'email' => 'This email address is already in use. Please log in or use a different email.',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_in_different_case_is_a_duplicate(): void
    {
        $this->existingUser(['email' => 'mariaclara.santos@example.com']);
        $this->signUp(['email' => 'MariaClara.Santos@EXAMPLE.com'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_spaces_in_email_are_removed(): void
    {
        $this->signUp(['email' => ' seller.test01 @example.com '])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'seller.test01@example.com']);
    }

    // ── Mobile number ───────────────────────────────────────

    public function test_non_numeric_mobile_numbers_are_rejected(): void
    {
        foreach (['abcdefghijk', '0918abc6543', '0918#876543', '08189876543'] as $bad) {
            $this->signUp(['phone' => $bad])->assertSessionHasErrors([
                'phone' => 'Please enter a valid mobile number (e.g. 09171234567 or +63 917 123 4567).',
            ]);
        }
    }

    public function test_mobile_10_and_12_digits_rejected_11_accepted(): void
    {
        $this->signUp(['phone' => '0918987654'])->assertSessionHasErrors(['phone' => 'Mobile number must be exactly 11 digits (e.g. 09171234567).']);
        $this->signUp(['phone' => '091898765432'])->assertSessionHasErrors(['phone' => 'Mobile number must be exactly 11 digits (e.g. 09171234567).']);
        $this->signUp(['phone' => '09189876543', 'email' => 'x'])->assertSessionDoesntHaveErrors('phone');
    }

    public function test_mobile_number_in_use_blocks_registration(): void
    {
        $this->existingUser(['contact_no' => '09189876543']);
        $this->signUp()->assertSessionHasErrors(['phone' => 'This mobile number is already in use.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_plus_63_format_is_recognised_as_the_same_number(): void
    {
        $this->existingUser(['contact_no' => '09189876543']);
        $this->signUp(['phone' => '+63 918 987 6543'])->assertSessionHasErrors(['phone' => 'This mobile number is already in use.']);
        $this->assertDatabaseCount('users', 1);
    }

    // ── Password & terms ────────────────────────────────────

    public function test_password_7_rejected_with_min_length_8_accepted(): void
    {
        $this->signUp(['password' => 'Tin@202', 'password_confirmation' => 'Tin@202'])
            ->assertSessionHasErrors(['password' => 'Password must be at least 8 characters.']);
        $this->signUp(['password' => 'Tin@2026', 'password_confirmation' => 'Tin@2026', 'email' => 'x'])
            ->assertSessionDoesntHaveErrors('password');
    }

    public function test_weak_passwords_are_rejected_with_the_rule(): void
    {
        $rule = 'Password must have at least 8 characters, including an uppercase letter, a lowercase letter, a number and a symbol (e.g. Tindahan@2026).';
        foreach (['tindahan@2026', 'TINDAHAN@2026', 'Tindahan2026', 'Tindahan@abc', 'password'] as $weak) {
            $this->signUp(['password' => $weak, 'password_confirmation' => $weak])->assertSessionHasErrors(['password' => $rule]);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_passwords_must_match(): void
    {
        $this->signUp(['password_confirmation' => 'Tindahan@2025'])
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->signUp(['terms' => null])
            ->assertSessionHasErrors(['terms' => 'You must accept the Terms and Conditions and Privacy Policy.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_double_submit_creates_one_account_and_sends_one_otp(): void
    {
        $this->signUp()->assertRedirect(route('otp.show'));
        $this->signUp()->assertRedirect(route('otp.show'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('seller_applications', 1);
        Mail::assertSentCount(1);
    }

    // ── Shop ────────────────────────────────────────────────

    public function test_shop_name_already_taken(): void
    {
        $other = $this->existingUser();
        SellerApplication::create(['user_id' => $other->id, 'full_name' => 'X', 'shop_name' => 'Maria Clara Handicrafts',
            'phone' => '09170000000', 'address' => 'x', 'government_id_path' => 'x', 'status' => 'approved']);

        $this->signUp(['shop_name' => 'maria clara HANDICRAFTS'])
            ->assertSessionHasErrors(['shop_name' => 'This shop name is already taken. Please choose another.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_shop_name_2_and_31_rejected_3_and_30_accepted(): void
    {
        $this->signUp(['shop_name' => 'AB'])->assertSessionHasErrors(['shop_name' => 'Shop name must be at least 3 characters.']);
        $this->signUp(['shop_name' => str_repeat('a', 31)])->assertSessionHasErrors(['shop_name' => 'Shop name must not be longer than 30 characters.']);
        $this->signUp(['shop_name' => 'ABC', 'email' => 'x'])->assertSessionDoesntHaveErrors('shop_name');
        $this->signUp(['shop_name' => str_repeat('a', 30), 'email' => 'x'])->assertSessionDoesntHaveErrors('shop_name');
    }

    public function test_corporation_requires_sec_certificate(): void
    {
        $this->signUp(['seller_type' => 'Corporation', 'dti_certificate' => null])
            ->assertSessionHasErrors(['sec_certificate' => 'The SEC Certificate is required for corporations.'])
            ->assertSessionDoesntHaveErrors('dti_certificate');
        $this->assertDatabaseCount('users', 0);

        $this->signUp(['seller_type' => 'Corporation', 'dti_certificate' => null,
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 300, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertNotNull(SellerApplication::first()->sec_certificate_path);
    }

    public function test_only_12_digit_tin_is_accepted(): void
    {
        $msg = 'Please enter a valid 12-digit TIN in the format 000-000-000-000.';
        foreach (['123-456-789', '123456789', '12345678900', '1234567890001', '123-456-789-00A', '123/456/789/000'] as $bad) {
            $this->signUp(['tin' => $bad])->assertSessionHasErrors(['tin' => $msg]);
        }
        $this->signUp(['tin' => '123456789000'])->assertSessionHasNoErrors();
        $this->assertSame('123-456-789-000', SellerApplication::first()->tin);
    }

    public function test_tin_already_registered(): void
    {
        $other = $this->existingUser();
        SellerApplication::create(['user_id' => $other->id, 'full_name' => 'X', 'shop_name' => 'Other Shop', 'tin' => '123-456-789-000',
            'phone' => '09170000000', 'address' => 'x', 'government_id_path' => 'x', 'status' => 'approved']);

        $this->signUp(['tin' => '123456789000'])->assertSessionHasErrors(['tin' => 'This TIN is already registered.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_only_4_digit_postal_code_is_accepted(): void
    {
        foreach (['112', '11211', '11A1', 'abcd'] as $bad) {
            $this->signUp(['zip_code' => $bad])->assertSessionHasErrors(['zip_code' => 'Please enter a 4-digit postal code.']);
        }
        $this->signUp(['zip_code' => '1121'])->assertSessionHasNoErrors();
    }

    // ── Uploads ─────────────────────────────────────────────

    public function test_jpg_png_and_pdf_files_are_accepted_and_sign_up_continues_to_email_verification(): void
    {
        $this->signUp([
            'government_id'   => UploadedFile::fake()->create('id.png', 500, 'image/png'),
            'dti_certificate' => UploadedFile::fake()->create('dti.jpg', 500, 'image/jpeg'),
            'bir_2303'        => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
        ])->assertRedirect(route('otp.show'));

        $app = SellerApplication::first();
        Storage::disk('public')->assertExists([$app->government_id_path, $app->dti_certificate_path, $app->bir_2303_path]);
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('mariaclara.santos@example.com'));
    }

    public function test_other_file_types_are_rejected_with_the_accepted_types(): void
    {
        $this->signUp([
            'government_id'   => UploadedFile::fake()->create('id.gif', 100, 'image/gif'),
            'dti_certificate' => UploadedFile::fake()->create('dti.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'bir_2303'        => UploadedFile::fake()->create('bir.exe', 100, 'application/x-msdownload'),
        ])->assertSessionHasErrors([
            'government_id'   => 'The government-issued ID must be a JPG, PNG or PDF file.',
            'dti_certificate' => 'The DTI Certificate must be a JPG, PNG or PDF file.',
            'bir_2303'        => 'BIR Form 2303 must be a JPG, PNG or PDF file.',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    // ── Email verification (OTP) ────────────────────────────

    public function test_new_account_starts_unverified_and_verification_moves_it_to_pending_approval(): void
    {
        $this->signUp();
        $user = User::first();
        $this->assertSame('unverified', $user->approval_status);
        $this->assertSame('unverified', $user->sellerApplication->status);

        $this->post(route('otp.verify'), ['otp' => $this->sentCode()])
            ->assertRedirect(route('seller.status'))
            ->assertSessionHas('success', 'Email verified! Your seller application is now waiting for admin approval.');

        $user->refresh();
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('pending', $user->sellerApplication->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_wrong_code_fails_and_account_stays_unverified(): void
    {
        $this->signUp();
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->post(route('otp.verify'), ['otp' => $wrong])->assertSessionHasErrors(['otp' => 'The code you entered is incorrect.']);
        $this->assertSame('unverified', User::first()->approval_status);
    }

    public function test_badly_formatted_codes_fail_and_account_stays_unverified(): void
    {
        $this->signUp();
        foreach (['12345', '1234567', 'abcdef', '12 456', ''] as $bad) {
            $this->post(route('otp.verify'), ['otp' => $bad])->assertSessionHasErrors('otp');
        }
        $this->assertSame('unverified', User::first()->approval_status);
    }

    public function test_expired_code_fails_offers_resend_and_account_stays_unverified(): void
    {
        $this->signUp();
        $code = $this->sentCode();
        $this->travel(LoginOtp::TTL_MINUTES + 1)->minutes();

        $this->from(route('otp.show'))->post(route('otp.verify'), ['otp' => $code])
            ->assertSessionHasErrors(['otp' => 'This code has expired. Please request a new one.']);
        $this->get(route('otp.show'))->assertOk()->assertSee('Resend code');
        $this->assertSame('unverified', User::first()->approval_status);
    }

    public function test_resend_sends_a_new_code_old_one_rejected_new_one_accepted(): void
    {
        $this->signUp();
        $first = $this->sentCode();

        $this->travel(LoginOtp::RESEND_COOLDOWN + 1)->seconds();
        $this->post(route('otp.resend'))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
        $second = $this->sentCode();

        if ($first !== $second) {
            $this->post(route('otp.verify'), ['otp' => $first])->assertSessionHasErrors('otp');
        }
        $this->post(route('otp.verify'), ['otp' => $second])->assertRedirect(route('seller.status'));
        $this->assertSame('pending', User::first()->approval_status);
    }

    public function test_unverified_seller_cannot_get_in_and_is_sent_to_verification(): void
    {
        $this->signUp();
        $this->post(route('logout'));
        $this->flushSession();

        $this->login()->assertRedirect(route('otp.show'));
        $this->assertGuest();
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
        $this->get(route('otp.show'))->assertOk()->assertSee('Verify Your Account');
    }

    // ── Pending / approval / rejection ──────────────────────

    public function test_pending_seller_sees_under_review_and_cannot_add_products_or_see_orders(): void
    {
        $this->pendingSeller();

        $this->login()->assertRedirect(route('seller.status'));
        $this->get(route('seller.status'))->assertOk()
            ->assertSee('Pending Approval')
            ->assertSee('Your application is under review.')
            ->assertSee("you can't add products or receive orders", false);

        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.status'));
        $this->get(route('seller.books.create'))->assertRedirect(route('seller.status'));
        $this->post(route('seller.books.store'), [])->assertRedirect(route('seller.status'));
        $this->get(route('seller.orders.index'))->assertRedirect(route('seller.status'));
    }

    public function test_unverified_applications_are_not_in_the_admin_list(): void
    {
        $this->signUp();
        $this->post(route('logout'));
        $this->actingAs($this->admin())->get(route('admin.seller-applications.index'))
            ->assertOk()->assertDontSee('Maria Clara Handicrafts');
    }

    public function test_admin_approval_emails_the_seller_and_opens_the_dashboard(): void
    {
        $seller = $this->pendingSeller();
        $app = $seller->sellerApplication;

        $this->actingAs($this->admin())->get(route('admin.seller-applications.index'))->assertSee('Maria Clara Handicrafts');
        $this->patch(route('admin.seller-applications.approve', $app))->assertSessionHas('success');
        $this->post(route('logout'));

        $this->assertSame('approved', $app->fresh()->status);
        $this->assertSame('approved', $seller->fresh()->approval_status);
        Mail::assertSent(SellerApprovedMail::class, fn ($m) => $m->hasTo('mariaclara.santos@example.com'));

        $this->login()->assertRedirect(route('seller.dashboard'));
    }

    public function test_admin_rejection_emails_the_reason_and_blocks_the_dashboard(): void
    {
        $seller = $this->pendingSeller();
        $app = $seller->sellerApplication;

        $this->actingAs($this->admin());
        $this->patch(route('admin.seller-applications.reject', $app), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');
        $this->patch(route('admin.seller-applications.reject', $app), ['rejection_reason' => 'The BIR 2303 is blurry.'])
            ->assertSessionHas('success');
        $this->post(route('logout'));

        $this->assertSame('rejected', $app->fresh()->status);
        Mail::assertSent(SellerRejectedMail::class, fn ($m) => $m->hasTo('mariaclara.santos@example.com')
            && str_contains($m->render(), 'The BIR 2303 is blurry.'));

        $this->login()->assertRedirect(route('seller.status'));
        $this->get(route('seller.status'))->assertOk()->assertSee('Rejected')->assertSee('The BIR 2303 is blurry.');
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.status'));
    }

    public function test_resubmitting_a_document_returns_the_application_to_pending(): void
    {
        $seller = $this->pendingSeller();
        $app = $seller->sellerApplication;
        $oldBir = $app->bir_2303_path;

        $this->actingAs($this->admin())->patch(route('admin.seller-applications.reject', $app), ['rejection_reason' => 'Blurry BIR form.']);
        $this->post(route('logout'));

        $this->login();
        $this->post(route('seller.status.resubmit'), [])->assertSessionHasErrors('documents');
        $this->post(route('seller.status.resubmit'), [
            'bir_2303' => UploadedFile::fake()->create('bir_new.pdf', 400, 'application/pdf'),
        ])->assertRedirect(route('seller.status'));
        $this->post(route('logout'));

        $app->refresh();
        $this->assertSame('pending', $app->status);
        $this->assertSame('pending', $seller->fresh()->approval_status);
        $this->assertNotSame($oldBir, $app->bir_2303_path);
        Storage::disk('public')->assertExists($app->bir_2303_path);

        $this->actingAs($this->admin())->get(route('admin.seller-applications.index', ['status' => 'pending']))
            ->assertOk()->assertSee('Maria Clara Handicrafts');
        $this->get(route('admin.seller-applications.show', $app))->assertSee('BIR Form 2303');
    }

    // ── Access control ──────────────────────────────────────

    public function test_non_admins_cannot_view_or_act_on_seller_applications(): void
    {
        $seller = $this->pendingSeller();
        $app = $seller->sellerApplication;
        $buyer = $this->existingUser();

        // Guest
        $this->get(route('admin.seller-applications.show', $app))->assertRedirect(route('login'));
        $this->patch(route('admin.seller-applications.approve', $app))->assertRedirect(route('login'));

        foreach ([$buyer, $seller->fresh()] as $who) {
            $this->actingAs($who);
            $this->get(route('admin.seller-applications.index'))->assertForbidden()->assertDontSee('Maria Clara Handicrafts');
            $this->get(route('admin.seller-applications.show', $app))->assertForbidden()->assertDontSee('123-456-789-000');
            $this->patch(route('admin.seller-applications.approve', $app))->assertForbidden();
            $this->patch(route('admin.seller-applications.reject', $app), ['rejection_reason' => 'x'])->assertForbidden();
        }
        $this->assertSame('pending', $app->fresh()->status);
    }

    public function test_roles_cannot_open_each_others_areas(): void
    {
        $seller = $this->pendingSeller();
        $seller->forceFill(['approval_status' => 'approved'])->save();
        $seller->sellerApplication->update(['status' => 'approved']);
        $buyer = $this->existingUser();

        // Seller → admin, sorting center, courier areas
        $this->actingAs($seller->fresh());
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.seller-applications.index'))->assertForbidden();
        $this->get(route('sc.dashboard'))->assertForbidden();

        // Buyer → seller panel and seller status page
        $this->actingAs($buyer);
        $this->get(route('seller.dashboard'))->assertForbidden();
        $this->get(route('seller.books.create'))->assertForbidden();
        $this->get(route('seller.status'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
