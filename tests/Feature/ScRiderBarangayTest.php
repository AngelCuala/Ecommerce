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

class ScRiderBarangayTest extends TestCase
{
    use RefreshDatabase;

    private const LUISIANA = '043412000';
    private const LAGUNA   = '043400000';

    private function sc(string $email = 'sc@example.com', ?string $mun = self::LUISIANA): User
    {
        return User::forceCreate([
            'name' => 'Luisiana Center', 'email' => $email, 'password' => bcrypt('x'), 'role' => 'sorting_center',
            'approval_status' => 'approved',
            'assigned_municipality' => $mun ? 'Luisiana' : null, 'assigned_municipality_code' => $mun,
            'assigned_province' => $mun ? 'Laguna' : null, 'assigned_province_code' => $mun ? self::LAGUNA : null,
        ]);
    }

    private function brgy(string $munCode, string $name): array
    {
        $b = collect(app(PsgcDirectory::class)->barangaysByMunicipality($munCode))->firstWhere('name', $name);
        $this->assertNotNull($b, "{$name} not found under {$munCode}");
        return $b;
    }

    private function addRider(User $sc, array $fields)
    {
        return $this->actingAs($sc)->from(route('sc.riders'))->post(route('sc.riders.store'), $fields + ['full_name' => 'Test Rider']);
    }

    public function test_luisiana_returns_all_23_psa_barangays(): void
    {
        $list = app(PsgcDirectory::class)->barangaysByMunicipality(self::LUISIANA);
        $this->assertCount(23, $list);
        foreach ($list as $b) {
            $this->assertStringStartsWith('043412', $b['code']);
        }
        $this->assertContains('San Antonio', array_column($list, 'name'));
        $this->assertContains('San Juan', array_column($list, 'name'));
    }

    public function test_rider_page_shows_luisiana_barangays(): void
    {
        $sc   = $this->sc();
        $html = $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->getContent();
        $this->assertSame(23, substr_count($html, 'value="0403412'));
        $this->assertStringContainsString('23 in Luisiana', $html);
    }

    public function test_adding_rider_stores_psgc_chain_and_creates_coverage(): void
    {
        $sc      = $this->sc();
        $sanIsidro = $this->brgy(self::LUISIANA, 'San Isidro');

        $this->addRider($sc, ['barangay_psgc' => $sanIsidro['psgc']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $rider = Rider::firstOrFail();
        $this->assertSame('043412013', $rider->barangay_code);
        $this->assertSame(self::LUISIANA, $rider->municipality_code);
        $this->assertSame(self::LAGUNA, $rider->province_code);

        $area = $rider->area;
        $this->assertSame('San Isidro', $area->name);
        $this->assertSame('043412013', $area->barangay_code);

        // Second rider in same barangay reuses the area.
        $this->addRider($sc, ['barangay_psgc' => $sanIsidro['psgc'], 'full_name' => 'Other Rider'])->assertSessionHasNoErrors();
        $this->assertSame(1, DeliveryArea::count());
    }

    public function test_barangay_from_another_municipality_is_rejected(): void
    {
        $sc = $this->sc();
        $alipit = $this->brgy('043415000', 'Alipit');  // Magdalena, Laguna
        $this->addRider($sc, ['barangay_psgc' => $alipit['psgc']])->assertSessionHasErrors('barangay_psgc');
        $this->assertSame(0, Rider::count());
    }

    public function test_barangay_from_another_province_is_rejected(): void
    {
        $sc = $this->sc();
        $amuyong = $this->brgy('042101000', 'Amuyong');  // Alfonso, Cavite
        $this->addRider($sc, ['barangay_psgc' => $amuyong['psgc']])->assertSessionHasErrors('barangay_psgc');
        $this->assertSame(0, Rider::count());
    }

    public function test_tampered_codes_are_rejected(): void
    {
        $sc = $this->sc();
        foreach ([
            ['barangay_psgc' => '0403412099'],        // non-existent
            ['barangay_psgc' => 'abc'],                // malformed
            ['barangay_psgc' => ''],                   // empty
            ['area_id' => 1],                          // old form field
        ] as $fields) {
            $this->addRider($sc, $fields)->assertSessionHasErrors('barangay_psgc');
        }
        $this->assertSame(0, Rider::count());
    }

    public function test_sc_without_municipality_cannot_add_riders(): void
    {
        $sc = $this->sc('nomun@example.com', null);
        $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->assertSee('No municipality assigned');
        $this->addRider($sc, ['barangay_psgc' => $this->brgy(self::LUISIANA, 'San Juan')['psgc']])->assertSessionHas('error');
        $this->assertSame(0, Rider::count());
    }

    public function test_coverage_areas_uses_psgc_list_and_validates(): void
    {
        $sc     = $this->sc();
        $sanJuan = $this->brgy(self::LUISIANA, 'San Juan');

        $html = $this->actingAs($sc)->get(route('sc.areas'))->assertOk()->getContent();
        $this->assertSame(23, substr_count($html, 'value="0403412'));

        $this->actingAs($sc)->from(route('sc.areas'))
            ->post(route('sc.areas.store'), ['barangay_psgc' => $sanJuan['psgc']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('043412015', DeliveryArea::firstOrFail()->barangay_code);

        // Duplicate is rejected.
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $sanJuan['psgc']])->assertSessionHas('error');
        // Other municipality rejected.
        $this->actingAs($sc)->post(route('sc.areas.store'), ['barangay_psgc' => $this->brgy('043415000', 'Alipit')['psgc']])
            ->assertSessionHasErrors('barangay_psgc');
        $this->assertSame(1, DeliveryArea::count());
    }

    public function test_existing_legacy_riders_keep_working(): void
    {
        $sc = $this->sc();
        $areaId = DB::table('delivery_areas')->insertGetId([
            'sorting_center_id' => $sc->id, 'name' => 'San Antonio', 'code' => 'SANANT-'.$sc->id,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $riderId = DB::table('riders')->insertGetId([
            'user_id' => $sc->id, 'sorting_center_id' => $sc->id, 'full_name' => 'Legacy Rider',
            'area_id' => $areaId, 'application_status' => 'approved', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($sc)->get(route('sc.riders'))->assertOk()->assertSee('Legacy Rider')->assertSee('In your coverage');
        $this->actingAs($sc)->post(route('sc.riders.toggle', $riderId))->assertSessionHas('success');
        $this->assertFalse(Rider::find($riderId)->is_active);
        $this->assertNull(Rider::find($riderId)->barangay_code, 'legacy riders keep null codes');
    }

    public function test_backfill_fills_codes_without_changing_names_or_links(): void
    {
        $sc = $this->sc();
        $areaId = DB::table('delivery_areas')->insertGetId([
            'sorting_center_id' => $sc->id, 'name' => 'San Antonio', 'code' => 'SANANT-'.$sc->id,
            'municipality' => 'Luisiana', 'municipality_code' => self::LUISIANA, 'barangay_code' => '043412010',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $riderId = DB::table('riders')->insertGetId([
            'user_id' => $sc->id, 'sorting_center_id' => $sc->id, 'full_name' => 'Legacy',
            'area_id' => $areaId, 'application_status' => 'approved', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $updatedAt = DB::table('riders')->where('id', $riderId)->value('updated_at');

        Artisan::call('sc:backfill-psgc-codes', ['--dry-run' => true]);
        $this->assertStringContainsString('[DRY RUN]', Artisan::output());
        $this->assertNull(DB::table('riders')->find($riderId)->barangay_code);

        Artisan::call('sc:backfill-psgc-codes');
        $rider = DB::table('riders')->find($riderId);
        $this->assertSame(['040000000', self::LAGUNA, self::LUISIANA, '043412010'],
            [$rider->region_code, $rider->province_code, $rider->municipality_code, $rider->barangay_code]);
        $this->assertSame($updatedAt, $rider->updated_at, 'updated_at unchanged');

        Artisan::call('sc:backfill-psgc-codes', ['--dry-run' => true]);
        $this->assertStringContainsString('0 record(s)', Artisan::output());
    }
}
