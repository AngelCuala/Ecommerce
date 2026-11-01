<?php

namespace App\Http\Controllers;

use App\Services\PsgcDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Map pin → address for the address form.
 *
 * The pin is looked up on OpenStreetMap (Nominatim), then the place names it returns are
 * matched to the official PSGC lists (PsgcDirectory), so the form can pick the region,
 * province, city and barangay by code. Name, mobile number and label are never touched.
 *
 * Nominatim's usage policy asks for an identifying User-Agent, at most one request per second
 * and caching: requests go through here, are cached, and the routes are throttled.
 */
class GeoController extends Controller
{
    private const NOMINATIM = 'https://nominatim.openstreetmap.org';

    public function __construct(private PsgcDirectory $psgc) {}

    /** GET /api/geo/reverse?lat=&lon= */
    public function reverse(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:4,22',     // the Philippines
            'lon' => 'required|numeric|between:116,127',
        ]);
        $lat = round((float) $data['lat'], 5);
        $lon = round((float) $data['lon'], 5);

        $osm = Cache::remember("geo:rev:$lat,$lon", now()->addDays(30), function () use ($lat, $lon) {
            return $this->nominatim('/reverse', [
                'lat' => $lat, 'lon' => $lon, 'format' => 'jsonv2', 'addressdetails' => 1, 'zoom' => 18,
            ]);
        });
        if (! $osm || empty($osm['address'])) {
            return response()->json(['message' => 'We couldn\'t find an address at that spot. Try moving the pin a little, or fill in the address below.'], 422);
        }

        return response()->json(['lat' => $lat, 'lon' => $lon] + $this->toAddress($osm));
    }

    /** GET /api/geo/search?q= : places in the Philippines matching a typed search. */
    public function search(Request $request)
    {
        $q = trim((string) $request->validate(['q' => 'required|string|min:3|max:120'])['q']);

        $results = Cache::remember('geo:search:' . md5(Str::lower($q)), now()->addDays(7), function () use ($q) {
            return $this->nominatim('/search', [
                'q' => $q, 'format' => 'jsonv2', 'countrycodes' => 'ph', 'limit' => 5, 'addressdetails' => 0,
            ]) ?? [];
        });

        return response()->json(collect($results)->map(fn ($r) => [
            'label' => $r['display_name'] ?? '',
            'lat'   => (float) ($r['lat'] ?? 0),
            'lon'   => (float) ($r['lon'] ?? 0),
        ])->values());
    }

    private function nominatim(string $path, array $query): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'ALVY-marketplace/1.0 (alvyshop27@gmail.com)',
                'Accept-Language' => 'en',
            ])->timeout(12)->get(self::NOMINATIM . $path, $query);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Nominatim address → form values, with PSGC matches where the names line up. */
    private function toAddress(array $osm): array
    {
        $a = $osm['address'];
        $out = [
            'display'  => $osm['display_name'] ?? '',
            'zip'      => preg_match('/^\d{4}$/', (string) ($a['postcode'] ?? '')) ? $a['postcode'] : null,
            'street'   => trim(implode(' ', array_filter([$a['house_number'] ?? null, $a['road'] ?? $a['pedestrian'] ?? null]))) ?: null,
            'region'   => null, 'province' => null, 'city' => null, 'barangay' => null,
        ];

        $cityNames = array_filter([$a['city'] ?? null, $a['town'] ?? null, $a['municipality'] ?? null, $a['city_district'] ?? null]);
        $brgyNames = array_filter([$a['quarter'] ?? null, $a['neighbourhood'] ?? null, $a['suburb'] ?? null, $a['village'] ?? null, $a['hamlet'] ?? null]);

        // Region: by name, else the region that has the matching province.
        $region = $this->best($this->psgc->regions(), array_filter([$a['region'] ?? null, $a['state'] ?? null]), true);
        $provinceNames = array_filter([$a['province'] ?? null, $a['state'] ?? null, $a['county'] ?? null]);
        if (! $region && $provinceNames) {
            foreach ($this->psgc->regions() as $r) {
                if ($this->best($this->psgc->provincesOfRegion($r), $provinceNames)) { $region = $r; break; }
            }
        }
        if (! $region) {
            return $out;
        }
        $out['region'] = $this->pick($region);

        // Province (none for NCR: its cities hang directly off the region).
        $provinces = $this->psgc->provincesOfRegion($region);
        $parentKey = $region['key'] ?? $region['code'] ?? null;
        if ($provinces) {
            $province = $this->best($provinces, $provinceNames) ?? $this->provinceByCity($provinces, $cityNames);
            if (! $province) {
                return $out;
            }
            $out['province'] = $this->pick($province);
            $parentKey = $province['key'] ?? $province['code'] ?? null;
        }

        $cities = $parentKey ? ($this->psgc->localitiesOf($parentKey) ?? []) : [];
        $city = $this->best($cities, $cityNames);
        if (! $city) {
            return $out;
        }
        $out['city'] = $this->pick($city);

        $locality = $this->psgc->locality($city['key'] ?? $city['code'] ?? null);
        if ($locality) {
            $barangay = $this->best($this->psgc->barangayItems($locality), $brgyNames);
            if ($barangay) {
                $out['barangay'] = $this->pick($barangay);
            }
        }

        return $out;
    }

    /** A province of the region that contains a city with one of these names. */
    private function provinceByCity(array $provinces, array $cityNames): ?array
    {
        if (! $cityNames) return null;
        foreach ($provinces as $p) {
            $list = $this->psgc->localitiesOf($p['key'] ?? $p['code'] ?? '') ?? [];
            if ($this->best($list, $cityNames)) return $p;
        }
        return null;
    }

    private function pick(array $item): array
    {
        return ['name' => $item['name'], 'code' => $item['code'] ?? $item['key'] ?? $item['psgc'] ?? null];
    }

    /**
     * The list item whose name best matches one of the names (exact after cleaning, then
     * whole-word containment). Null when nothing is close enough: better to leave a
     * dropdown for the user than to guess wrong.
     */
    private function best(array $items, array $names, bool $isRegion = false): ?array
    {
        $wanted = array_values(array_filter(array_map(fn ($n) => $this->clean($n, $isRegion), $names)));
        if (! $wanted) return null;

        $bestItem = null; $bestScore = 0;
        foreach ($items as $item) {
            foreach ($this->variants($item['name'] ?? '', $isRegion) as $v) {
                foreach ($wanted as $w) {
                    $score = $v === $w ? 3 : ((strlen($w) >= 4 && (str_contains(" $v ", " $w ") || str_contains(" $w ", " $v "))) ? 2 : 0);
                    if ($score > $bestScore) { $bestScore = $score; $bestItem = $item; }
                }
            }
        }
        return $bestItem;
    }

    /** A PSGC name and the parts worth matching, e.g. "Region IV-A (CALABARZON)" → also "calabarzon". */
    private function variants(string $name, bool $isRegion): array
    {
        $out = [$this->clean($name, $isRegion)];
        if (preg_match('/\(([^)]+)\)/', $name, $m)) {
            $out[] = $this->clean($m[1], $isRegion);
            $out[] = $this->clean(preg_replace('/\([^)]*\)/', '', $name), $isRegion);
        }
        return array_values(array_unique(array_filter($out)));
    }

    private function clean(?string $s, bool $isRegion = false): string
    {
        $s = Str::lower(Str::ascii((string) $s));
        if ($isRegion) {
            $s = str_replace(['metro manila', 'national capital region'], 'ncr', $s);
            $s = str_replace(['cordillera administrative region'], 'car', $s);
            $s = preg_replace('/\bbangsamoro\b.*/', 'barmm', $s);
        }
        $s = preg_replace('/\b(city of|municipality of|province of|barangay|brgy|bgy)\b\.?/', ' ', $s);
        $s = preg_replace('/\b(city|municipality|region)\b/', ' ', $s);
        $s = preg_replace('/\(pob\.?\)|\bpoblacion\b/', ' pob ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
