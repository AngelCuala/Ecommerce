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
     * @param  Collection $groups  sellerId => ['items' => Collection, ...] (VoucherService::groupBySeller)
     * @return array<int, array{fee: float, weight: float, chargeable: int, from: string, to: string}>
     */
    public function quote(Collection $groups, string $destinationZone): array
    {
        $out = [];
        foreach ($groups as $sellerId => $group) {
            $seller = $group['items']->first()->product->seller ?? null;
            $from   = $this->originZone($seller);
            $weight = $this->weightOf($group['items']);
            $out[$sellerId] = [
                'fee'        => $this->fee($from, $destinationZone, $weight),
                'weight'     => $weight,
                'chargeable' => $this->chargeableKg($weight),
                'from'       => $from,
                'to'         => $destinationZone,
            ];
        }
        return $out;
    }
}
