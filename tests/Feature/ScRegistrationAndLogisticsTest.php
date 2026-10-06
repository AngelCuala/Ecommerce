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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Logistics / Sorting Center (web side only): ERP registration + admin approval,
 * SC login states, and the parcel process fixes (cancel, transfers).
 */
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

    private function registration(array $override = []): array
    {
        return $override + [
            'last_name' => 'Dela Cruz', 'first_name' => 'Maria', 'middle_initial' => 'S',
            'sex' => 'Female', 'email' => 'luisiana-sc@example.com', 'contact_no' => '09171234567',
            'birthday' => '1990-05-15', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'business_name' => 'Luisiana Sorting Center',
            'region' => 'Region IV-A (CALABARZON)', 'region_psgc' => '0400000000', 'region_code' => '040000000',
            'province' => 'Laguna', 'province_code' => self::LAGUNA,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA,
            'barangay' => 'San Antonio', 'barangay_psgc' => '0403412010',
            'street' => 'Rizal St.', 'house_number' => '12', 'zip_code' => '4032',
            'government_id'   => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('dti.pdf', 100, 'application/pdf'),
            'terms' => '1',
        ];
    }

    private function register(array $override = [])
    {
        return $this->from(route('sc.register'))->post(route('sc.register.store'), $this->registration($override));
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
            'role' => 'admin', 'approval_status' => 'approved']);
    }

    private function sc(string $email, ?string $code = self::LUISIANA, string $name = 'Luisiana Center'): User
    {
        return User::forceCreate([
            'name' => $name, 'email' => $email, 'password' => bcrypt('password'), 'role' => 'sorting_center',
            'approval_status' => 'approved',
            'assigned_municipality' => $code ? $name : null, 'assigned_municipality_code' => $code,
            'assigned_province' => $code ? 'Laguna' : null, 'assigned_province_code' => $code ? self::LAGUNA : null,
        ]);
    }

    private function order(User $buyer, string $status = 'Processing'): Order
    {
        return Order::forceCreate([
            'user_id' => $buyer->id, 'full_name' => 'Buyer', 'city' => 'Luisiana', 'province' => 'Laguna',
            'municipality_code' => self::LUISIANA, 'total_price' => 150, 'payment_method' => 'COD',
            'payment_status' => 'Pending', 'shipping_address' => 'San Antonio, Luisiana, Laguna', 'status' => $status,
        ]);
    }

    private function parcel(User $sc, User $seller, ?Order $order, string $status, array $extra = []): Parcel
    {
        return Parcel::forceCreate($extra + [
            'tracking_number' => Parcel::generateTracking(), 'order_id' => $order?->id, 'seller_id' => $seller->id,
            'pickup_address' => 'Seller St.', 'dropoff_address' => 'San Antonio, Luisiana, Laguna', 'receiver_name' => 'Buyer',
            'destination_municipality' => 'Luisiana', 'status' => $status, 'current_sorting_center_id' => $sc->id,
        ]);
    }

    private function people(): array
    {
        $buyer  = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => bcrypt('x'), 'role' => 'buyer', 'approval_status' => 'approved']);
        $seller = User::forceCreate(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x'), 'role' => 'seller', 'approval_status' => 'approved']);
        return [$buyer, $seller];
    }

    // ── 1. Registration ───────────────────────────────────────

    public function test_registration_page_loads_with_the_psgc_cascade_and_is_linked_from_login(): void
    {
        $html = $this->get(route('sc.register'))->assertOk()->getContent();
        foreach (['scr_region', 'scr_province', 'scr_municipality', 'scr_barangay', 'js/psgc-address.js',
                  'name="business_name"', 'name="government_id"', 'name="business_permit"', 'id="age"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->get(route('sc.login'))->assertOk()->assertSee(route('sc.register'), false);
    }

    public function test_valid_registration_creates_a_locked_account_and_a_pending_application(): void
    {
        $this->register()->assertSessionHasNoErrors()->assertRedirect(route('sc.login'))->assertSessionHas('success');

        $user = User::where('email', 'luisiana-sc@example.com')->firstOrFail();
        $this->assertSame('sorting_center_pending', $user->role);
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('Luisiana Sorting Center', $user->name);
        $this->assertNull($user->assigned_municipality_code, 'no coverage until approved');

        $app = SortingCenterApplication::firstOrFail();
        $this->assertSame([self::LUISIANA, self::LAGUNA, '040000000', '043412010', 'San Antonio', 'Luisiana', 35],
            [$app->municipality_code, $app->province_code, $app->region_code, $app->barangay_code, $app->barangay, $app->municipality, $app->age]);
        $this->assertSame('pending', $app->status);
        Storage::disk('local')->assertExists($app->government_id_path);
        Storage::disk('local')->assertExists($app->business_permit_path);
        $this->assertFalse(auth()->check(), 'not logged in after registering');
    }

    public function test_invalid_psgc_chains_and_bad_input_are_rejected(): void
    {
        $cases = [
            'province'     => ['province_code' => '042100000', 'province' => 'Cavite'],          // Cavite + Luisiana
            'barangay'     => ['barangay' => 'Alipit', 'barangay_psgc' => '0403415001'],          // Magdalena barangay
            'municipality' => ['municipality_code' => '999999999', 'municipality' => 'Nowhere'],
            'business_permit' => ['business_permit' => null],
            'government_id'   => ['government_id' => UploadedFile::fake()->create('id.exe', 10)],
            'birthday'     => ['birthday' => now()->addDay()->toDateString()],
            'sex'          => ['sex' => 'Robot'],
        ];
        foreach ($cases as $field => $override) {
            $this->register($override)->assertSessionHasErrors($field);
        }
        $this->assertSame(0, SortingCenterApplication::count());
        $this->assertSame(0, User::count());

        // Duplicate e-mail
        $this->register()->assertSessionHasNoErrors();
        $this->register()->assertSessionHasErrors('email');
        $this->assertSame(1, SortingCenterApplication::count());
    }

    // ── 2. Admin approval ─────────────────────────────────────

    public function test_pending_registration_appears_for_admin_with_documents(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.sc-applications.index'))->assertOk()
            ->assertSee('Luisiana Sorting Center')->assertSee('Pending');
        $this->actingAs($admin)->get(route('admin.sc-applications.show', $app))->assertOk()
            ->assertSee('Maria')->assertSee(self::LUISIANA)->assertSee('Approve')->assertSee('Disapprove');
        $this->actingAs($admin)->get(route('admin.sc-applications.document', [$app, 'id']))->assertOk();
        $this->actingAs($admin)->get(route('admin.sc-applications.document', [$app, 'permit']))->assertOk();
        $this->actingAs($admin)->get('/admin/sc-applications/' . $app->id . '/document/other')->assertNotFound();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Sorting Center Applications');

        // Non-admins cannot review.
        $sc = $this->sc('other@example.com');
        $this->actingAs($sc)->get(route('admin.sc-applications.index'))->assertForbidden();
    }

    public function test_admin_approves_and_the_center_gets_coverage_and_an_email(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('success');

        $user = $app->user->fresh();
        $this->assertSame('sorting_center', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame(['Luisiana', self::LUISIANA, 'Laguna', self::LAGUNA],
            [$user->assigned_municipality, $user->assigned_municipality_code, $user->assigned_province, $user->assigned_province_code]);
        $this->assertSame('approved', $app->fresh()->status);
        $this->assertSame((int) $admin->id, (int) $app->fresh()->reviewed_by);
        Mail::assertSent(SortingCenterApprovedMail::class, fn ($m) => $m->hasTo('luisiana-sc@example.com'));

        // Already reviewed: cannot approve or reject again.
        $this->actingAs($admin)->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('admin.sc-applications.reject', $app), ['rejection_reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_approval_is_blocked_when_the_municipality_already_has_a_center(): void
    {
        $this->sc('existing@example.com', self::LUISIANA, 'Existing Center');
        $this->register();
        $app = SortingCenterApplication::firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.sc-applications.show', $app))->assertSee('already serves Luisiana');
        $this->actingAs($this->admin())->patch(route('admin.sc-applications.approve', $app))->assertSessionHas('error');
        $this->assertSame('pending', $app->fresh()->status);
        $this->assertSame('sorting_center_pending', $app->user->fresh()->role);
        Mail::assertNothingSent();
    }

    public function test_admin_rejects_with_a_required_reason_and_an_email(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.sc-applications.reject', $app), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending', $app->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.sc-applications.reject', $app), ['rejection_reason' => 'DTI permit is expired'])
            ->assertSessionHas('success');
        $this->assertSame(['rejected', 'DTI permit is expired'], [$app->fresh()->status, $app->fresh()->rejection_reason]);
        $this->assertSame('rejected', $app->user->fresh()->approval_status);
        $this->assertSame('sorting_center_pending', $app->user->fresh()->role);
        Mail::assertSent(SortingCenterRejectedMail::class, fn ($m) => $m->hasTo('luisiana-sc@example.com')
            && str_contains($m->render(), 'DTI permit is expired'));
        Mail::assertNotSent(SortingCenterApprovedMail::class);
    }

    public function test_review_emails_render(): void
    {
        $this->register();
        $app = SortingCenterApplication::firstOrFail();
        $this->assertStringContainsString('Luisiana', (new SortingCenterApprovedMail($app))->render());
        $app->rejection_reason = 'Blurry ID';
        $this->assertStringContainsString('Blurry ID', (new SortingCenterRejectedMail($app))->render());
    }

    // ── 3. Login states ───────────────────────────────────────

    public function test_login_messages_for_pending_rejected_approved_and_suspended(): void
    {
        $login = fn (string $email, string $pw = 'secret-pass-1') =>
            $this->from(route('sc.login'))->post(route('sc.login.store'), ['email' => $email, 'password' => $pw]);

        $this->register();
        $app = SortingCenterApplication::firstOrFail();

        $login('luisiana-sc@example.com')->assertSessionHasErrors(['email' => 'Your sorting center registration is pending administrator approval. You will be notified by email.']);
        $this->assertGuest();

        // The main /login is locked too.
        $this->post(route('login'), ['email' => 'luisiana-sc@example.com', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $app->update(['status' => 'rejected', 'rejection_reason' => 'Blurry ID']);
        $app->user->update(['approval_status' => 'rejected']);
        $login('luisiana-sc@example.com')->assertSessionHasErrors(['email' => 'Your sorting center registration was not approved. Reason: Blurry ID']);
        $this->assertGuest();

        $approved = $this->sc('approved@example.com');
        $login('approved@example.com', 'password')->assertRedirect(route('sc.dashboard'));
        $this->assertAuthenticatedAs($approved);
        $this->post(route('sc.logout'));

        $suspended = $this->sc('suspended@example.com');
        $suspended->update(['role' => 'suspended']);
        $login('suspended@example.com', 'password')->assertSessionHasErrors(['email' => 'You do not have access to the Sorting Center portal.']);
        $this->assertGuest();
        $this->actingAs($suspended->fresh())->get(route('sc.dashboard'))->assertForbidden();
    }

    public function test_old_sorting_center_entry_points_redirect_to_the_single_login(): void
    {
        $this->get('/sorting-center/login')->assertRedirect('/sc/login');
        $this->get('/sorting-center')->assertRedirect('/sc/login');
    }

    // ── 4. Logistics process ──────────────────────────────────

    public function test_receiving_still_works_and_syncs_the_order(): void
    {
        [$buyer, $seller] = $this->people();
        $sc = $this->sc('sc@example.com');
        $order = $this->order($buyer, 'Processing');
        $parcel = $this->parcel($sc, $seller, $order, 'pickup_approved');

        $this->actingAs($sc)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $this->assertSame('picked_up', $parcel->fresh()->status);
        $this->assertNotNull($parcel->fresh()->received_at);
        $this->assertSame('Shipped', $order->fresh()->status);
    }

    public function test_a_cancelled_order_cannot_be_received(): void
    {
        [$buyer, $seller] = $this->people();
        $sc = $this->sc('sc@example.com');
        $order = $this->order($buyer, 'Cancelled');
        $byButton = $this->parcel($sc, $seller, $order, 'pickup_approved');

        $this->actingAs($sc)->post(route('sc.parcels.advance', $byButton))->assertSessionHas('error');
        $this->assertSame('cancelled', $byButton->fresh()->status);
        $this->assertNull($byButton->fresh()->received_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $seller->id, 'title' => 'Pickup cancelled']);

        $order2 = $this->order($buyer, 'Cancelled');
        $byScan = $this->parcel($sc, $seller, $order2, 'pickup_approved');
        $this->actingAs($sc)->post(route('sc.parcels.scan'), ['tracking_number' => $byScan->tracking_number])->assertSessionHas('error');
        $this->assertSame('cancelled', $byScan->fresh()->status);
    }

    public function test_buyer_cancellation_closes_the_sorting_center_request(): void
    {
        [$buyer, $seller] = $this->people();
        $sc = $this->sc('sc@example.com');

        foreach (['pending_pickup', 'pickup_approved'] as $status) {
            $order = $this->order($buyer, 'Processing');
            $parcel = $this->parcel($sc, $seller, $order, $status);

            $this->actingAs($buyer)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])
                ->assertSessionHas('success');
            $this->assertSame('Cancelled', $order->fresh()->status);
            $this->assertSame('cancelled', $parcel->fresh()->status, $status);
            $this->assertSame('Order was cancelled by the buyer.', $parcel->fresh()->failure_reason);
        }
        $this->assertSame(2, \App\Models\UserNotification::where('user_id', $sc->id)->where('title', 'Pickup cancelled')->count());
        $this->assertSame(2, \App\Models\UserNotification::where('user_id', $seller->id)->where('title', 'Pickup cancelled')->count());

        // Cancelled requests no longer show as pending pickups.
        $this->actingAs($sc)->get(route('sc.pickup-requests', ['tab' => 'pending']))->assertOk();
        $this->assertSame(0, Parcel::where('status', 'pending_pickup')->count());
    }

    public function test_transfer_guards_hold_and_acceptance(): void
    {
        [$buyer, $seller] = $this->people();
        $from = $this->sc('from@example.com', self::LUISIANA, 'Luisiana Center');
        $to   = $this->sc('to@example.com', '043415000', 'Magdalena Center');
        $noMun = $this->sc('nomun@example.com', null, 'Unassigned Center');

        $order = $this->order($buyer, 'Shipped');
        $area = \App\Models\DeliveryArea::create(['sorting_center_id' => $from->id, 'name' => 'San Antonio', 'code' => 'BGY-1',
            'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010']);
        $parcel = $this->parcel($from, $seller, $order, 'sorted', ['area_id' => $area->id, 'sorted_at' => now()]);
        $rider = Rider::forceCreate(['user_id' => $from->id, 'sorting_center_id' => $from->id, 'full_name' => 'R1',
            'area_id' => $area->id, 'application_status' => 'approved', 'is_active' => true]);

        $initiate = fn (User $as, Parcel $p, User $dest) => $this->actingAs($as)->from(route('sc.transfers'))
            ->post(route('sc.transfers.initiate'), ['parcel_id' => $p->id, 'to_sorting_center_id' => $dest->id]);

        // Invalid statuses are rejected.
        foreach (['pickup_approved', 'assigned', 'in_transit', 'delivered'] as $bad) {
            $p = $this->parcel($from, $seller, $this->order($buyer, 'Shipped'), $bad);
            $initiate($from, $p, $to)->assertSessionHasErrors('parcel_id');
            $this->assertNull($p->fresh()->transfer_status, $bad);
        }
        // Invalid destinations and non-owners are rejected.
        $initiate($from, $parcel, $noMun)->assertSessionHasErrors('to_sorting_center_id');
        $initiate($from, $parcel, $from)->assertSessionHasErrors('to_sorting_center_id');
        $initiate($to, $parcel, $from)->assertSessionHasErrors('parcel_id');
        $this->assertSame(0, ParcelTransfer::count());

        // Valid transfer: parcel is held.
        $initiate($from, $parcel, $to)->assertSessionHas('success');
        $this->assertSame('outgoing', $parcel->fresh()->transfer_status);
        $this->actingAs($from)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $rider->id])->assertSessionHas('error');
        $this->assertDatabaseCount('parcel_deliveries', 0);
        $this->actingAs($from)->get(route('sc.delivery-assignment'))->assertOk()->assertDontSee($parcel->tracking_number);
        $initiate($from, $parcel, $to)->assertSessionHasErrors('parcel_id'); // no double transfer

        // Only the destination can accept.
        $transfer = ParcelTransfer::firstOrFail();
        $this->actingAs($from)->post(route('sc.transfers.accept', $transfer))->assertForbidden();

        $this->actingAs($to)->post(route('sc.transfers.accept', $transfer))->assertSessionHas('success');
        $p = $parcel->fresh();
        $this->assertSame([(int) $to->id, 'picked_up', null, null, null],
            [(int) $p->current_sorting_center_id, $p->status, $p->area_id, $p->transfer_status, $p->sorted_at]);
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame('Shipped', $order->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $buyer->id, 'title' => 'Parcel transferred']);
        $this->actingAs($to)->get(route('sc.parcel-sorting'))->assertOk()->assertSee($parcel->tracking_number);
    }

    public function test_rejected_and_cancelled_transfers_release_the_hold(): void
    {
        [$buyer, $seller] = $this->people();
        $from = $this->sc('from@example.com', self::LUISIANA, 'Luisiana Center');
        $to   = $this->sc('to@example.com', '043415000', 'Magdalena Center');

        foreach (['reject' => $to, 'cancel' => $from] as $action => $actor) {
            $parcel = $this->parcel($from, $seller, $this->order($buyer, 'Shipped'), 'picked_up');
            $this->actingAs($from)->post(route('sc.transfers.initiate'), ['parcel_id' => $parcel->id, 'to_sorting_center_id' => $to->id]);
            $transfer = ParcelTransfer::where('parcel_id', $parcel->id)->firstOrFail();

            $this->actingAs($actor)->post(route("sc.transfers.{$action}", $transfer))->assertSessionHas('success');
            $this->assertNull($parcel->fresh()->transfer_status, $action);
            $this->assertSame((int) $from->id, (int) $parcel->fresh()->current_sorting_center_id);
            $this->assertSame('rejected', $transfer->fresh()->status);
        }
    }
}
