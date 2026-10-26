<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Per-store shipping fees: weight-based, by zone (NCR / Luzon / Visayas / Mindanao).
 * See config/shipping.php for the default rate table; the logistics team can change a rate
 * (shipping_rates) and add a zone for a province no zone covered (shipping_zones).
 */
class ShippingService
{
    /** Rates and zones saved by logistics, loaded once per request. */
    private ?array $savedRates = null;
    private ?array $savedZones = null;

    public function forget(): void
    {
        $this->savedRates = $this->savedZones = null;
    }

    /** [route => [first, additional]] saved by logistics. */
    public function savedRates(): array
    {
        return $this->savedRates ??= \App\Models\ShippingRate::all()
            ->mapWithKeys(fn ($r) => [$r->route => [(float) $r->first_kg, (float) $r->additional_kg]])->all();
    }

    /** [province code => ShippingZone] added by logistics. */
    public function savedZones(): array
    {
        return $this->savedZones ??= \App\Models\ShippingZone::all()->keyBy('province_code')->all();
    }

    /** The 9-digit province code ("RRPP00000") of any PSGC code. */
    public function provinceOf(?string $psgcCode): ?string
    {
        $code = preg_replace('/\D/', '', (string) $psgcCode);
        if ($code === '') {
            return null;
        }
        return strlen($code) === 10 ? substr($code, 0, 2) . substr($code, 3, 2) . '00000' : substr($code, 0, 4) . '00000';
    }

    /** Every route of the rate table with its current rate (saved by logistics, else the default). */
    public function rateTable(): array
    {
        $out = [];
        foreach (config('shipping.rates') as $route => $rate) {
            $out[$route] = $this->savedRates()[$route] ?? $rate;
        }
        return $out;
    }
    private const ZONES = [
        'NCR'      => ['13'],
        'Luzon'    => ['01', '02', '03', '04', '05', '14', '17'],
        'Visayas'  => ['06', '07', '08', '18'],
        'Mindanao' => ['09', '10', '11', '12', '15', '16', '19'],
    ];

    /** Zone of a PSGC code (any level: region, province, city, barangay), or null. */
    public function zoneOf(?string $psgcCode): ?string
    {
        // A zone logistics added for this province (e.g. "Batanes") comes first.
        if (($province = $this->provinceOf($psgcCode)) && ($zone = $this->savedZones()[$province] ?? null)) {
            return 'zone:' . $zone->province_code;
        }
        $region = substr(preg_replace('/\D/', '', (string) $psgcCode), 0, 2);
        foreach (self::ZONES as $zone => $regions) {
            if (in_array($region, $regions, true)) {
                return $zone;
            }
        }
        return null;
    }

    /** Where a seller's parcels start (their approved pickup address). */
    public function originZone(?User $seller): string
    {
        $origin = $seller ? app(DeliveryRoutingService::class)->sellerOrigin($seller) : null;

        return $this->zoneOf($origin?->municipality_code ?? $origin?->province_code)
            ?? config('shipping.default_origin_zone', 'NCR');
    }

    /** [first kg, each additional kg] between two zones. */
    public function rate(string $from, string $to): array
    {
        foreach ([$from, $to] as $z) {
            if (str_starts_with($z, 'zone:') && ($zone = $this->savedZones()[substr($z, 5)] ?? null)) {
                return [(float) $zone->first_kg, (float) $zone->additional_kg];
            }
        }
        $rates = $this->rateTable();
        return $rates["$from-$to"] ?? $rates["$to-$from"] ?? [120, 40];
    }

    /** City/municipality part of a PSGC code (9-digit "RRPPMMBBB" or 10-digit "RRPPPMMBBB"). */
    public function cityOf(?string $psgcCode): ?string
    {
        $code = preg_replace('/\D/', '', (string) $psgcCode);
        if (strlen($code) < 9) {
            return null;
        }
        return strlen($code) === 10 ? substr($code, 0, 7) : substr($code, 0, 6);
    }

    /**
     * How close two places are: "Same city", "Same province", "Same region", or null when
     * they're further apart (or a code is missing). Shipping gets cheaper the closer they are.
     */
    public function distanceTier(?string $originCode, ?string $destinationCode): ?string
    {
        $o = preg_replace('/\D/', '', (string) $originCode);
        $d = preg_replace('/\D/', '', (string) $destinationCode);
        if ($o === '' || $d === '') {
            return null;
        }
        return match (true) {
            $this->cityOf($o) !== null && $this->cityOf($o) === $this->cityOf($d) => 'Same city',
            $this->provinceOf($o) === $this->provinceOf($d) && substr($o, 2, 2) !== '00' => 'Same province',
            substr($o, 0, 2) === substr($d, 0, 2)                                   => 'Same region',
            default                                                                 => null,
        };
    }

    /** Total weight rounded up to a whole kg, at least 1 (1.0 → 1, 1.2 → 2). */
    public function chargeableKg(float $kg): int
    {
        return max(1, (int) ceil(round($kg, 3)));
    }

    public function fee(string $from, string $to, float $kg): float
    {
        [$first, $extra] = $this->rate($from, $to);
        return (float) ($first + ($this->chargeableKg($kg) - 1) * $extra);
    }

    /** Actual weight of checkout lines (products without a weight count as the default). */
    public function weightOf(Collection $lines): float
    {
        $default = (float) config('shipping.default_weight_kg', 0.5);
        return round($lines->sum(fn ($l) => $l->quantity * ((float) ($l->product->weight_kg ?? 0) ?: $default)), 3);
    }

    /**
     * Why the address can't be delivered to, or null when it can.
     * Needs the PSGC city/municipality code (from the address dropdowns).
     */
    public function unserviceableReason(?string $municipalityCode, ?string $provinceCode = null): ?string
    {
        $code = preg_replace('/\D/', '', (string) ($municipalityCode ?: $provinceCode));
        if ($code === '' || ! $this->zoneOf($code)) {
            return 'This address has no region / city selected. Please edit it and choose the region, city and barangay from the lists.';
        }
        // Orders are only delivered inside the service area (Laguna). Older saved addresses elsewhere are kept but can't be used.
        if (! \App\Support\ServiceArea::covers($municipalityCode, null, null, $provinceCode)) {
            return 'We only deliver within ' . \App\Support\ServiceArea::label() . ' for now. Please choose or add a delivery address in ' . \App\Support\ServiceArea::label() . '.';
        }

        $province = strlen($code) === 10
            ? substr($code, 0, 2) . substr($code, 3, 2) . '00000'   // 10-digit PSGC → 9-digit province code
            : substr($code, 0, 4) . '00000';
        if (($name = config('shipping.unserviceable_provinces')[$province] ?? null) && ! isset($this->savedZones()[$province])) {
            return "Sorry, we don't deliver to {$name} yet. Please choose another address.";
        }

        return null;
    }

    /**
     * Shipping for each store's group of lines to a destination.
     *
     * The fee depends on how far the buyer is from the seller: same city / province / region
     * tiers when the PSGC codes are close, otherwise the zone route (e.g. Luzon → Visayas).
     * A zone the logistics team added for a province always wins.
     *
     * @param  Collection $groups  sellerId => ['items' => Collection, ...] (VoucherService::groupBySeller)
     * @param  ?string $destinationCode  the buyer's city/municipality PSGC code (for the distance tiers)
     * @return array<int, array{fee: float, weight: float, chargeable: int, from: string, to: string, route: string}>
     */
    public function quote(Collection $groups, string $destinationZone, ?string $destinationCode = null): array
    {
        $out = [];
        foreach ($groups as $sellerId => $group) {
            $seller = $group['items']->first()->product->seller ?? null;
            $from   = $this->originZone($seller);
            $weight = $this->weightOf($group['items']);

            $origin = $seller ? app(DeliveryRoutingService::class)->sellerOrigin($seller) : null;
            $tier   = $this->distanceTier($origin?->municipality_code ?? $origin?->province_code, $destinationCode);
            $special = str_starts_with($from, 'zone:') || str_starts_with($destinationZone, 'zone:');

            if ($tier && ! $special) {
                [$first, $extra] = $this->rateTable()[$tier];
                $fee   = (float) ($first + ($this->chargeableKg($weight) - 1) * $extra);
                $route = $tier;
            } else {
                $fee   = $this->fee($from, $destinationZone, $weight);
                $route = $this->routeLabel($from, $destinationZone);
            }

            $out[$sellerId] = [
                'fee'        => $fee,
                'weight'     => $weight,
                'chargeable' => $this->chargeableKg($weight),
                'from'       => $from,
                'to'         => $destinationZone,
                'route'      => $route,
            ];
        }
        return $out;
    }

    /** "Within Luzon", "Luzon → Visayas", or "Remote area" for a zone logistics added. */
    public function routeLabel(string $from, string $to): string
    {
        if (str_starts_with($from, 'zone:') || str_starts_with($to, 'zone:')) {
            return 'Remote area';
        }
        return $from === $to ? 'Within ' . $from : $from . ' → ' . $to;
    }
}
