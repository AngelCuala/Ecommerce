<?php

namespace App\Http\Controllers;

use App\Services\PsgcDirectory;

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

    public function regions()
    {
        return $this->json($this->psgc->regions());
    }

    /** Province-level choices of a region. Empty for NCR (its cities sit directly under it). */
    public function provincesByRegion(string $code)
    {
        $region = $this->psgc->region($code);
        abort_unless($region, 404, 'Unknown region.');
        return $this->json($this->psgc->provincesOfRegion($region));
    }

    /** All province-level choices nationwide (forms without a region dropdown). */
    public function provinces()
    {
        return $this->json($this->psgc->allProvinceChoices());
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
