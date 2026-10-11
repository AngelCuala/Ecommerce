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
        $expected = [
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
        $mag = $this->pick($this->getJson("/api/psgc/provinces/{$laguna['key']}/municipalities")->json(), 'Magdalena');
        $this->assertSame('043415000', $mag['code']);
        $brgys = $this->getJson("/api/psgc/municipalities/{$mag['key']}/barangays")->assertOk()->json();
        $this->assertCount(24, $brgys);
        $this->pick($brgys, 'Alipit');
        $this->assertCount(24, $this->getJson('/api/psgc/municipalities/043415000/barangays')->json());
    }

    public function test_ncr_has_no_provinces_and_lists_its_cities_directly(): void
    {
        $ncr = $this->regionKey('National Capital Region (NCR)');
        $this->assertSame([], $this->getJson("/api/psgc/regions/{$ncr}/provinces")->assertOk()->json());
        $cities = $this->getJson("/api/psgc/provinces/{$ncr}/municipalities")->assertOk()->json();
        $this->assertCount(17, $cities);
        $this->pick($cities, 'City of Manila');
        $this->pick($cities, 'Pateros');
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
        $this->assertSame('Magdalena', $addr['municipality']);
        $this->assertSame('043415000', $addr['municipality_code']);
    }

    public function test_laguna_municipality_with_cavite_province_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->dir()->resolve(['province_code' => '042100000', 'municipality_code' => '043415000'], false);
    }

    public function test_barangay_from_another_municipality_is_rejected(): void
    {
        $cavintiBrgy = $this->getJson('/api/psgc/municipalities/043407000/barangays')->json()[0];
        $this->expectException(ValidationException::class);
        $this->dir()->resolve(['municipality_code' => '043415000', 'barangay_psgc' => $cavintiBrgy['psgc']]);
    }

    public function test_unknown_codes_return_404(): void
    {
        $this->getJson('/api/psgc/regions/999999999/provinces')->assertNotFound();
        $this->getJson('/api/psgc/municipalities/000000000/barangays')->assertNotFound();
    }

    public function test_saved_address_endpoint_rejects_a_tampered_chain(): void
    {
        $user = User::forceCreate([
            'name' => 'PSGC Test', 'email' => 'psgc-test@example.com',
            'password' => bcrypt('password'), 'role' => 'buyer', 'approval_status' => 'approved',
        ]);
        $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Home', 'full_name' => 'PSGC Test', 'address_line' => '1 Test St',
            'province' => 'Cavite', 'province_code' => '042100000',
            'city' => 'Magdalena', 'municipality_code' => '043415000',
        ])->assertSessionHasErrors('province');
        $this->assertDatabaseCount('user_addresses', 0);

        $this->actingAs($user)->post(route('addresses.store'), [
            'label' => 'Home', 'full_name' => 'PSGC Test', 'address_line' => '1 Test St',
            'province' => 'Laguna', 'province_code' => '043400000',
            'city' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Alipit',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_addresses', ['city' => 'Magdalena', 'province' => 'Laguna', 'barangay' => 'Alipit']);
    }
}
