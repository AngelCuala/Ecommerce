<?php

namespace App\Support;

use App\Services\PsgcDirectory;
use Illuminate\Validation\ValidationException;

/**
 * The provinces ALVY operates in (config/service_area.php — Laguna by default), checked against
 * the official PSA directory. One place for every "is this address inside the service area?"
 * question: sign-up addresses, the address book, checkout, seller pickup addresses, riders
 * and sorting centers.
 */
class ServiceArea
{
    /** True when a restriction is configured. */
    public static function enabled(): bool
    {
        return config('service_area.provinces', []) !== [];
    }

    /** Configured provinces as PSA rows (psgc, code, name). */
    public static function provinces(): array
    {
        $psgc = app(PsgcDirectory::class);
        return collect(config('service_area.provinces', []))
            ->map(fn ($code) => $psgc->province((string) $code))
            ->filter()->values()->all();
    }

    /** "Laguna" (or "Laguna and Batangas"). */
    public static function label(): string
    {
        $names = array_column(self::provinces(), 'name');
        return count($names) > 1
            ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names)
            : ($names[0] ?? 'the service area');
    }

    public static function message(string $what = 'address'): string
    {
        return 'ALVY currently operates in ' . self::label() . ' only. Please choose a ' . $what . ' in ' . self::label() . '.';
    }

    /** Cities / municipalities inside the area (PSA items with name, code, psgc). */
    public static function localities(): array
    {
        $psgc = app(PsgcDirectory::class);
        return collect(self::provinces())->flatMap(fn ($p) => $psgc->localitiesOf($p['psgc']) ?? [])->values()->all();
    }

    /** 9-digit codes of those cities / municipalities. */
    public static function localityCodes(): array
    {
        return array_values(array_filter(array_column(self::localities(), 'code')));
    }

    /** Is this city / municipality (9-digit code or 10-digit PSGC) inside the area? */
    public static function coversLocality(?string $code): bool
    {
        if (! self::enabled()) {
            return true;
        }
        $locality = app(PsgcDirectory::class)->locality($code);
        return $locality !== null && in_array($locality['parent'], array_column(self::provinces(), 'psgc'), true);
    }

    /**
     * Is a stored or submitted address inside the area? A city / municipality code decides when present
     * (it can't be faked by typing a different province name); otherwise the province name and the city
     * name must both match an official locality of a served province.
     */
    public static function covers(?string $municipalityCode, ?string $provinceName = null, ?string $municipalityName = null, ?string $provinceCode = null): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if (trim((string) $municipalityCode) !== '') {
            return self::coversLocality($municipalityCode);
        }
        if (trim((string) $provinceCode) !== '') {
            $p = app(PsgcDirectory::class)->province($provinceCode);
            if (! $p || ! in_array($p['psgc'], array_column(self::provinces(), 'psgc'), true)) {
                return false;
            }
        }

        $province = collect(self::provinces())->first(fn ($p) => self::same($p['name'], $provinceName));
        if (! $province) {
            return false;
        }
        $city = self::norm($municipalityName);
        return $city !== '' && collect(app(PsgcDirectory::class)->localitiesOf($province['psgc']) ?? [])
            ->contains(fn ($l) => self::norm($l['name']) === $city);
    }

    /** Throw a validation error on $field when the address is outside the area. */
    public static function assert(?string $municipalityCode, ?string $provinceName, ?string $municipalityName, string $field = 'municipality', string $what = 'address', ?string $provinceCode = null): void
    {
        if (! self::covers($municipalityCode, $provinceName, $municipalityName, $provinceCode)) {
            throw ValidationException::withMessages([$field => self::message($what)]);
        }
    }

    private static function same(?string $a, ?string $b): bool
    {
        return self::norm($a) !== '' && self::norm($a) === self::norm($b);
    }

    /** "City of Calamba" / "Calamba City" / "calamba" → "calamba". */
    private static function norm(?string $name): string
    {
        $n = strtolower(trim((string) $name));
        $n = preg_replace('/^city of\s+|\s+city$|\s*\(.*\)$/', '', $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }
}
