<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Local Philippine location directory built from the official PSA PSGC workbook
 * (see `php artisan psgc:import`; data in resources/data/psgc/).
 *
 * Two codes per location:
 *   psgc  10-digit official PSGC code — unique, always present, used for the hierarchy.
 *   code  9-digit Correspondence Code — the value the app STORES (province_code,
 *         municipality_code, barangay_code) and that delivery routing compares.
 *         Null when PSA publishes none (it is never invented).
 *
 * Hierarchy (as published by PSA):
 *   Region → Province → City/Municipality → Barangay
 *   Region → City/Municipality (NCR cities, Pateros, highly urbanized cities: parent = null)
 *   Region → "City of Isabela (Not a Province)" / "Special Geographic Area" (special parents)
 *   City of Manila → Sub-municipality (district) → Barangay
 */
class PsgcDirectory
{
    private array $regions = [];      // psgc => row
    private array $provinces = [];    // psgc => row (type: province | special)
    private array $localities = [];   // psgc => row
    private array $districts = [];    // psgc => row
    private array $byCode = [];       // 9-digit code => psgc (regions, provinces, localities)
    private array $barangayCache = [];

    public function __construct(?string $dir = null)
    {
        $dir ??= resource_path('data/psgc');
        $load = fn (string $f) => json_decode((string) file_get_contents("$dir/$f"), true) ?: [];

        foreach ($load('regions.json') as $r)    $this->regions[$r['psgc']] = $r;
        foreach ($load('provinces.json') as $p)  $this->provinces[$p['psgc']] = $p;
        foreach ($load('localities.json') as $l) $this->localities[$l['psgc']] = $l;
        foreach ($load('districts.json') as $d)  $this->districts[$d['psgc']] = $d;

        foreach ([$this->regions, $this->provinces, $this->localities] as $set) {
            foreach ($set as $row) if ($row['code']) $this->byCode[$row['code']] = (string) $row['psgc'];
        }
        $this->dir = $dir;
    }

    private string $dir;

    // ── Lookups ───────────────────────────────────────────────

    /** Accepts a 10-digit PSGC code or a 9-digit Correspondence Code. */
    public function key(?string $value): ?string
    {
        $v = trim((string) $value);
        if (strlen($v) === 10 && ctype_digit($v)) return $v;
        if (strlen($v) === 9 && ctype_digit($v)) return isset($this->byCode[$v]) ? (string) $this->byCode[$v] : null;
        return null;
    }

    public function region(?string $key): ?array   { $k = $this->key($key); return $k ? ($this->regions[$k] ?? null) : null; }
    public function province(?string $key): ?array { $k = $this->key($key); return $k ? ($this->provinces[$k] ?? null) : null; }
    public function locality(?string $key): ?array { $k = $this->key($key); return $k ? ($this->localities[$k] ?? null) : null; }

    /** Barangays of a city/municipality (Manila's resolve through its districts). */
    public function barangaysOf(array $locality): array
    {
        $region = $locality['region'];
        if (! isset($this->barangayCache[$region])) {
            $file = "{$this->dir}/barangays/{$region}.json";
            $this->barangayCache[$region] = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        }
        return $this->barangayCache[$region][$locality['psgc']] ?? [];
    }

    public function districtName(?string $psgc): ?string
    {
        return $psgc ? ($this->districts[$psgc]['name'] ?? null) : null;
    }

    // ── Lists for the dropdowns ───────────────────────────────

    public function regions(): array
    {
        return array_map(fn ($r) => $this->item($r, 'Reg'), array_values($this->regions));
    }

    /**
     * Province-level choices for a region: provinces, PSA special parents, and — when the
     * region also has cities directly under it (HUCs) — a "Highly Urbanized Cities" group.
     * NCR has no provinces, so it returns [] and the city list is loaded from the region.
     */
    public function provincesOfRegion(array $region): array
    {
        $list = [];
        foreach ($this->provinces as $p) {
            if ($p['region'] === $region['psgc']) $list[] = $this->item($p, $p['type'] === 'special' ? 'Special' : 'Prov');
        }
        usort($list, fn ($a, $b) => strcmp($a['name'], $b['name']));

        if ($list && $this->regionLevelLocalities($region)) {
            $list[] = $this->groupItem($region, 'Highly Urbanized Cities (not under a province)');
        }
        return $list;
    }

    /** Every province-level choice nationwide (for forms without a region dropdown). */
    public function allProvinceChoices(): array
    {
        $list = [];
        foreach ($this->provinces as $p) $list[] = $this->item($p, $p['type'] === 'special' ? 'Special' : 'Prov');
        foreach ($this->regions as $r) {
            if (! $this->regionLevelLocalities($r)) continue;
            $label = $this->hasProvinces($r)
                ? 'Highly Urbanized Cities — ' . $r['name']
                : $r['name'];                               // NCR: its cities sit directly under the region
            $list[] = $this->groupItem($r, $label);
        }
        usort($list, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $list;
    }

    /** Cities/municipalities under a province, special parent, or (region key) directly under a region. */
    public function localitiesOf(string $key): ?array
    {
        $k = $this->key($key);
        if (! $k) return null;

        if (isset($this->regions[$k])) {
            $rows = $this->regionLevelLocalities($this->regions[$k]);
        } elseif (isset($this->provinces[$k])) {
            $rows = array_filter($this->localities, fn ($l) => $l['parent'] === $k);
        } else {
            return null;
        }

        $list = array_map(fn ($l) => $this->localityItem($l), array_values($rows));
        usort($list, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $list;
    }

    public function barangayItems(array $locality): array
    {
        $list = array_map(fn ($b) => [
            'psgc'          => $b['psgc'],
            'code'          => $b['code'],
            'name'          => $b['name'],
            'level'         => 'Bgy',
            'district'      => $this->districtName($b['district']),
            'district_psgc' => $b['district'],
            'locality_psgc' => $locality['psgc'],
            'locality_code' => $locality['code'],
        ], $this->barangaysOf($locality));

        usort($list, fn ($a, $b) => [$a['district'] ?? '', $a['name']] <=> [$b['district'] ?? '', $b['name']]);
        return $list;
    }

    // ── Hierarchy validation ──────────────────────────────────

    /** Format rules for the hidden code inputs every address form submits. */
    public static function codeRules(): array
    {
        $nine = ['nullable', 'regex:/^\d{9}$/'];   // Correspondence Code (stored)
        $ten  = ['nullable', 'regex:/^\d{10}$/'];  // 10-digit PSGC code
        return [
            'region_code' => $nine, 'province_code' => $nine, 'municipality_code' => $nine, 'barangay_code' => $nine,
            'region_psgc' => $ten,  'province_psgc' => $ten,  'municipality_psgc' => $ten,  'barangay_psgc' => $ten,
        ];
    }

    /**
     * Resolve and validate a submitted address. The city/municipality is located from
     * municipality_psgc or municipality_code; every other submitted value (region,
     * province, barangay — psgc and/or 9-digit code) must belong to it, otherwise a
     * ValidationException is thrown. Names in the result are the official PSA names.
     *
     * @param array $in      region_psgc, province_psgc, municipality_psgc, barangay_psgc,
     *                       region_code, province_code, municipality_code, barangay_code
     * @param array $fields  form field names for error messages: province, municipality, barangay
     */
    public function resolve(array $in, bool $requireBarangay = true, ?string $barangayName = null, array $fields = []): array
    {
        $f = $fields + ['province' => 'province', 'municipality' => 'municipality', 'barangay' => 'barangay'];
        $fail = fn (string $field, string $msg) => throw ValidationException::withMessages([$field => $msg]);
        $val  = fn (string $k) => trim((string) ($in[$k] ?? ''));

        // City / municipality
        $loc = $this->locality($val('municipality_psgc')) ?? $this->locality($val('municipality_code'));
        if (! $loc) {
            $fail($f['municipality'], 'Please select a valid city / municipality from the list.');
        }
        if ($val('municipality_psgc') !== '' && $val('municipality_psgc') !== $loc['psgc']) {
            $fail($f['municipality'], 'The selected city / municipality is not valid.');
        }
        if ($val('municipality_code') !== '' && $val('municipality_code') !== (string) $loc['code']) {
            $fail($f['municipality'], 'The city / municipality code does not match the selected city / municipality.');
        }

        // Region
        $region = $this->regions[$loc['region']] ?? null;
        foreach (['region_psgc' => 'psgc', 'region_code' => 'code'] as $k => $col) {
            if ($val($k) !== '' && $val($k) !== (string) $region[$col]) {
                $fail($f['province'], 'The selected city / municipality does not belong to the selected region.');
            }
        }

        // Province (or region-level group / special parent)
        $parent = $loc['parent'] ? ($this->provinces[$loc['parent']] ?? null) : null;
        $pPsgc  = $val('province_psgc');
        $pCode  = $val('province_code');
        if ($parent) {
            if (($pPsgc !== '' && $pPsgc !== $parent['psgc']) || ($pCode !== '' && $pCode !== (string) $parent['code'])) {
                $fail($f['province'], 'The selected city / municipality does not belong to the selected province.');
            }
        } else {
            // Directly under the region: no province; the region's "group" key is also accepted.
            if (($pPsgc !== '' && $pPsgc !== $region['psgc']) || $pCode !== '') {
                $fail($f['province'], 'The selected city / municipality is not under a province.');
            }
        }

        // Barangay
        $brgy = null;
        $bPsgc = $val('barangay_psgc');
        $bCode = $val('barangay_code');
        $bName = trim((string) $barangayName);
        if ($bPsgc !== '' || $bCode !== '' || $bName !== '') {
            $list = $this->barangaysOf($loc);
            if ($bPsgc !== '') {
                $brgy = collect($list)->firstWhere('psgc', $bPsgc);
            } elseif ($bCode !== '') {
                $brgy = collect($list)->firstWhere('code', $bCode);
            } else {
                $matches = array_values(array_filter($list, fn ($b) => strcasecmp($b['name'], $bName) === 0));
                $brgy = count($matches) === 1 ? $matches[0] : null;
            }
            if (! $brgy || ($bCode !== '' && $bCode !== (string) $brgy['code'])) {
                $fail($f['barangay'], 'The selected barangay does not belong to the selected city / municipality.');
            }
        } elseif ($requireBarangay) {
            $fail($f['barangay'], 'Please select a barangay.');
        }

        return [
            'region'            => $region['name'],
            'region_psgc'       => $region['psgc'],
            'region_code'       => $region['code'],
            'province'          => $parent['name'] ?? null,
            'province_psgc'     => $parent['psgc'] ?? null,
            'province_code'     => $parent['code'] ?? null,
            // Display name for the province field when the city is not under a province (NCR, HUCs).
            'province_display'  => $parent['name'] ?? $region['name'],
            'municipality'      => $loc['name'],
            'municipality_psgc' => $loc['psgc'],
            'municipality_code' => $loc['code'],
            'city_class'        => $loc['class'],
            'barangay'          => $brgy['name'] ?? null,
            'barangay_psgc'     => $brgy['psgc'] ?? null,
            'barangay_code'     => $brgy['code'] ?? null,
            'district'          => $this->districtName($brgy['district'] ?? null),
        ];
    }

    // ── Internals ─────────────────────────────────────────────

    private function hasProvinces(array $region): bool
    {
        foreach ($this->provinces as $p) if ($p['region'] === $region['psgc']) return true;
        return false;
    }

    private function regionLevelLocalities(array $region): array
    {
        return array_filter($this->localities, fn ($l) => $l['region'] === $region['psgc'] && $l['parent'] === null);
    }

    private function item(array $row, string $level): array
    {
        $region = $this->regions[$row['region'] ?? $row['psgc']] ?? $row;
        return [
            'psgc'        => $row['psgc'],
            'code'        => $row['code'],
            'key'         => $row['psgc'],   // value to request the next level with
            'name'        => $row['name'],
            'level'       => $level,
            'region_psgc' => $region['psgc'],
            'region_code' => $region['code'],
        ];
    }

    /** A choice that lists the cities directly under a region (it is not a province). */
    private function groupItem(array $region, string $label): array
    {
        return [
            'psgc'        => null,
            'code'        => null,
            'key'         => $region['psgc'],
            'name'        => $label,
            'level'       => 'Group',
            'region_psgc' => $region['psgc'],
            'region_code' => $region['code'],
        ];
    }

    private function localityItem(array $l): array
    {
        $parent = $l['parent'] ? ($this->provinces[$l['parent']] ?? null) : null;
        $region = $this->regions[$l['region']];
        return [
            'psgc'          => $l['psgc'],
            'code'          => $l['code'],
            'key'           => $l['psgc'],
            'name'          => $l['name'],
            'level'         => $l['level'],
            'city_class'    => $l['class'],
            'region_psgc'   => $region['psgc'],
            'region_code'   => $region['code'],
            'province_psgc' => $parent['psgc'] ?? null,
            'province_code' => $parent['code'] ?? null,
        ];
    }
}
