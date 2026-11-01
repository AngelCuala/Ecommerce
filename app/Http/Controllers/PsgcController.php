<?php

namespace App\Http\Controllers;

use App\Services\PsgcDirectory;
use App\Support\ServiceArea;

/**
 * Address dropdown data, served from the local PSA PSGC dataset (resources/data/psgc/).
 * No external API is used. Route parameters accept either the 10-digit PSGC code or the
 * 9-digit Correspondence Code.
 *
 * Every item carries: psgc (10-digit), code (9-digit Correspondence Code or null),
 * key (value for the next request), name, level, plus region/province/city codes.
 */
class PsgcController extends Controller
{
    public function __construct(private PsgcDirectory $psgc) {}

    /** The address forms send `code` back for the next level; give it the PSGC key when an entry has no correspondence code. */
    private function json(array $items)
    {
        return response()->json(array_map(function (array $i) {
            $i['code'] ??= $i['key'] ?? $i['psgc'] ?? null;
            return $i;
        }, $items));
    }

    /**
     * The dropdowns only offer the service area (Laguna by default, config/service_area.php), so nobody
     * picks an address the server will refuse. The server still checks every submitted address.
     */
    private function inArea(array $items, string $key): array
    {
        if (! ServiceArea::enabled()) {
            return $items;
        }
        $allowed = array_column(ServiceArea::provinces(), $key);
        return array_values(array_filter($items, fn ($i) => in_array($i['psgc'] ?? null, $allowed, true)));
    }

    public function regions()
    {
        return $this->json($this->inArea($this->psgc->regions(), 'region'));
    }

    /** Province-level choices of a region. Empty for NCR (its cities sit directly under it). */
    public function provincesByRegion(string $code)
    {
        $region = $this->psgc->region($code);
        abort_unless($region, 404, 'Unknown region.');
        return $this->json($this->inArea($this->psgc->provincesOfRegion($region), 'psgc'));
    }

    /** All province-level choices nationwide (forms without a region dropdown). */
    public function provinces()
    {
        return $this->json($this->inArea($this->psgc->allProvinceChoices(), 'psgc'));
    }

    /** Cities/municipalities under a province, a special parent, or directly under a region. */
    public function municipalities(string $code)
    {
        $list = $this->psgc->localitiesOf($code);
        abort_if($list === null, 404, 'Unknown province or region.');
        return $this->json($list);
    }

    public function barangays(string $code)
    {
        $locality = $this->psgc->locality($code);
        abort_unless($locality, 404, 'Unknown city or municipality.');
        return $this->json($this->psgc->barangayItems($locality));
    }
}
