<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Local PSA PSGC dataset (resources/data/psgc, 2Q 2026): hierarchy, special cases,
 * the 9-digit codes delivery routing depends on, and server-side validation.
 */
class PsgcDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function dir(): PsgcDirectory
    {
        return app(PsgcDirectory::class);
    }

    /** Find a list item by exact name. */
    private function pick(array $list, string $name): array
    {
        $hit = collect($list)->firstWhere('name', $name);
        $this->assertNotNull($hit, "Expected '{$name}' in: " . collect($list)->pluck('name')->take(30)->implode(', '));
        return $hit;
    }

    private function regionKey(string $name): string
    {
        return $this->pick($this->getJson('/api/psgc/regions')->assertOk()->json(), $name)['key'];
    }

    public function test_dataset_counts_match_the_psa_national_summary(): void
    {
        $meta = json_decode(file_get_contents(resource_path('data/psgc/meta.json')), true);
        $this->assertSame('30 June 2026', $meta['publication_date']);
        $this->assertSame(18, $meta['counts']['regions']);
        $this->assertSame(82, $meta['counts']['provinces']);
        $this->assertSame(149, $meta['counts']['cities']);
        $this->assertSame(1493, $meta['counts']['municipalities']);
        $this->assertSame(14, $meta['counts']['sub_municipalities']);
        $this->assertSame(42010, $meta['counts']['barangays']);
        $this->assertCount(18, $this->getJson('/api/psgc/regions')->json());
    }

    public function test_existing_routing_codes_resolve_to_the_same_municipalities(): void
    {
        $expected = [ // [code, name] pairs (string keys like '175211000' would become ints)
            ['043415000', 'Magdalena'], ['043407000', 'Cavinti'], ['175211000', 'Puerto Galera'],
            ['034910000', 'General Tinio'], ['043412000', 'Luisiana'],
        ];
        foreach ($expected as [$code, $name]) {
            $loc = $this->dir()->locality($code);
            $this->assertNotNull($loc, $code);
            $this->assertSame($name, $loc['name']);
            $this->assertSame($code, $loc['code']);
        }
    }

    public function test_calabarzon_laguna_magdalena_and_its_barangays(): void
    {
        $laguna = $this->pick($this->getJson('/api/psgc/regions/' . $this->regionKey('Region IV-A (CALABARZON)') . '/provinces')->json(), 'Laguna');
        $this->assertSame('043400000', $laguna['code']);
        $this->assertSame('0403400000', $laguna['psgc']);

        $mag = $this->pick($this->getJson("/api/psgc/provinces/{$laguna['key']}/municipalities")->json(), 'Magdalena');
        $this->assertSame('043415000', $mag['code']);
        $this->assertSame('0403415000', $mag['psgc']);
        $this->assertSame('Mun', $mag['level']);

        $brgys = $this->getJson("/api/psgc/municipalities/{$mag['key']}/barangays")->assertOk()->json();
        $this->assertCount(24, $brgys);
        $this->pick($brgys, 'Alipit');

        // The 9-digit code works as a route parameter too (existing callers).
        $this->assertCount(24, $this->getJson('/api/psgc/municipalities/043415000/barangays')->json());
    }

    public function test_ncr_has_no_provinces_and_lists_its_cities_and_pateros_directly(): void
    {
        $ncr = $this->regionKey('National Capital Region (NCR)');
        $this->assertSame([], $this->getJson("/api/psgc/regions/{$ncr}/provinces")->assertOk()->json());

        $cities = $this->getJson("/api/psgc/provinces/{$ncr}/municipalities")->assertOk()->json();
        $this->assertCount(17, $cities);
        $manila = $this->pick($cities, 'City of Manila');
        $this->assertSame('133900000', $manila['code']);
        $this->assertSame('HUC', $manila['city_class']);
        $this->assertNull($manila['province_code']);
        $qc = $this->pick($cities, 'Quezon City');
        $this->assertSame('137404000', $qc['code']);
        $pateros = $this->pick($cities, 'Pateros');
        $this->assertSame('Mun', $pateros['level']);
    }

    public function test_manila_barangays_resolve_through_their_sub_municipality(): void
    {
        $brgys = $this->getJson('/api/psgc/municipalities/133900000/barangays')->assertOk()->json();
        $this->assertCount(897, $brgys);
        $this->assertCount(14, collect($brgys)->pluck('district')->unique());

        $b = collect($brgys)->firstWhere('district', 'Tondo I/II');
        $this->assertSame('1380601000', $b['district_psgc']);
        $this->assertSame('1380600000', $b['locality_psgc']);

        $addr = $this->dir()->resolve(['municipality_code' => '133900000', 'barangay_psgc' => $b['psgc']]);
        $this->assertSame('City of Manila', $addr['municipality']);
        $this->assertSame('Tondo I/II', $addr['district']);
        $this->assertNull($addr['province_code']);
        $this->assertSame('National Capital Region (NCR)', $addr['province_display']);
    }

    public function test_highly_urbanized_cities_sit_directly_under_their_region(): void
    {
        foreach ([
            ['Cordillera Administrative Region (CAR)', 'City of Baguio'],
            ['Region III (Central Luzon)', 'City of Angeles'],
            ['Negros Island Region (NIR)', 'City of Bacolod'],
        ] as [$regionName, $cityName]) {
            $region = $this->regionKey($regionName);
            $provinces = $this->getJson("/api/psgc/regions/{$region}/provinces")->json();
            $group = collect($provinces)->firstWhere('level', 'Group');
            $this->assertNotNull($group, "{$regionName} should offer its highly urbanized cities");
            $this->assertNull($group['code']);

            $city = $this->pick($this->getJson("/api/psgc/provinces/{$group['key']}/municipalities")->json(), $cityName);
            $this->assertSame('HUC', $city['city_class']);
            $this->assertNull($city['province_psgc']);

            // ...and not under their geographic province.
            foreach ($provinces as $p) {
                if ($p['level'] === 'Group') continue;
                $names = collect($this->getJson("/api/psgc/provinces/{$p['key']}/municipalities")->json())->pluck('name');
                $this->assertNotContains($cityName, $names, "{$cityName} wrongly listed under {$p['name']}");
            }
        }
    }

    public function test_nir_is_its_own_region_with_three_provinces(): void
    {
        $names = collect($this->getJson('/api/psgc/regions/' . $this->regionKey('Negros Island Region (NIR)') . '/provinces')->json())
            ->where('level', 'Prov')->pluck('name')->sort()->values()->all();
        $this->assertSame(['Negros Occidental', 'Negros Oriental', 'Siquijor'], $names);
    }

    public function test_barmm_cotabato_city_is_under_maguindanao_del_norte(): void
    {
        $provinces = $this->getJson('/api/psgc/regions/' . $this->regionKey('Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)') . '/provinces')->json();
        $mdn = $this->pick($provinces, 'Maguindanao del Norte');
        $this->assertNull($mdn['code'], 'PSA publishes no correspondence code for Maguindanao del Norte');

        $cot = $this->pick($this->getJson("/api/psgc/provinces/{$mdn['key']}/municipalities")->json(), 'City of Cotabato');
        $this->assertSame('ICC', $cot['city_class']);
        $this->assertSame('129804000', $cot['code']);
    }

    public function test_barmm_special_geographic_area(): void
    {
        $provinces = $this->getJson('/api/psgc/regions/' . $this->regionKey('Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)') . '/provinces')->json();
        $sga = $this->pick($provinces, 'Special Geographic Area');
        $this->assertSame('Special', $sga['level']);

        $muns = $this->getJson("/api/psgc/provinces/{$sga['key']}/municipalities")->json();
        $this->assertCount(8, $muns);
        $kap = $this->pick($muns, 'Kapalawan');
        $this->assertNull($kap['code'], 'no correspondence code is invented');

        $brgy = $this->getJson("/api/psgc/municipalities/{$kap['key']}/barangays")->json()[0];
        $addr = $this->dir()->resolve(['province_psgc' => $sga['psgc'], 'municipality_psgc' => $kap['psgc'], 'barangay_psgc' => $brgy['psgc']]);
        $this->assertSame('Kapalawan', $addr['municipality']);
        $this->assertNull($addr['municipality_code']);
    }

    public function test_region_ix_city_of_isabela_and_sulu(): void
    {
        $provinces = $this->getJson('/api/psgc/regions/' . $this->regionKey('Region IX (Zamboanga Peninsula)') . '/provinces')->json();

        $sulu = $this->pick($provinces, 'Sulu');
        $this->assertSame('Prov', $sulu['level']);
        $this->assertSame('0900000000', $sulu['region_psgc']);

        $isabelaParent = $this->pick($provinces, 'City of Isabela (Not a Province)');
        $isabela = $this->pick($this->getJson("/api/psgc/provinces/{$isabelaParent['key']}/municipalities")->json(), 'City of Isabela');
        $this->assertSame('0900000000', $isabela['region_psgc']);

        // Not under Basilan (BARMM).
        $basilan = $this->pick($this->getJson('/api/psgc/regions/' . $this->regionKey('Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)') . '/provinces')->json(), 'Basilan');
        $this->assertNotContains('City of Isabela', collect($this->getJson("/api/psgc/provinces/{$basilan['key']}/municipalities")->json())->pluck('name'));
    }

    public function test_valid_chain_returns_official_names_and_codes(): void
    {
        $brgy = $this->getJson('/api/psgc/municipalities/043415000/barangays')->json()[0];
        $addr = $this->dir()->resolve([
            'region_psgc' => '0400000000', 'province_code' => '043400000',
            'municipality_code' => '043415000', 'barangay_psgc' => $brgy['psgc'],
        ]);
        $this->assertSame('Region IV-A (CALABARZON)', $addr['region']);
        $this->assertSame('Laguna', $addr['province']);
        $this->assertSame('043400000', $addr['province_code']);
        $this->assertSame('Magdalena', $addr['municipality']);
        $this->assertSame('043415000', $addr['municipality_code']);
    }

    public function test_laguna_municipality_with_cavite_province_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->dir()->resolve(['province_code' => '042100000', 'municipality_code' => '043415000'], false); // Cavite + Magdalena
    }

    public function test_barangay_from_another_municipality_is_rejected(): void
    {
        $cavintiBrgy = $this->getJson('/api/psgc/municipalities/043407000/barangays')->json()[0];
        $this->expectException(ValidationException::class);
        $this->dir()->resolve(['municipality_code' => '043415000', 'barangay_psgc' => $cavintiBrgy['psgc']]);
    }

    public function test_province_code_for_an_ncr_city_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->dir()->resolve(['province_code' => '043400000', 'municipality_code' => '137404000'], false); // Laguna + Quezon City
    }

    public function test_saved_address_endpoint_rejects_a_tampered_chain(): void
    {
        $user = User::forceCreate([
            'name' => 'PSGC Test', 'email' => 'psgc-test@example.com',
            'password' => bcrypt('password'), 'role' => 'buyer',
        ]);

        $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Home', 'full_name' => 'PSGC Test', 'address_line' => '1 Test St',
            'province' => 'Cavite', 'province_code' => '042100000',      // Cavite
            'city' => 'Magdalena', 'municipality_code' => '043415000',  // Laguna municipality
        ])->assertSessionHasErrors('province');
        $this->assertDatabaseCount('user_addresses', 0);

        $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Home', 'full_name' => 'PSGC Test', 'address_line' => '1 Test St',
            'province' => 'Laguna', 'province_code' => '043400000',
            'city' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Alipit',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_addresses', ['city' => 'Magdalena', 'province' => 'Laguna', 'barangay' => 'Alipit']);
    }

    /**
     * The requested end-to-end cases, each resolved down to a real barangay and validated
     * server-side by PSGC code. HUCs (Baguio, Angeles, Bacolod) sit directly under their
     * region in PSA data — e.g. Benguet is published "(excluding CITY OF BAGUIO)".
     */
    public function test_requested_cascades_resolve_to_valid_barangays(): void
    {
        $regions = $this->getJson('/api/psgc/regions')->json();
        $provincesOf = fn (string $region) => $this->getJson('/api/psgc/regions/' . $this->pick($regions, $region)['key'] . '/provinces')->json();
        $citiesOf    = fn (string $key) => $this->getJson("/api/psgc/provinces/{$key}/municipalities")->json();
        $brgysOf     = fn (string $key) => $this->getJson("/api/psgc/municipalities/{$key}/barangays")->json();

        $cases = [
            // [region, province-level choice (name or 'GROUP' for region-level cities, null for NCR), city, notUnderProvince]
            ['Region IV-A (CALABARZON)', 'Laguna', 'Magdalena', null],
            ['Region IV-A (CALABARZON)', 'Laguna', 'Cavinti', null],
            ['National Capital Region (NCR)', null, 'City of Manila', null],
            ['National Capital Region (NCR)', null, 'Quezon City', null],
            ['Cordillera Administrative Region (CAR)', 'GROUP', 'City of Baguio', 'Benguet'],
            ['Region III (Central Luzon)', 'GROUP', 'City of Angeles', 'Pampanga'],
            ['Negros Island Region (NIR)', 'GROUP', 'City of Bacolod', 'Negros Occidental'],
            ['Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)', 'Maguindanao del Norte', 'City of Cotabato', null],
            ['Region IX (Zamboanga Peninsula)', 'Sulu', null, null],
            ['Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)', 'Special Geographic Area', null, null],
            ['Region IX (Zamboanga Peninsula)', 'City of Isabela (Not a Province)', 'City of Isabela', null],
        ];

        foreach ($cases as [$regionName, $provName, $cityName, $notUnder]) {
            $provinces = $provincesOf($regionName);
            if ($provName === null) {
                $this->assertSame([], $provinces, "{$regionName} has no provinces");
                $parentKey = $this->pick($regions, $regionName)['key'];
                $prov = null;
            } else {
                $prov = $provName === 'GROUP' ? collect($provinces)->firstWhere('level', 'Group') : $this->pick($provinces, $provName);
                $this->assertNotNull($prov, "{$regionName} → {$provName}");
                $parentKey = $prov['key'];
            }

            $cities = $citiesOf($parentKey);
            $this->assertNotEmpty($cities, "{$regionName} → {$provName} has cities/municipalities");
            $city = $cityName ? $this->pick($cities, $cityName) : $cities[0];

            if ($notUnder) {
                $this->assertNotContains($city['name'], collect($citiesOf($this->pick($provinces, $notUnder)['key']))->pluck('name'),
                    "{$city['name']} must not be under {$notUnder}");
            }

            $brgy = $brgysOf($city['key'])[0] ?? null;
            $this->assertNotNull($brgy, "{$city['name']} has barangays");
            if ($cityName === 'City of Manila') {
                $this->assertNotNull($brgy['district_psgc'], 'Manila barangays sit under a sub-municipality');
            }

            $addr = $this->dir()->resolve([
                'region_psgc'       => $city['region_psgc'],
                'province_psgc'     => $prov['psgc'] ?? '',
                'municipality_psgc' => $city['psgc'],
                'municipality_code' => $city['code'] ?? '',
                'barangay_psgc'     => $brgy['psgc'],
            ]);
            $this->assertSame($city['name'], $addr['municipality']);
            $this->assertSame($brgy['name'], $addr['barangay']);
            $this->assertSame($city['code'], $addr['municipality_code']);
        }

        // Magdalena → Alipit specifically.
        $alipit = $this->pick($brgysOf('043415000'), 'Alipit');
        $addr = $this->dir()->resolve(['province_code' => '043400000', 'municipality_code' => '043415000', 'barangay_psgc' => $alipit['psgc']]);
        $this->assertSame('Alipit', $addr['barangay']);
    }

    public function test_unknown_codes_return_404(): void
    {
        $this->getJson('/api/psgc/regions/999999999/provinces')->assertNotFound();
        $this->getJson('/api/psgc/municipalities/000000000/barangays')->assertNotFound();
    }
}
