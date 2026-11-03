<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * ALVY's own GCash account, which buyers pay at checkout (payment_method gcash_receipt). An admin checks
 * every receipt before the sellers see the order. Set by an admin on Admin → GCash payments and kept in
 * storage/app/settings/alvy-gcash.json: the QR image (public disk), the account name and the number.
 */
class AlvyGcash
{
    private const FILE = 'settings/alvy-gcash.json';

    /** @return array{qr_path: ?string, name: ?string, number: ?string} */
    public static function settings(): array
    {
        $saved = Storage::disk('local')->exists(self::FILE)
            ? (json_decode((string) Storage::disk('local')->get(self::FILE), true) ?: [])
            : [];

        return [
            'qr_path' => $saved['qr_path'] ?? null,
            'name'    => $saved['name'] ?? null,
            'number'  => $saved['number'] ?? null,
        ];
    }

    public static function save(array $values): void
    {
        Storage::disk('local')->put(self::FILE, json_encode(array_merge(self::settings(), $values), JSON_PRETTY_PRINT));
    }

    /** What checkout shows, or null when no QR is set (then GCash is not offered). @return ?array{qr_url: string, name: string, number: ?string} */
    public static function account(): ?array
    {
        $s = self::settings();
        if (! $s['qr_path'] || ! Storage::disk('public')->exists($s['qr_path'])) {
            return null;
        }

        return ['qr_url' => asset('storage/' . $s['qr_path']), 'name' => $s['name'] ?: 'ALVY', 'number' => $s['number']];
    }

    public static function enabled(): bool
    {
        return self::account() !== null;
    }

    /** GCash payments an admin still has to check (one checkout = one or more orders). */
    public static function waiting()
    {
        return \App\Models\Order::where('status', \App\Models\Order::AWAITING_PAYMENT)
            ->where('payment_method', \App\Models\Order::GCASH_RECEIPT)->where('payment_status', 'For verification');
    }

    /** GCash payments rejected and waiting for the buyer to upload the right receipt. */
    public static function rejected()
    {
        return \App\Models\Order::where('status', \App\Models\Order::AWAITING_PAYMENT)
            ->where('payment_method', \App\Models\Order::GCASH_RECEIPT)->where('payment_status', 'Rejected');
    }

    /**
     * The centavos that mark this buyer's checkout (1–99): different from every payment waiting to be checked,
     * so each one has its own exact amount in ALVY's GCash history. Kept in the session for the checkout.
     */
    public static function cents(): int
    {
        $kept = (int) session('gcash_cents');
        if ($kept >= 1 && $kept <= 99) {
            return $kept;
        }
        $taken = \App\Models\Order::where('status', \App\Models\Order::AWAITING_PAYMENT)->where('payment_method', \App\Models\Order::GCASH_RECEIPT)
            ->whereIn('payment_status', ['For verification', 'Rejected'])->whereNotNull('payment_expected')->pluck('payment_expected')
            ->map(fn ($v) => (int) round(((float) $v - floor((float) $v)) * 100))->unique()->all();
        $free = array_values(array_diff(range(1, 99), $taken)) ?: range(1, 99);
        $cents = $free[array_rand($free)];
        session(['gcash_cents' => $cents]);

        return $cents;
    }

    /**
     * What the buyer sends: the total with its centavos set to $cents, never more than the total
     * (ALVY covers the difference, under ₱1). E.g. ₱1,250.00 with 37 → ₱1,249.37.
     */
    public static function amount(float $total, ?int $cents = null): float
    {
        $total = round($total, 2);
        if ($total < 1) {
            return $total;
        }
        $cents ??= self::cents();
        $amount = floor($total) + $cents / 100;
        if ($amount > $total) {
            $amount -= 1;
        }

        return round(max(0.01, $amount), 2);
    }
}
