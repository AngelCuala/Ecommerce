<?php

namespace Tests\Feature\Logistics;

use App\Mail\AccountActivationMail;
use App\Models\AccountActivation;
use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\User;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's Add Rider / Add Courier: Pending Activation, the emailed set-password link, resend, expiry and access. */
class StaffAccountsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;
    private string $brgy;
    private LogisticsHub $qc;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
        $this->logistics1->forceFill(['assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => '043412000',
            'assigned_province' => 'Laguna', 'assigned_province_code' => '043400000'])->save();
        $this->token = $this->logistics1->createToken('t')->plainTextToken;
        $this->brgy = collect(app(PsgcDirectory::class)->barangaysByMunicipality('043412000'))->firstWhere('name', 'San Isidro')['psgc'];
        $this->qc = LogisticsHub::create(['sorting_center_id' => $this->logistics1->id, 'code' => 'HUB-QC', 'name' => 'QC First-Mile Hub']);
    }

    /** Switch accounts: the guard keeps the last signed-in user between requests of one test. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function file(string $name, int $kb = 500): UploadedFile
    {
        return str_ends_with($name, '.pdf') ? UploadedFile::fake()->create($name, $kb, 'application/pdf') : UploadedFile::fake()->image($name)->size($kb);
    }

    private function addRider(array $over = [])
    {
        return $this->as($this->token)->post('/api/v1/logistics/riders/accounts', array_merge([
            'first_name' => 'Jose Rizal', 'last_name' => 'Mercado', 'birthday' => '1995-03-22', 'email' => 'jose.mercado@example.com',
            'phone' => '09451230101', 'license_number' => 'N09-23-456789', 'license_expiry' => now()->addYears(3)->toDateString(),
            'vehicle_type' => 'Motorcycle', 'plate_number' => '777JRM', 'barangay_psgc' => $this->brgy,
            'license_file' => $this->file('drivers_license.jpg'), 'or_cr_file' => $this->file('or_cr.pdf'),
        ], $over), ['Accept' => 'application/json']);
    }

    private function addCourier(array $over = [])
    {
        return $this->as($this->token)->post('/api/v1/logistics/couriers', array_merge([
            'first_name' => 'Ernesto', 'last_name' => 'Villareal', 'birthday' => '1988-07-15', 'email' => 'ernesto.villareal@example.com',
            'phone' => '09276543210', 'license_number' => 'N02-15-345678', 'license_type' => 'Professional', 'dl_codes' => ['B', 'B2', 'C'],
            'license_expiry' => now()->addYears(3)->toDateString(), 'vehicle_type' => '4-Wheeler Closed Van Truck', 'plate_number' => 'NAB4521',
            'load_capacity_kg' => '3000', 'home_hub_id' => $this->qc->id,
            'license_file' => $this->file('pro_license.jpg'), 'or_cr_file' => $this->file('truck_or_cr.pdf'), 'nbi_file' => $this->file('nbi_clearance.pdf'),
        ], $over), ['Accept' => 'application/json']);
    }

    private function link(User $user): string
    {
        $link = null;
        Mail::assertSent(AccountActivationMail::class, function ($m) use ($user, &$link) {
            if ($m->user->is($user)) {
                $link = $m->link;
            }
            return true;
        });

        return $link;
    }

    public function test_add_rider_makes_a_pending_activation_rider_and_emails_the_link(): void
    {
        $this->addRider()->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Pending Activation'));

        $rider = Rider::where('license_number', 'N09-23-456789')->firstOrFail();
        $this->assertSame('pending_activation', $rider->application_status);
        $this->assertSame('Pending Activation', $rider->statusLabel());
        $this->assertSame('jose.mercado@example.com', $rider->user->email);
        $this->assertTrue($rider->user->isPendingActivation());
        Mail::assertSent(AccountActivationMail::class, 1);

        // Listed for this company only, under Pending.
        $names = collect($this->as($this->token)->getJson('/api/v1/logistics/riders?tab=pending')->json('data'))->pluck('full_name');
        $this->assertContains('Jose Rizal Mercado', $names);
        $other = $this->logistics2->createToken('t')->plainTextToken;
        $this->assertNotContains('Jose Rizal Mercado', collect($this->as($other)->getJson('/api/v1/logistics/riders')->json('data'))->pluck('full_name'));
    }

    public function test_add_rider_rules(): void
    {
        $r = $this->as($this->token)->postJson('/api/v1/logistics/riders/accounts', []);
        $r->assertStatus(422);
        foreach (['first_name', 'last_name', 'birthday', 'email', 'phone', 'license_number', 'license_expiry', 'vehicle_type', 'plate_number', 'license_file', 'or_cr_file'] as $f) {
            $this->assertArrayHasKey($f, $r->json('errors'), $f);
        }

        $this->addRider(['first_name' => 'Pedro Jose99'])->assertStatus(422)->assertJsonValidationErrors('first_name');
        $this->addRider(['birthday' => now()->subYears(18)->addDay()->toDateString()])->assertJsonValidationErrors(['birthday' => 'at least 18']);
        $this->addRider(['email' => 'pedrojose.garcia@example'])->assertJsonValidationErrors('email');
        $this->addRider(['email' => 'pedrojose.garcia@example.com'])->assertJsonValidationErrors(['email' => 'already in use']);
        $this->addRider(['license_number' => 'N01-23-45678'])->assertJsonValidationErrors(['license_number' => 'A00-00-000000']);
        $this->addRider(['license_number' => 'N01-23-456789'])->assertJsonValidationErrors(['license_number' => 'already registered']);
        $this->addRider(['license_expiry' => now()->subDay()->toDateString()])->assertJsonValidationErrors(['license_expiry' => 'expired']);
        $this->addRider(['plate_number' => '123 abc'])->assertJsonValidationErrors(['plate_number' => 'already registered']);
        $this->addRider(['vehicle_type' => 'Truck'])->assertJsonValidationErrors('vehicle_type');
        $this->addRider(['license_file' => UploadedFile::fake()->create('price_list.docx', 320)])->assertJsonValidationErrors(['license_file' => 'JPG, PNG or PDF']);
        $this->addRider(['license_file' => UploadedFile::fake()->image('big.jpg')->size(5121)])->assertJsonValidationErrors(['license_file' => '5 MB']);
        $this->assertSame(0, Rider::where('license_number', 'N09-23-456789')->count());

        // Exactly 5 MB and an 18th birthday today are accepted.
        $this->addRider(['license_file' => UploadedFile::fake()->image('ok.jpg')->size(5120), 'birthday' => now()->subYears(18)->toDateString(),
            'license_expiry' => now()->toDateString()])->assertOk();
        // A second save of the same rider is refused (email, mobile, license and plate are taken): one rider, one email.
        $this->addRider()->assertStatus(422);
        $this->assertSame(1, Rider::where('license_number', 'N09-23-456789')->count());
        Mail::assertSent(AccountActivationMail::class, 1);
    }

    public function test_activation_link_sets_the_password_once(): void
    {
        $this->addRider()->assertOk();
        $user = User::where('email', 'jose.mercado@example.com')->firstOrFail();
        $url = $this->link($user);
        $path = parse_url($url, PHP_URL_PATH);

        // Before activating: login is denied.
        $this->postJson('/api/v1/auth/login', ['email' => 'jose.mercado@example.com', 'password' => 'Rider@2026'])
            ->assertStatus(422)->assertJsonValidationErrors(['email' => 'not activated']);

        $this->get($path)->assertOk()->assertSee('choose the password', false);
        $this->post($path, ['password' => 'rider@2026', 'password_confirmation' => 'rider@2026'])->assertSessionHasErrors('password');
        $this->post($path, ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2027'])->assertSessionHasErrors(['password' => 'The passwords do not match.']);
        $this->assertTrue($user->fresh()->isPendingActivation());

        $this->post($path, ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertOk()->assertSee('now active');
        $this->assertSame('approved', $user->fresh()->approval_status);
        $this->assertSame('approved', Rider::where('user_id', $user->id)->value('application_status'));

        // The used link can't be used again.
        $this->get($path)->assertOk()->assertSee('already been used');
        $this->post($path, ['password' => 'Other@2026', 'password_confirmation' => 'Other@2026'])->assertSee('already been used');

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'jose.mercado@example.com', 'password' => 'Rider@2026']);
        $login->assertOk()->assertJsonPath('user.home', 'rider');
    }

    public function test_expired_and_resent_links(): void
    {
        $this->addRider()->assertOk();
        $user = User::where('email', 'jose.mercado@example.com')->firstOrFail();
        $first = parse_url($this->link($user), PHP_URL_PATH);

        $this->travel(24 * 60 + 1)->minutes();
        $this->get($first)->assertSee('has expired');
        $this->post($first, ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertSee('has expired');
        $this->assertTrue($user->fresh()->isPendingActivation());
        $this->travelBack();

        $rider = Rider::where('user_id', $user->id)->first();
        $this->as($this->token)->postJson("/api/v1/logistics/riders/{$rider->id}/resend-activation")->assertOk();
        Mail::assertSent(AccountActivationMail::class, 2);
        $this->get($first)->assertSee('invalid or has been replaced');
        $this->assertSame(1, AccountActivation::where('user_id', $user->id)->count());

        // Another company can't resend it.
        $other = $this->logistics2->createToken('t')->plainTextToken;
        $this->as($other)->postJson("/api/v1/logistics/riders/{$rider->id}/resend-activation")->assertForbidden();
    }

    public function test_add_courier_with_home_hub_capacity_and_dl_codes(): void
    {
        $this->as($this->token)->getJson('/api/v1/logistics/couriers/form')
            ->assertOk()->assertJsonPath('hubs.0.code', 'HUB-QC')->assertJsonCount(3, 'vehicles');

        $r = $this->addCourier();
        $r->assertCreated()->assertJsonPath('courier.status_label', 'Pending Activation')->assertJsonPath('courier.home_hub', 'HUB-QC (QC First-Mile Hub)')
            ->assertJsonPath('courier.load_capacity_kg', 3000);
        $courier = Courier::where('license_number', 'N02-15-345678')->firstOrFail();
        $this->assertTrue($courier->isLinehaul());
        Mail::assertSent(AccountActivationMail::class, 1);

        $more = ['email' => 'courier.test03@example.com', 'phone' => '09170000021', 'license_number' => 'D10-12-654321', 'plate_number' => 'CAV7788'];
        $this->addCourier(['license_type' => 'Non-professional'] + $more)->assertJsonValidationErrors(['license_type' => 'professional']);
        $this->addCourier(['vehicle_type' => 'Van', 'dl_codes' => ['A'], 'load_capacity_kg' => '800'] + $more)->assertJsonValidationErrors(['dl_codes' => 'B or B2']);
        $this->addCourier(['dl_codes' => ['B', 'B2']] + $more)->assertJsonValidationErrors(['dl_codes' => 'C']);
        $this->addCourier(['dl_codes' => []] + $more)->assertJsonValidationErrors('dl_codes');
        foreach (['99', '1001', '0', '-1', '1500.5', 'abc'] as $kg) {
            $this->addCourier(['vehicle_type' => 'Van', 'dl_codes' => ['B'], 'load_capacity_kg' => $kg] + $more)->assertJsonValidationErrors('load_capacity_kg');
        }
        $this->addCourier(['load_capacity_kg' => '4001'] + $more)->assertJsonValidationErrors(['load_capacity_kg' => '4,000']);
        $this->addCourier(['plate_number' => 'nab 4521'] + $more)->assertJsonValidationErrors(['plate_number' => 'already registered']);
        $this->addCourier(['plate_number' => '123ABC'] + $more)->assertJsonValidationErrors(['plate_number' => 'already registered']);
        $this->addCourier(['license_number' => 'N01-23-456789'] + $more)->assertJsonValidationErrors(['license_number' => 'already registered']);
        $this->addCourier(['phone' => '+639276543210'] + $more)->assertJsonValidationErrors(['phone' => 'already in use']);
        $this->addCourier(['email' => 'pedrojose.garcia@example.com'] + $more)->assertJsonValidationErrors(['email' => 'already in use']);
        $this->addCourier(['nbi_file' => UploadedFile::fake()->create('store_banner.gif', 480, 'image/gif')] + $more)->assertJsonValidationErrors('nbi_file');
        $agilaHub = LogisticsHub::create(['sorting_center_id' => $this->logistics2->id, 'code' => 'HUB-DVO', 'name' => 'Davao Hub']);
        $this->addCourier(['home_hub_id' => $agilaHub->id] + $more)->assertJsonValidationErrors(['home_hub_id' => 'your company']);

        $this->addCourier(['vehicle_type' => 'Van', 'dl_codes' => ['B2'], 'load_capacity_kg' => '1000'] + $more)->assertCreated();
        $this->assertSame(2, Courier::whereNotNull('home_hub_id')->count());
    }

    public function test_courier_activation_and_access(): void
    {
        $this->addCourier()->assertCreated();
        $user = User::where('email', 'ernesto.villareal@example.com')->firstOrFail();
        $path = parse_url($this->link($user), PHP_URL_PATH);
        $this->post($path, ['password' => 'Courier@2026', 'password_confirmation' => 'Courier@2026'])->assertOk();
        $this->assertSame('approved', Courier::where('user_id', $user->id)->value('status'));

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'ernesto.villareal@example.com', 'password' => 'Courier@2026']);
        $login->assertOk()->assertJsonPath('user.home', 'linehaul');
        $t = $login->json('token');
        // No rider jobs or company screens for a courier.
        $this->as($t)->getJson('/api/v1/rider/jobs')->assertForbidden();
        $this->as($t)->postJson('/api/v1/logistics/couriers', [])->assertForbidden();

        // Only the company that made the courier lists them.
        $other = $this->logistics2->createToken('t')->plainTextToken;
        $this->assertNotContains('Ernesto Villareal', collect($this->as($other)->getJson('/api/v1/logistics/couriers?status=approved')->json('data'))->pluck('name'));
        $this->assertContains('Ernesto Villareal', collect($this->as($this->token)->getJson('/api/v1/logistics/couriers?status=approved')->json('data'))->pluck('name'));
    }

    public function test_buyers_and_riders_cannot_add_staff(): void
    {
        $rider = $this->postJson('/api/v1/auth/login', ['email' => 'pedrojose.garcia@example.com', 'password' => 'Rider@2026'])->json('token');
        $this->as($rider)->postJson('/api/v1/logistics/riders/accounts', [])->assertForbidden();
        $this->as($rider)->postJson('/api/v1/logistics/couriers', [])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->postJson('/api/v1/logistics/couriers', [])->assertUnauthorized();

    }

    public function test_hubs(): void
    {
        $this->as($this->token)->postJson('/api/v1/logistics/hubs', ['code' => 'hub-psg', 'name' => 'Pasig Main Hub'])->assertCreated()
            ->assertJsonCount(2, 'hubs');
        $this->as($this->token)->postJson('/api/v1/logistics/hubs', ['code' => 'HUB-PSG', 'name' => 'Again'])->assertJsonValidationErrors(['code' => 'already used']);
        $this->as($this->token)->postJson('/api/v1/logistics/hubs', ['code' => 'PSG', 'name' => 'Bad code'])->assertJsonValidationErrors('code');
        // A company without hubs gets its main hub.
        $other = $this->logistics2->createToken('t')->plainTextToken;
        $this->as($other)->getJson('/api/v1/logistics/hubs')->assertOk()->assertJsonPath('hubs.0.name', 'Agila Cargo Movers Corp. Main Hub');
    }
}
