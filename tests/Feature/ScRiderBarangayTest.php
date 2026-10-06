<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\Parcel;
use App\Models\Rider;
use App\Models\User;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sorting Center Rider Management + Coverage Areas use the local PSA PSGC barangays of the
 * SC's assigned municipality, validated server-side.
 */
class ScRiderBarangayTest extends TestCase
{
    use RefreshDatabase;

    private const LUISIANA = '043412000';   // Luisiana, Laguna
    private const LAGUNA   = '043400000';

    private function sc(string $email = 'sc-luisiana@example.com', ?string $mun = self::LUISIANA): User
    {
        return User::forceCreate([
            'name' => 'Luisiana Center', 'email' => $email, 'password' => bcrypt('password'),
            'role' => 'sorting_center',
            'assigned_municipality' => $mun ? 'Luisiana' : null, 'assigned_municipality_code' => $mun,
            'assigned_province' => $mun ? 'Laguna' : null, 'assigned_province_code' => $mun ? self::LAGUNA : null,
        ]);
    }

    private function brgy(string $municipalityCode, string $name): array
    {
        $b = collect(app(PsgcDirectory::class)->barangaysByMunicipality($municipalityCode))->firstWhere('name', $name);
        $this->assertNotNull($b, "{$name} not found under {$municipalityCode}");
        return $b;
    }

    private function addRider(User $sc, array $fields)
    {
        return $this->actingAs($sc)->from(route('sc.riders'))->post(route('sc.riders.store'), $fields + ['full_name' => 'Juan Rider']);
    }

    public function test_luisiana_returns_all_of_its_psa_barangays_only(): void
    {
        $list = app(PsgcDirectory::class)->barangaysByMunicipality(self::LUISIANA);

        $this->assertCount(23, $list);
        foreach ($list as $b) {
            $this->assertSame('0403412000', $b['locality_psgc'], "{$b['name']} must be a child of Luisiana");
            $this->assertStringStartsWith('043412', $b['code']);
        }
        $names = array_column($list, 'name');
        foreach (['San Antonio', 'San Juan', 'De La Paz', 'Santo Tomas', 'Barangay Zone I'] as $n) {
            $this->assertContains($n, $names);
        }
        // Same list with the 10-digit PSGC key; unknown municipality -> null.
        $this->assertSame($list, app(PsgcDirectory::class)->barangaysByMunicipality('0403412000'));
        $this->assertNull(app(PsgcDirectory::class)->barangaysByMunicipality('000000000'));
    }

    public function test_rider_page_lists_every_luisiana_barangay_and_no_other_municipality(): void
    {
        $sc = $this->sc();
        $html = $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->getContent();

        foreach (app(PsgcDirectory::class)->barangaysByMunicipality(self::LUISIANA) as $b) {
            $this->assertStringContainsString('value="' . $b['psgc'] . '"', $html, $b['name']);
        }
        $this->assertSame(23, substr_count($html, 'value="0403412'));
        $this->assertStringNotContainsString('value="' . $this->brgy('043415000', 'Alipit')['psgc'] . '"', $html); // Magdalena
        $this->assertStringContainsString('Barangay (23 in Luisiana)', $html);
    }

    public function test_adding_a_rider_stores_the_full_psgc_chain_and_links_coverage(): void
    {
        $sc = $this->sc();
        $sanIsidro = $this->brgy(self::LUISIANA, 'San Isidro');

        $this->addRider($sc, ['barangay_psgc' => $sanIsidro['psgc'], 'phone' => '09170000000'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $rider = Rider::firstOrFail();
        $this->assertSame('040000000', $rider->region_code);
        $this->assertSame(self::LAGUNA, $rider->province_code);
        $this->assertSame(self::LUISIANA, $rider->municipality_code);
        $this->assertSame('043412013', $rider->barangay_code);
        $this->assertSame((int) $sc->id, (int) $rider->sorting_center_id);

        // A coverage area was created for the barangay with the same chain.
        $area = $rider->area;
        $this->assertSame('San Isidro', $area->name);
        $this->assertSame('043412013', $area->barangay_code);
        $this->assertSame(self::LUISIANA, $area->municipality_code);
        $this->assertSame(self::LAGUNA, $area->province_code);
        $this->assertSame('040000000', $area->region_code);

        // A second rider in the same barangay reuses that area.
        $this->addRider($sc, ['barangay_psgc' => $sanIsidro['psgc'], 'full_name' => 'Pedro Rider'])->assertSessionHasNoErrors();
        $this->assertSame(1, DeliveryArea::count());
        $this->assertSame(2, Rider::where('area_id', $area->id)->count());
    }

    public function test_barangay_from_another_laguna_municipality_is_rejected(): void
    {
        $sc = $this->sc();
        $this->addRider($sc, ['barangay_psgc' => $this->brgy('043415000', 'Alipit')['psgc']]) // Magdalena, Laguna
            ->assertSessionHasErrors(['barangay_psgc' => 'The selected barangay is not a barangay of Luisiana.']);
        $this->assertSame(0, Rider::count());
        $this->assertSame(0, DeliveryArea::count());
    }

    public function test_barangay_from_another_province_is_rejected(): void
    {
        $sc = $this->sc();
        $this->addRider($sc, ['barangay_psgc' => $this->brgy('042101000', 'Amuyong')['psgc']]) // Alfonso, Cavite
            ->assertSessionHasErrors('barangay_psgc');
        $this->assertSame(0, Rider::count());
    }

    public function test_tampered_and_malformed_barangay_codes_are_rejected(): void
    {
        $sc = $this->sc();
        $sanJuan = $this->brgy(self::LUISIANA, 'San Juan');

        $cases = [
            ['barangay_psgc' => '0403412099'],                                        // not a Luisiana barangay
            ['barangay_psgc' => $sanJuan['psgc'], 'barangay_code' => '043412016'],    // code of a different barangay
            ['barangay_psgc' => $sanJuan['psgc'], 'barangay_code' => '043415001'],    // Magdalena code
            ['barangay_psgc' => '043412015'],                                         // 9-digit in the psgc field
            ['barangay_psgc' => 'abc'],
            ['barangay_psgc' => ''],
            ['area_id' => 1],                                                         // old form field alone
        ];
        foreach ($cases as $fields) {
            $this->addRider($sc, $fields)->assertSessionHasErrors('barangay_psgc');
        }
        $this->assertSame(0, Rider::count());

        // The matching psgc + code pair is accepted.
        $this->addRider($sc, ['barangay_psgc' => $sanJuan['psgc'], 'barangay_code' => $sanJuan['code']])->assertSessionHasNoErrors();
        $this->assertSame('043412015', Rider::firstOrFail()->barangay_code);
    }

    public function test_sc_without_municipality_cannot_add_riders_or_areas(): void
    {
        $sc = $this->sc('no-mun@example.com', null);
        $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->assertSee('No municipality assigned');
        $this->addRider($sc, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Juan')['psgc']])->assertSessionHas('error');
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Juan')['psgc']])
            ->assertSessionHas('error');
        $this->assertSame(0, Rider::count() + DeliveryArea::count());
    }

    public function test_coverage_areas_use_the_psgc_list_and_validate_server_side(): void
    {
        $sc = $this->sc();
        $sanJuan = $this->brgy(self::LUISIANA, 'San Juan');

        $html = $this->actingAs($sc)->get(route('sc.areas'))->assertOk()->getContent();
        $this->assertSame(23, substr_count($html, 'value="0403412'));
        $this->assertStringContainsString('0 of 23 PSA barangays covered', $html);

        $this->actingAs($sc)->from(route('sc.areas'))->post(route('sc.areas.store'), ['barangay_psgc' => $sanJuan['psgc']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $area = DeliveryArea::firstOrFail();
        $this->assertSame(['San Juan', '043412015', self::LUISIANA, self::LAGUNA, '040000000'],
            [$area->name, $area->barangay_code, $area->municipality_code, $area->province_code, $area->region_code]);

        // Duplicate, other municipality, other province, tampered name-only post: all rejected.
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $sanJuan['psgc']])->assertSessionHas('error');
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $this->brgy('043415000', 'Alipit')['psgc']])->assertSessionHasErrors('barangay_psgc');
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $this->brgy('042101000', 'Amuyong')['psgc']])->assertSessionHasErrors('barangay_psgc');
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay' => 'Fake Barangay', 'barangay_code' => '043412015'])->assertSessionHasErrors('barangay_psgc');
        $this->assertSame(1, DeliveryArea::count());

        // Added barangay disappears from the dropdown; the table still shows it.
        $html = $this->actingAs($sc)->get(route('sc.areas'))->assertOk()->getContent();
        $this->assertSame(22, substr_count($html, 'value="0403412'));
        $this->assertStringContainsString('1 of 23 PSA barangays covered', $html);
    }

    public function test_existing_legacy_riders_and_areas_keep_working(): void
    {
        $sc = $this->sc();
        // Pre-migration shape: area with only municipality/barangay codes, rider with no codes.
        $areaId = DB::table('delivery_areas')->insertGetId([
            'sorting_center_id' => $sc->id, 'name' => 'San Antonio', 'code' => 'SANANT-' . $sc->id,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $legacyId = DB::table('riders')->insertGetId([
            'user_id' => $sc->id, 'sorting_center_id' => $sc->id, 'full_name' => 'Legacy Rider', 'area_id' => $areaId,
            'application_status' => 'approved', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->assertSee('Legacy Rider')->assertSee('In your coverage');
        $this->actingAs($sc)->get(route('sc.areas'))->assertOk()->assertSee('San Antonio');

        // Choosing San Antonio reuses the legacy area instead of creating a duplicate.
        $this->addRider($sc, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Antonio')['psgc']])->assertSessionHasNoErrors();
        $this->assertSame(1, DeliveryArea::count());
        $this->assertSame($areaId, (int) Rider::where('full_name', 'Juan Rider')->value('area_id'));

        // Legacy rider untouched, still toggles.
        $this->assertNull(Rider::find($legacyId)->barangay_code);
        $this->actingAs($sc)->post(route('sc.riders.toggle', $legacyId))->assertSessionHas('success');
        $this->assertFalse(Rider::find($legacyId)->is_active);
    }

    public function test_backfill_dry_run_changes_nothing_and_apply_fills_codes(): void
    {
        $sc = $this->sc();
        $areaId = DB::table('delivery_areas')->insertGetId([
            'sorting_center_id' => $sc->id, 'name' => 'San Antonio', 'code' => 'SANANT-' . $sc->id,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $nameOnly = DB::table('delivery_areas')->insertGetId([
            'sorting_center_id' => $sc->id, 'name' => 'san juan', 'code' => 'SANJUA-' . $sc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $riderId = DB::table('riders')->insertGetId([
            'user_id' => $sc->id, 'sorting_center_id' => $sc->id, 'full_name' => 'Legacy Rider', 'area_id' => $areaId,
            'application_status' => 'approved', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $updatedAt = DB::table('riders')->where('id', $riderId)->value('updated_at');

        Artisan::call('sc:backfill-psgc-codes', ['--dry-run' => true]);
        $this->assertStringContainsString('[DRY RUN] 3 record(s) would be updated', Artisan::output());
        $this->assertNull(DB::table('riders')->where('id', $riderId)->value('barangay_code'));
        $this->assertNull(DB::table('delivery_areas')->where('id', $areaId)->value('province_code'));

        Artisan::call('sc:backfill-psgc-codes');
        $rider = DB::table('riders')->find($riderId);
        $this->assertSame(['040000000', self::LAGUNA, self::LUISIANA, '043412010'],
            [$rider->region_code, $rider->province_code, $rider->municipality_code, $rider->barangay_code]);
        $this->assertSame($updatedAt, $rider->updated_at);
        $this->assertSame((int) $areaId, (int) $rider->area_id);
        $this->assertSame('043412015', DB::table('delivery_areas')->where('id', $nameOnly)->value('barangay_code'));
        $this->assertSame('san juan', DB::table('delivery_areas')->where('id', $nameOnly)->value('name')); // names untouched

        Artisan::call('sc:backfill-psgc-codes', ['--dry-run' => true]);
        $this->assertStringContainsString('0 record(s) would be updated', Artisan::output());
    }

    public function test_delivery_assignment_still_matches_rider_and_parcel_area(): void
    {
        $sc = $this->sc();
        $seller = User::forceCreate(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x'), 'role' => 'seller']);

        $this->addRider($sc, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Isidro')['psgc']])->assertSessionHasNoErrors();
        $this->addRider($sc, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Jose')['psgc'], 'full_name' => 'Other Rider'])->assertSessionHasNoErrors();
        $rider = Rider::where('full_name', 'Juan Rider')->firstOrFail();
        $other = Rider::where('full_name', 'Other Rider')->firstOrFail();

        $parcel = Parcel::forceCreate([
            'tracking_number' => 'TEST-0001', 'seller_id' => $seller->id,
            'pickup_address' => 'A', 'dropoff_address' => 'B', 'receiver_name' => 'C',
            'area_id' => $rider->area_id, 'status' => 'sorted', 'current_sorting_center_id' => $sc->id,
        ]);

        // A rider of a different barangay is still refused; the matching rider is assigned.
        $this->actingAs($sc)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $other->id])->assertSessionHas('error');
        $this->actingAs($sc)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $rider->id])->assertSessionHas('success');
        $this->assertSame('assigned', $parcel->fresh()->status);
        $this->assertDatabaseHas('parcel_deliveries', ['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'area_id' => $rider->area_id]);

        $this->actingAs($sc)->get(route('sc.delivery-assignment'))->assertOk();
        $this->actingAs($sc)->get(route('sc.parcel-sorting'))->assertOk();
    }

    public function test_another_sorting_center_cannot_use_or_see_luisiana_data(): void
    {
        $luisiana = $this->sc();
        $magdalena = User::forceCreate([
            'name' => 'Magdalena Center', 'email' => 'sc-mag@example.com', 'password' => bcrypt('password'),
            'role' => 'sorting_center', 'assigned_municipality' => 'Magdalena', 'assigned_municipality_code' => '043415000',
            'assigned_province' => 'Laguna', 'assigned_province_code' => self::LAGUNA,
        ]);
        $this->addRider($luisiana, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Juan')['psgc']])->assertSessionHasNoErrors();

        $this->addRider($magdalena, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Juan')['psgc']])->assertSessionHasErrors('barangay_psgc');
        $html = $this->actingAs($magdalena)->get(route('sc.riders'))->assertOk()->getContent();
        $this->assertSame(0, substr_count($html, 'value="0403412'));
        $this->assertStringContainsString('value="' . $this->brgy('043415000', 'Alipit')['psgc'] . '"', $html);
        $this->assertStringContainsString('No riders found.', $html);           // Luisiana's rider is not listed
        $this->assertStringNotContainsString('RDR-', $html);
    }
}
