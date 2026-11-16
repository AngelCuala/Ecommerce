<?php

namespace App\Services;

use App\Models\Parcel;
use App\Models\PickupStop;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Bulk pickups (PAYMENTS_SPEC §11). The logistics company sets the pickup time, not the seller:
 *  - a request before the daily cutoff (config marketplace.pickup_cutoff, 12:00) is picked up today, after it tomorrow
 *  - the seller gets a time window (config marketplace.pickup_windows, e.g. 1–5 PM) and may choose one of those offered
 *  - one stop per seller per day: every parcel the seller makes ready that day joins it, and one rider collects them all
 *    (a stop the rider already started collecting from takes no more parcels: the next ones get a new stop)
 */
class PickupStops
{
    public static function cutoff(): string
    {
        return (string) config('marketplace.pickup_cutoff', '12:00');
    }

    /** ['09:00-12:00', '13:00-17:00'] as [[start, end], …]. */
    public static function windows(): array
    {
        return collect(config('marketplace.pickup_windows', ['09:00-12:00', '13:00-17:00']))
            ->map(fn ($w) => array_map('trim', explode('-', $w)))->filter(fn ($w) => count($w) === 2)->values()->all();
    }

    private static function now(): Carbon
    {
        return now()->tz(config('app.business_timezone'));
    }

    /** The day a request made now is picked up: today before the cutoff, otherwise tomorrow. */
    public static function pickupDate(): Carbon
    {
        $now = self::now();

        return $now->format('H:i') < self::cutoff() ? $now->copy()->startOfDay() : $now->copy()->addDay()->startOfDay();
    }

    /** The windows still possible on that day (today: those that haven't started yet). */
    public static function windowsFor(Carbon $date): array
    {
        $now = self::now();

        return array_values(array_filter(self::windows(), fn ($w) => ! $date->isSameDay($now) || $w[0] > $now->format('H:i')));
    }

    /** ['13:00-17:00' => 'Today, 1–5 PM', …] for the seller's form. */
    public static function choices(): array
    {
        $date = self::pickupDate();

        return collect(self::windowsFor($date))->mapWithKeys(fn ($w) => [$w[0] . '-' . $w[1] => self::label($date, $w[0], $w[1])])->all();
    }

    public static function label($date, string $start, string $end): string
    {
        $date = Carbon::parse($date, config('app.business_timezone'));
        $today = self::now()->startOfDay();
        $day = $date->isSameDay($today) ? 'Today' : ($date->isSameDay($today->copy()->addDay()) ? 'Tomorrow' : $date->format('M j'));
        $t = fn (string $hm) => ltrim(Carbon::createFromFormat('H:i', $hm)->format(substr($hm, 3) === '00' ? 'g A' : 'g:i A'), '0');
        $s = $t($start);
        $e = $t($end);
        if (substr($s, -2) === substr($e, -2)) {
            $s = trim(substr($s, 0, -2));
        }

        return "{$day}, {$s}–{$e}";
    }

    /**
     * The seller's stop with this company for the next pickup day, made if needed. A chosen window applies to a new stop;
     * an existing stop keeps its window (the parcel rides along).
     */
    public function stopFor(User $sc, User $seller, ?string $window = null): PickupStop
    {
        $date = self::pickupDate();
        $offered = self::windowsFor($date) ?: self::windows();
        $pick = collect($offered)->first(fn ($w) => $window && $w[0] . '-' . $w[1] === $window) ?? $offered[0];

        $open = PickupStop::where('sorting_center_id', $sc->id)->where('seller_id', $seller->id)->whereDate('pickup_date', $date)->latest('id')->get()
            ->first(fn (PickupStop $s) => ! $s->parcels()->whereNotIn('status', array_merge(PickupStop::OPEN_PARCELS, ['pickup_rejected', 'cancelled']))->exists());

        return $open ?? PickupStop::create(['sorting_center_id' => $sc->id, 'seller_id' => $seller->id, 'pickup_date' => $date->toDateString(),
            'window_start' => $pick[0], 'window_end' => $pick[1]]);
    }

    /** The other parcels of the same stop still waiting for a rider (bulk: the same rider takes them). */
    public function siblings(Parcel $parcel): \Illuminate\Support\Collection
    {
        if (! $parcel->pickup_stop_id) {
            return collect();
        }

        return Parcel::where('pickup_stop_id', $parcel->pickup_stop_id)->whereKeyNot($parcel->id)->whereIn('status', PickupStop::OPEN_PARCELS)->get();
    }
}
