<?php

namespace Tests\Feature;

use App\Mail\SortingCenterApprovedMail;
use App\Mail\SortingCenterRejectedMail;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelTransfer;
use App\Models\Rider;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Models\DeliveryArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScRegistrationAndLogisticsTest extends TestCase
{
    use RefreshDatabase;

    private const LUISIANA = '043412000';
    private const LAGUNA   = '043400000';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    // ── Helpers ───────────────────────────────────────────────

    private function regData(array $override = []): array
    {
        return $override + [
            'last_name' => 'Cruz', 'first_name' => 'Maria', 'middle_initial' => 'S',
            'sex' => 'Female', 'email' => 'luisiana-sc@example.com', 'contact_no' => '09171234567',
            'birthday' => '1990-05-15', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'business_name' => 'Luisiana Sorting Center',
            'region' => 'Region IV-A (CALABARZON)', 'region_psgc' => '0400000000', 'region_code' => '040000000',
            'province' => 'Laguna', 'province_code' => self::LAGUNA,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA,
            'barangay' => 'San Antonio', 'barangay_psgc' => '0403412010',
            'street' => 'Rizal St', 'house_number' => '12', 'zip_code' => '4032',
            'government_id'   => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('dti.pdf', 50, 'application/pdf'),
            'terms' => '1',
        ];
    }

    private function register(array $override = [])
    {
        return $this->from(route('sc.register'))->post(route('sc.register.store'), $this->regData($override));
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@e.com', 'password' => bcrypt('x'),
            'role' => 'admin', 'approval_status' => 'approved']);
    }

    private function sc(string $email, ?string $code = self::LUISIANA): User
    {
        return User::forceCreate([
            'name' => 'SC '.$email, 'email' => $email, 'password' => bcrypt('password'), 'role' => 'sorting_center',
            'approval_status' => 'approved',
            'assigned_municipality' => $code ? 'Luisiana' : null, 'assigned_municipality_code' => $code,
            'assigned_province' => $code ? 'Laguna' : null, 'assigned_province_code' => $code ? self::LAGUNA : null,
        ]);
    }

    private function buyer(): User
    {
        return User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@e.com', 'password' => bcrypt('x'),
            'role' => 'buyer', 'approval_status' => 'approved']);
    }

    private function seller(): User
    {
        return User::forceCreate(['name' => 'Seller', 'email' => 'seller@e.com', 'password' => bcrypt('x'),
            'role' => 'seller', 'approval_status' => 'approved']);
    }

    private function order(User $buyer, string $status = 'Processing'): Order
    {
        return Order::forceCreate([
            'user_id' => $buyer->id, 'full_name' => 'Buyer', 'city' => 'Luisiana', 'province' => 'Laguna',
            'municipality_code' => self::LUISIANA, 'total_price' => 150, 'payment_method' => 'COD',
            'payment_status' => 'Pending', 'shipping_address' => 'San Antonio, Luisiana', 'status' => $status,
        ]);
    }

    private function parcel(User $sc, User $seller, ?Order $order, string $status, array $extra = []): Parcel
    {
        return Parcel::forceCreate($extra + [
            'tracking_number' => Parcel::generateTracking(), 'order_id' => $order?->id, 'seller_id' => $seller->id,
            'pickup_address' => 'Seller', 'dropoff_address' => 'San Antonio, Luisiana', 'receiver_name' => 'Buyer',
            'destination_municipality' => 'Luisiana', 'status' => $status, 'current_sorting_center_id' => $sc->id,
        ]);
    }

    // ── 1. Registration ───────────────────────────────────────

    public function test_registration_page_loads_and_has_psgc_and_register_link_on_login(): void
    {
        // Sorting center sign-up is the "Logistics" account type on the /register wizard.
        $this->get(route('sc.register'))->assertRedirect(route('register', ['role' => 'logistics']));
        $html = $this->get(route('register', ['role' => 'logistics']))->assertOk()->getContent();
        $this->assertStringContainsString('reg_region', $html);
        $this->assertStringContainsString('name="business_name"', $html);
        $this->assertStringContainsString('government_id', $html);
        $this->assertStringContainsString('business_permit', $html);
        $this->get(route('sc.login'))->assertOk()->assertSee(route('sc.register'), false);
    }

    public function test_valid_registration_creates_pending_account_and_application(): void
    {
        $this->register()->assertSessionHasNoErrors()->assertRedirect(route('sc.login'))->assertSessionHas('success');

        $user = User::where('email', 'luisiana-sc@example.com')->firstOrFail();
        $this->assertSame('sorting_center_pending', $user->role);
        $this->assertSame('pending', $user->approval_status);
        $this->assertNull($user->assigned_municipality_code);

        $app = SortingCenterApplication::firstOrFail();
        $this->assertSame(self::LUISIANA, $app->municipality_code);
        $this->assertSame(self::LAGUNA, $app->province_code);
        $this->assertSame('pending', $app->status);
        Storage::disk('local')->assertExists($app->government_id_path);
        Storage::disk('local')->assertExists($app->business_permit_path);
        $this->assertFalse(auth()->check());
    }

    public function test_registration_rejects_invalid_psgc_wrong_province_missing_permit(): void
    {
        // Cavite province code with Luisiana municipality (Laguna)
        $this->register(['province_code' => '042100000'])->assertSessionHasErrors('province');

        // Missing required business permit
        $this->register(['business_permit' => null])->assertSessionHasErrors('business_permit');

        // Invalid file type
        $this->register(['government_id' => UploadedFile::fake()->create('x.exe', 10)])
            ->assertSessionHasErrors('government_id');

        $this->assertSame(0, SortingCenterApplication::count());
        $this->assertSame(0, User::count());
    }

    public function test_duplicate_email_rejected(): void
    {
        $this->register()->assertSessionHasNoErrors();
        $this->register()->assertSessionHasErrors('email');
        $this->assertSame(1, SortingCenterApplication::count());
    }

    // ── 2. Admin approval ─────────────────────────────────────

    public function test_pending_registration_appears_in_admin(): void
    {
        $this->register();
        $app   = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.sc-applications.index'))->assertOk()->assertSee('Luisiana Sorting Center');
        $this->actingAs($admin)->get(route('admin.sc-applications.show', $app))->assertOk()
            ->assertSee('Maria')->assertSee(self::LUISIANA)->assertSee('Approve')->assertSee('Disapprove');
        $this->actingAs($admin)->get(route('admin.sc-applications.document', [$app, 'id']))->assertOk();
        $this->actingAs($admin)->get(route('admin.sc-applications.document', [$app, 'permit']))->assertOk();
        $this->actingAs($admin)->get('/admin/sc-applications/'.$app->id.'/document/other')->assertNotFound();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Sorting Center Registrations');
    }

    public function test_admin_approves_unlocks_portal_sends_email(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();

        $this->actingAs($this->admin())->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('success');

        $user = $app->user->fresh();
        $this->assertSame('sorting_center', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame(self::LUISIANA, $user->assigned_municipality_code);
        $this->assertSame('approved', $app->fresh()->status);
        Mail::assertSent(SortingCenterApprovedMail::class, fn ($m) => $m->hasTo('luisiana-sc@example.com'));
    }

    public function test_approval_blocked_when_municipality_already_taken(): void
    {
        $this->sc('existing@e.com', self::LUISIANA);
        $this->register();
        $app = SortingCenterApplication::firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.sc-applications.show', $app))->assertSee('already serves');
        $this->actingAs($this->admin())->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('error');
        $this->assertSame('pending', $app->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_already_reviewed_cannot_be_approved_again(): void
    {
        $this->register();
        $app   = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.sc-applications.approve', $app));
        $this->actingAs($admin)->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('error');
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_admin_rejects_with_reason_sends_email(): void
    {
        $this->register();
        $app   = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.sc-applications.reject', $app), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)->patch(route('admin.sc-applications.reject', $app), ['rejection_reason' => 'DTI expired'])
            ->assertSessionHas('success');
        $this->assertSame('rejected', $app->fresh()->status);
        $this->assertSame('DTI expired', $app->fresh()->rejection_reason);
        $this->assertSame('sorting_center_pending', $app->user->fresh()->role);
        Mail::assertSent(SortingCenterRejectedMail::class, fn ($m) => $m->hasTo('luisiana-sc@example.com'));
        Mail::assertNotSent(SortingCenterApprovedMail::class);
    }

    public function test_email_views_render(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $this->assertStringContainsString('Luisiana', (new SortingCenterApprovedMail($app))->render());
        $app->rejection_reason = 'Blurry ID';
        $this->assertStringContainsString('Blurry ID', (new SortingCenterRejectedMail($app))->render());
    }

    // ── 3. Login states ───────────────────────────────────────

    public function test_pending_account_gets_correct_message(): void
    {
        $this->register();
        $this->from(route('sc.login'))->post(route('sc.login.store'), [
            'email' => 'luisiana-sc@example.com', 'password' => 'secret-pass-1',
        ])->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('pending', session()->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_rejected_account_gets_correct_message(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $app->update(['status' => 'rejected', 'rejection_reason' => 'Blurry ID']);
        $app->user->update(['approval_status' => 'rejected']);

        $this->from(route('sc.login'))->post(route('sc.login.store'), [
            'email' => 'luisiana-sc@example.com', 'password' => 'secret-pass-1',
        ])->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('not approved', session()->get('errors')->first('email'));
        $this->assertStringContainsString('Blurry ID', session()->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_approved_account_can_login(): void
    {
        $sc = $this->sc('approved@e.com');
        $this->from(route('sc.login'))->post(route('sc.login.store'), ['email' => 'approved@e.com', 'password' => 'password'])
            ->assertRedirect(route('sc.dashboard'));
        $this->assertAuthenticatedAs($sc);
    }

    public function test_wrong_role_cannot_access_sc_portal(): void
    {
        $suspended = $this->sc('sus@e.com');
        $suspended->update(['role' => 'suspended']);
        $this->from(route('sc.login'))->post(route('sc.login.store'), ['email' => 'sus@e.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_old_sorting_center_urls_redirect(): void
    {
        $this->get('/sorting-center/login')->assertRedirect('/sc/login');
        $this->get('/sorting-center')->assertRedirect('/sc/login');
    }

    // ── 4. Logistics process ──────────────────────────────────

    public function test_receiving_still_works_and_syncs_order(): void
    {
        $sc    = $this->sc('sc@e.com');
        $buyer = $this->buyer();
        $order = $this->order($buyer);
        $parcel = $this->parcel($sc, $this->seller(), $order, 'pickup_approved');

        $this->actingAs($sc)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])
            ->assertSessionHas('success');
        $this->assertSame('picked_up', $parcel->fresh()->status);
        $this->assertSame('Shipped', $order->fresh()->status);
    }

    public function test_cancelled_order_cannot_be_received(): void
    {
        $sc    = $this->sc('sc@e.com');
        $buyer = $this->buyer();
        $seller = $this->seller();
        $order  = $this->order($buyer, 'Cancelled');
        $parcel = $this->parcel($sc, $seller, $order, 'pickup_approved');

        $this->actingAs($sc)->post(route('sc.parcels.advance', $parcel))->assertSessionHas('error');
        $this->assertSame('cancelled', $parcel->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $seller->id, 'title' => 'Pickup cancelled']);
    }

    public function test_buyer_cancel_closes_pending_and_approved_parcels(): void
    {
        $sc    = $this->sc('sc@e.com');
        $buyer = $this->buyer();
        $seller = $this->seller();

        foreach (['pending_pickup', 'pickup_approved'] as $status) {
            $order  = $this->order($buyer, 'Processing');
            $parcel = $this->parcel($sc, $seller, $order, $status);

            $this->actingAs($buyer)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed mind'])
                ->assertSessionHas('success');
            $this->assertSame('Cancelled', $order->fresh()->status);
            $this->assertSame('cancelled', $parcel->fresh()->status, "status=$status");
        }

        $this->assertSame(2, \App\Models\UserNotification::where('user_id', $sc->id)->where('title', 'Pickup cancelled')->count());
    }

    public function test_transfer_full_flow_and_guards(): void
    {
        $from   = $this->sc('from@e.com', self::LUISIANA);
        $to     = $this->sc('to@e.com', '043415000');
        $noMun  = User::forceCreate(['name' => 'NoMun', 'email' => 'nomun@e.com', 'password' => bcrypt('x'),
            'role' => 'sorting_center', 'approval_status' => 'approved']);
        $buyer  = $this->buyer();
        $seller = $this->seller();
        $order  = $this->order($buyer, 'Shipped');
        $area   = DeliveryArea::create(['sorting_center_id' => $from->id, 'name' => 'San Antonio', 'code' => 'BGY-1',
            'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010']);
        $parcel = $this->parcel($from, $seller, $order, 'sorted', ['area_id' => $area->id, 'sorted_at' => now()]);
        $rider  = Rider::forceCreate(['user_id' => $from->id, 'sorting_center_id' => $from->id, 'full_name' => 'R',
            'area_id' => $area->id, 'application_status' => 'approved', 'is_active' => true]);

        $initiate = fn (User $as, Parcel $p, User $dest) =>
            $this->actingAs($as)->from(route('sc.transfers'))->post(route('sc.transfers.initiate'),
                ['parcel_id' => $p->id, 'to_sorting_center_id' => $dest->id]);

        // Bad statuses rejected.
        foreach (['pickup_approved', 'assigned', 'in_transit', 'delivered'] as $bad) {
            $p = $this->parcel($from, $seller, $this->order($buyer, 'Shipped'), $bad);
            $initiate($from, $p, $to)->assertSessionHasErrors('parcel_id');
        }

        // No municipality and self-transfer rejected.
        $initiate($from, $parcel, $noMun)->assertSessionHasErrors('to_sorting_center_id');
        $initiate($from, $parcel, $from)->assertSessionHasErrors('to_sorting_center_id');

        // Valid transfer: parcel is held; assignment blocked.
        $initiate($from, $parcel, $to)->assertSessionHas('success');
        $this->assertSame('outgoing', $parcel->fresh()->transfer_status);
        $this->actingAs($from)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $rider->id])->assertSessionHas('error');
        $this->assertDatabaseCount('parcel_deliveries', 0);

        // Only destination can accept.
        $transfer = ParcelTransfer::firstOrFail();
        $this->actingAs($from)->post(route('sc.transfers.accept', $transfer))->assertForbidden();

        $this->actingAs($to)->post(route('sc.transfers.accept', $transfer))->assertSessionHas('success');
        $p = $parcel->fresh();
        $this->assertSame((int) $to->id, (int) $p->current_sorting_center_id);
        $this->assertSame('picked_up', $p->status);
        $this->assertNull($p->area_id);
        $this->assertNull($p->transfer_status);
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame('Shipped', $order->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $buyer->id, 'title' => 'Parcel transferred']);
    }

    public function test_reject_and_cancel_transfer_release_hold(): void
    {
        $from = $this->sc('from@e.com', self::LUISIANA);
        $to   = $this->sc('to@e.com', '043415000');
        $buyer = $this->buyer(); $seller = $this->seller();

        foreach (['reject' => $to, 'cancel' => $from] as $action => $actor) {
            $parcel = $this->parcel($from, $seller, $this->order($buyer, 'Shipped'), 'picked_up');
            $this->actingAs($from)->post(route('sc.transfers.initiate'), ['parcel_id' => $parcel->id, 'to_sorting_center_id' => $to->id]);
            $transfer = ParcelTransfer::where('parcel_id', $parcel->id)->firstOrFail();
            $this->actingAs($actor)->post(route("sc.transfers.{$action}", $transfer))->assertSessionHas('success');
            $this->assertNull($parcel->fresh()->transfer_status, $action);
        }
    }
}
