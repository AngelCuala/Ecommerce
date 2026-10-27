<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Rider;
use App\Models\User;
use App\Rules\InServiceArea;
use App\Services\RiderDispatch;
use App\Support\ServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** ALVY operates in Laguna only: addresses, checkout, stores, couriers, sorting centers and riders. */
class ServiceAreaTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    private const CALAMBA = '043405000';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service_area.provinces' => ['043400000']]);   // Laguna (the production default)
        $this->seedBuyerTestData();
    }

    private function lagunaAddress(array $over = []): array
    {
        return $over + ['label' => 'Home', 'full_name' => 'Juan Dela Cruz', 'phone' => '09171234567',
            'region' => 'Region IV-A', 'province' => 'Laguna', 'city' => 'City of Calamba', 'barangay' => 'Bagong Kalsada',
            'address_line' => '12 Rizal St.', 'zip' => '4027', 'municipality_code' => self::CALAMBA, 'barangay_code' => '043405001'];
    }

    private function makatiAddress(): array
    {
        return ['label' => 'Office', 'full_name' => 'Juan Dela Cruz', 'phone' => '09171234567',
            'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'City of Makati', 'barangay' => 'San Lorenzo',
            'address_line' => '6750 Ayala Ave.', 'zip' => '1223', 'municipality_code' => '137602000', 'barangay_code' => '137602025'];
    }

    public function test_the_service_area_is_laguna_and_its_30_cities_and_municipalities(): void
    {
        $this->assertSame('Laguna', ServiceArea::label());
        $this->assertCount(30, ServiceArea::localities());
        $this->assertTrue(ServiceArea::coversLocality(self::CALAMBA));
        $this->assertTrue(ServiceArea::coversLocality('0403405000'));          // 10-digit PSGC form
        $this->assertFalse(ServiceArea::coversLocality('137404000'));          // Quezon City
        $this->assertTrue(ServiceArea::covers(null, 'Laguna', 'Calamba City'));
        $this->assertTrue(ServiceArea::covers(null, 'laguna', 'Santa Rosa'));
        $this->assertFalse(ServiceArea::covers(null, 'Laguna', 'Quezon City'));  // a non-Laguna city with a Laguna label
        $this->assertFalse(ServiceArea::covers('137404000', 'Laguna', 'Calamba')); // the code decides, not the typed names
    }

    public function test_buyer_can_only_save_a_laguna_delivery_address(): void
    {
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->lagunaAddress())->assertSessionHasNoErrors();
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->makatiAddress())
            ->assertSessionHasErrors(['city' => ServiceArea::message('delivery address')]);
        $this->assertSame(0, $this->buyer2->addresses()->where('city', 'City of Makati')->count());
    }

    public function test_an_older_address_outside_laguna_is_kept_but_cannot_be_used_at_checkout(): void
    {
        CartItem::create(['user_id' => $this->buyer1->id, 'product_id' => $this->p02->id, 'quantity' => 1]);

        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1))
            ->assertSessionHas('error', 'We only deliver within Laguna for now. Please choose or add a delivery address in Laguna.');
        $this->assertSame(0, $this->buyer1->orders()->count());
        $this->assertNotNull($this->addr1->fresh());                           // nothing was deleted
    }

    public function test_a_laguna_address_checks_out(): void
    {
        CartItem::create(['user_id' => $this->buyer1->id, 'product_id' => $this->p02->id, 'quantity' => 1]);
        $laguna = $this->addressFor($this->buyer1, self::CALAMBA, ['city' => 'City of Calamba', 'province' => 'Laguna', 'province_code' => '043400000']);

        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($laguna))
            ->assertRedirect(route('checkout.confirmation'));
        $this->assertSame(1, $this->buyer1->orders()->count());
    }

    public function test_address_dropdowns_offer_only_laguna(): void
    {
        $regions = $this->getJson('/api/psgc/regions')->assertOk()->json();
        $this->assertSame(['Region IV-A (CALABARZON)'], array_column($regions, 'name'));
        $provinces = $this->getJson('/api/psgc/regions/' . $regions[0]['key'] . '/provinces')->assertOk()->json();
        $this->assertSame(['Laguna'], array_column($provinces, 'name'));
        $this->assertSame(['Laguna'], array_column($this->getJson('/api/psgc/provinces')->json(), 'name'));
    }

    public function test_store_courier_and_hub_address_rules_use_the_same_check(): void
    {
        $rule = fn (array $data) => Validator::make($data, ['municipality' => [new InServiceArea('business address')]]);

        $this->assertTrue($rule(['province' => 'Laguna', 'municipality' => 'City of Biñan', 'municipality_code' => '043403000'])->passes());
        $this->assertTrue($rule(['province' => 'Laguna', 'municipality' => 'Los Baños'])->passes());
        $v = $rule(['province' => 'Cavite', 'municipality' => 'General Emilio Aguinaldo']);
        $this->assertTrue($v->fails());
        $this->assertSame(ServiceArea::message('business address'), $v->errors()->first('municipality'));
        // A tampered code can't sneak past with Laguna names typed in.
        $this->assertTrue($rule(['province' => 'Laguna', 'municipality' => 'Calamba', 'municipality_code' => '137404000'])->fails());
    }

    public function test_admin_cannot_put_a_sorting_center_outside_laguna(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@e.com', 'password' => bcrypt('x'), 'role' => 'admin', 'approval_status' => 'approved']);
        $sc = User::forceCreate(['name' => 'Hub', 'email' => 'hub@e.com', 'password' => bcrypt('x'), 'role' => 'sorting_center', 'approval_status' => 'approved']);
        $this->actingAs($admin)->from('/back')->patch(route('admin.users.assign-municipality', $sc->id),
            ['province' => 'Metro Manila', 'municipality' => 'Quezon City', 'municipality_code' => '137404000'])
            ->assertSessionHasErrors('municipality');
        $this->assertNull($sc->fresh()->assigned_municipality_code);

        $this->actingAs($admin)->from('/back')->patch(route('admin.users.assign-municipality', $sc->id),
            ['province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'City of Calamba', 'municipality_code' => self::CALAMBA])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::CALAMBA, $sc->fresh()->assigned_municipality_code);
    }

    public function test_only_sorting_centers_in_laguna_dispatch_riders_and_take_courier_applicants(): void
    {
        $laguna = User::forceCreate(['name' => 'Calamba Hub', 'email' => 'cal@e.com', 'password' => bcrypt('x'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'assigned_municipality' => 'City of Calamba', 'assigned_municipality_code' => self::CALAMBA]);
        $mindoro = User::forceCreate(['name' => 'Mindoro Hub', 'email' => 'min@e.com', 'password' => bcrypt('x'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'assigned_municipality' => 'Puerto Galera', 'assigned_municipality_code' => '175209000']);
        $rider = fn (User $sc, string $email) => Rider::create(['user_id' => $this->account('courier', $email, 'Rider', 'Rider@2026')->id,
            'sorting_center_id' => $sc->id, 'full_name' => 'Rider ' . $email, 'phone' => '09170000011', 'vehicle_type' => 'Motorcycle',
            'plate_number' => 'ABC' . strlen($email), 'application_status' => 'approved', 'approved_at' => now(), 'is_active' => true,
            'is_online' => true, 'last_seen_at' => now()]);
        $r1 = $rider($laguna, 'r1@e.com');
        $r2 = $rider($mindoro, 'r2@e.com');

        $dispatch = app(RiderDispatch::class);
        $this->assertSame([$r1->id], $dispatch->eligibleRiders($laguna->id)->pluck('id')->all());
        $this->assertSame([], $dispatch->eligibleRiders($mindoro->id)->all());
        $this->assertStringContainsString('outside the service area', $dispatch->ineligibleReason($r2, $mindoro->id));

        $this->assertSame([$laguna->id], \App\Support\SortingCenters::forCouriers()->pluck('id')->all());
    }

    public function test_turning_the_restriction_off_allows_everywhere(): void
    {
        config(['service_area.provinces' => []]);
        $this->assertFalse(ServiceArea::enabled());
        $this->assertTrue(ServiceArea::coversLocality('137404000'));
    }
}
