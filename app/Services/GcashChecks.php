<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Assistant\GeminiAssistantModel;
use App\Support\AlvyGcash;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Helps the admin check GCash payments to ALVY (Admin → GCash Payments):
 *  - tells the admins about each new payment
 *  - reads the buyer's receipt screenshot with Gemini (the chatbot's key): is it a GCash receipt, what amount
 *    and Ref No. it shows. Only the image is sent. The admin still decides; these are hints.
 *  - after 24 hours unchecked, reminds the admins and tells the buyer it is still being checked (once)
 *  - a receipt that clearly doesn't match (not a GCash receipt, wrong amount, other Ref No., older than the order,
 *    sent to another number) is rejected at once: the buyer is told why and how to fix it, and has 24 hours to
 *    upload the right receipt on the order page (up to 3 tries); otherwise the order is cancelled.
 *    An admin can reject the same way, or cancel outright.
 *
 * Runs from `php artisan gcash:check` every minute (routes/console.php). Results are kept in each order's
 * payment_check: ['read' => bool, 'is_receipt', 'amount', 'reference', 'read_at', 'reminded_at'].
 */
class GcashChecks
{
    public const MODELS = ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest'];
    private const REST = 'gcash-receipt-model-rest:';
    public const REMIND_AFTER_HOURS = 24;
    public const FIX_HOURS = 24;     // to upload the right receipt after a rejection
    public const MAX_TRIES = 3;      // receipts per checkout (the first one included)

    public static function notifyAdmins(Collection $orders): void
    {
        $first = $orders->first();
        $amount = (float) ($first->payment_expected ?? $orders->sum('total_price'));
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            UserNotification::create([
                'user_id' => $adminId, 'type' => 'info', 'title' => 'GCash payment to check',
                'body'    => 'A buyer sent ₱' . number_format($amount, 2) . ' (Ref No. ' . $first->payment_reference . ') for '
                    . $orders->map->number()->implode(', ') . '. Check it in ALVY\'s GCash, then approve or reject.',
                'link'    => route('admin.gcash.index', [], false),
            ]);
        }
    }

    /** One run: read new receipts (a few at a time) and send the 24-hour reminders. */
    public function run(int $limit = 10): array
    {
        $read = 0;
        $groups = AlvyGcash::waiting()->get()->groupBy('checkout_group');
        foreach ($groups as $orders) {
            if ($read >= $limit) {
                break;
            }
            if (empty($orders->first()->payment_check['read']) && $this->read($orders)) {
                $read++;
            }
        }

        $reminded = 0;
        foreach ($groups as $orders) {
            $first = $orders->first();
            if ($first->created_at->lte(now()->subHours(self::REMIND_AFTER_HOURS)) && empty($first->payment_check['reminded_at'])) {
                $this->remind($orders);
                $reminded++;
            }
        }

        return ['read' => $read, 'reminded' => $reminded, 'expired' => $this->expire()];
    }

    /** Rejected payments the buyer didn't fix in time: the orders are cancelled. */
    public function expire(): int
    {
        $n = 0;
        foreach (AlvyGcash::rejected()->get()->groupBy('checkout_group') as $orders) {
            $by = $orders->first()->payment_check['fix_by'] ?? null;
            if ($by && now()->gte(\Illuminate\Support\Carbon::parse($by))) {
                $this->cancel($orders, 'not fixed within ' . self::FIX_HOURS . ' hours');
                $n++;
            }
        }
        return $n;
    }

    /** Reads the receipt shared by the checkout's orders. False when no model could do it now (tried again later). */
    public function read(Collection $orders): bool
    {
        $first = $orders->first();
        $path = $first->payment_proof_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            $this->save($orders, ['read' => true, 'is_receipt' => false, 'amount' => null, 'reference' => null, 'note' => 'No receipt file']);
            return true;
        }
        if (! config('services.assistant.gemini_key')) {
            return false;   // no reader configured: the admin checks by eye
        }

        $found = $this->extract($path);
        if ($found === null) {
            return false;
        }
        $ref = preg_replace('/\D/', '', (string) ($found['reference'] ?? ''));
        $to = preg_replace('/\D/', '', (string) ($found['recipient_number'] ?? ''));
        $date = isset($found['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $found['date']) ? $found['date'] : null;
        $this->save($orders, [
            'read'       => true,
            'is_receipt' => (bool) ($found['is_gcash_receipt'] ?? false),
            'amount'     => isset($found['amount']) && is_numeric($found['amount']) ? round((float) $found['amount'], 2) : null,
            'reference'  => $ref !== '' ? $ref : null,
            'date'       => $date,
            'recipient'  => $to !== '' ? $to : null,
        ]);

        // A clear mismatch: rejected now, and the buyer is told how to fix it.
        $orders = $orders->map->fresh();
        if ($problems = self::problems($orders)) {
            $this->askToFix($orders, $problems, 'reader');
        }

        return true;
    }

    /**
     * Clear mismatches between the receipt (as read) and the payment. Nothing unread counts as a mismatch.
     *
     * @return array<int, string> problem codes (see explain())
     */
    public static function problems(Collection $orders): array
    {
        $first = $orders->first();
        $check = $first->payment_check ?? [];
        if (empty($check['read'])) {
            return [];
        }
        if (! ($check['is_receipt'] ?? false)) {
            return ['not_receipt'];
        }
        $expected = (float) ($first->payment_expected ?? $orders->sum('total_price'));
        $problems = [];
        if (isset($check['amount']) && abs((float) $check['amount'] - $expected) >= 0.005) {
            $problems[] = 'amount';
        }
        if (! empty($check['reference']) && strlen($check['reference']) === 13 && $check['reference'] !== $first->payment_reference) {
            $problems[] = 'reference';
        }
        // Paid before the order was placed (a day's leeway for time zones): an old receipt.
        if (! empty($check['date']) && $check['date'] < $first->created_at->copy()->tz(config('app.business_timezone', 'Asia/Manila'))->subDay()->toDateString()) {
            $problems[] = 'old';
        }
        // Sent to another number (only when ALVY's number is set and the receipt shows enough of one).
        $alvy = preg_replace('/\D/', '', (string) (AlvyGcash::settings()['number'] ?? ''));
        if ($alvy !== '' && strlen((string) ($check['recipient'] ?? '')) >= 4 && substr($check['recipient'], -4) !== substr($alvy, -4)) {
            $problems[] = 'recipient';
        }
        return $problems;
    }

    /** Why a payment was rejected and how the buyer fixes it: ['reason' => …, 'fix' => …]. */
    public static function explain(string $code, Collection $orders, ?string $note = null): array
    {
        $first = $orders->first();
        $check = $first->payment_check ?? [];
        $peso = fn ($v) => '₱' . number_format((float) $v, 2);
        $expected = $peso($first->payment_expected ?? $orders->sum('total_price'));
        $out = match ($code) {
            'not_receipt'  => ['The picture you uploaded isn\'t a GCash receipt.',
                               'Upload a screenshot of the GCash receipt you got after paying (it shows the amount and the Ref No.).'],
            'amount'       => ['Your receipt shows ' . $peso($check['amount'] ?? 0) . ', but this payment needs exactly ' . $expected . '.',
                               'Send exactly ' . $expected . ' to ALVY\'s GCash, then upload that receipt. If part of your money already reached ALVY, ALVY will return it.'],
            'reference'    => ['The Ref No. you typed (' . $first->payment_reference . ') isn\'t the one on your receipt (' . ($check['reference'] ?? '') . ').',
                               'Type the 13-number Ref No. exactly as it appears on your receipt.'],
            'old'          => ['Your receipt is dated ' . (! empty($check['date']) ? \Illuminate\Support\Carbon::parse($check['date'])->format('M j, Y') : 'earlier') . ', before you placed this order.',
                               'Upload the receipt of the payment you made for this order.'],
            'recipient'    => ['Your payment went to a different GCash number, not ALVY\'s.',
                               'Pay with ALVY\'s GCash QR shown below, then upload that receipt.'],
            'not_received' => ['No GCash payment with Ref No. ' . $first->payment_reference . ' reached ALVY\'s GCash.',
                               'Make sure you paid ALVY\'s GCash QR shown below, then upload that receipt and type its Ref No.'],
            'wrong_amount' => ['The amount you sent doesn\'t match this payment (' . $expected . ').',
                               'Send exactly ' . $expected . ' to ALVY\'s GCash, then upload that receipt. If part of your money already reached ALVY, ALVY will return it.'],
            default        => ['The receipt is unclear.',
                               'Upload a clear screenshot of the GCash receipt showing the amount and the Ref No.'],
        };

        return ['reason' => $out[0] . ($note ? ' (' . $note . ')' : ''), 'fix' => $out[1]];
    }

    /**
     * Rejects the payment so the buyer can fix it: the orders stay in To Pay with "Rejected", the buyer is told
     * why and how to fix it, and has FIX_HOURS to upload the right receipt. On the last try the orders are cancelled.
     */
    public function askToFix(Collection $orders, array $codes, string $by, ?string $note = null): void
    {
        $first = $orders->first();
        $tries = (int) ($first->payment_check['tries'] ?? 1);
        $explained = array_map(fn ($c) => ['code' => $c] + self::explain($c, $orders, $c === $codes[0] ? $note : null), $codes);
        $reasons = implode(' ', array_column($explained, 'reason'));

        if ($tries >= self::MAX_TRIES) {
            $this->cancel($orders, $reasons . ' (' . self::MAX_TRIES . ' receipts tried)');
            return;
        }

        $fixBy = now()->addHours(self::FIX_HOURS);
        foreach ($orders as $order) {
            $order->forceFill([
                'payment_status' => 'Rejected',
                'payment_check'  => array_merge($order->payment_check ?? [], [
                    'rejection' => ['problems' => $explained, 'by' => $by, 'at' => now()->toIso8601String()],
                    'fix_by'    => $fixBy->toIso8601String(),
                ]),
            ])->saveQuietly();
        }

        UserNotification::create([
            'user_id' => $first->user_id, 'type' => 'warning', 'title' => 'GCash payment rejected',
            'body'    => 'Your GCash payment for ' . $orders->map->number()->implode(', ') . ' was rejected. Why: ' . $reasons
                . ' How to fix it: ' . implode(' ', array_unique(array_column($explained, 'fix')))
                . ' Upload the right receipt on your order page by ' . $fixBy->copy()->tz(config('app.business_timezone', 'Asia/Manila'))->format('M j, g:i A')
                . ', or the order is cancelled.',
            'link'    => route('profile.orders.show', $first->id, false),
        ]);
        \App\Models\ActivityLog::note('GCash payment rejected (' . $by . ') · Ref ' . $first->payment_reference . ' · ' . $reasons
            . ' · orders ' . $orders->map->number()->implode(', '), $first);
    }

    /** The buyer uploads the right receipt after a rejection: the payment waits for checking again. */
    public function resubmit(Collection $orders, string $proofPath, string $reference): void
    {
        $first = $orders->first();
        $check = $first->payment_check ?? [];
        $history = $check['attempts'] ?? [];
        $history[] = ['proof' => $first->payment_proof_path, 'reference' => $first->payment_reference,
            'problems' => $check['rejection']['problems'] ?? [], 'at' => $check['rejection']['at'] ?? null];

        foreach ($orders as $order) {
            $order->forceFill([
                'payment_status'     => 'For verification',
                'payment_proof_path' => $proofPath,
                'payment_reference'  => $reference,
                'payment_check'      => ['read' => false, 'tries' => count($history) + 1, 'attempts' => $history],
            ])->saveQuietly();
        }
        self::notifyAdmins($orders->map->fresh());
    }

    /** The payment can't be confirmed: the orders are cancelled (nothing was taken from stock yet). */
    public function cancel(Collection $orders, string $why): void
    {
        foreach ($orders as $order) {
            $order->forceFill(['payment_status' => 'Not received'])->saveQuietly();
            $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => 'GCash payment not confirmed: ' . $why]);
        }
    }

    /** What Gemini reads on a receipt image (local disk): ['is_gcash_receipt', 'amount', 'reference'], or null when no model could answer now. */
    public function extract(string $path): ?array
    {
        $body = [
            'contents' => [['role' => 'user', 'parts' => [
                ['inlineData' => ['mimeType' => Storage::disk('local')->mimeType($path) ?: 'image/jpeg', 'data' => base64_encode(Storage::disk('local')->get($path))]],
                ['text' => 'This should be a screenshot of a GCash payment receipt (Philippines). Say whether it really is a GCash '
                    . 'payment/transfer receipt, the amount paid in pesos (a number, no currency sign), the 13-digit reference number '
                    . '(Ref No., digits only), the date of the payment (YYYY-MM-DD) and the mobile number the money was sent to '
                    . '(digits only, as far as it is shown). Use null for anything you cannot read clearly.'],
            ]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => ['type' => 'object', 'properties' => [
                    'is_gcash_receipt' => ['type' => 'boolean'],
                    'amount'           => ['type' => 'number', 'nullable' => true],
                    'reference'        => ['type' => 'string', 'nullable' => true],
                    'date'             => ['type' => 'string', 'nullable' => true],
                    'recipient_number' => ['type' => 'string', 'nullable' => true],
                ], 'required' => ['is_gcash_receipt']],
                'temperature' => 0,
            ],
        ];

        foreach (self::MODELS as $model) {
            if (Cache::has(self::REST . $model)) {
                continue;
            }
            try {
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.assistant.gemini_key')])
                    ->timeout(30)->post(GeminiAssistantModel::ENDPOINT . rawurlencode($model) . ':generateContent', $body);
            } catch (ConnectionException) {
                Cache::put(self::REST . $model, true, 60);
                continue;
            }
            if (in_array($response->status(), [429, 500, 503, 504, 404], true)) {
                Cache::put(self::REST . $model, true, 60);
                continue;
            }
            if (! $response->successful()) {
                report(new \RuntimeException("GCash receipt ({$model}): HTTP {$response->status()} " . mb_substr($response->body(), 0, 300)));
                return null;
            }
            return json_decode((string) $response->json('candidates.0.content.parts.0.text'), true) ?: [];
        }

        return null;
    }

    /**
     * What the admin sees next to a payment: [label, tone] lines (tone ok / warn / wait).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function hints(Collection $orders): array
    {
        $first = $orders->first();
        $expected = (float) ($first->payment_expected ?? $orders->sum('total_price'));
        $check = $first->payment_check ?? [];
        $hints = [];

        // Same Ref No. on another checkout (should never pass checkout; old data or a race).
        if ($first->payment_reference && Order::where('payment_method', Order::GCASH_RECEIPT)->where('payment_reference', $first->payment_reference)
            ->where('checkout_group', '!=', $first->checkout_group)->exists()) {
            $hints[] = ['This Ref No. was also used on another order', 'warn'];
        }

        if (empty($check['read'])) {
            $hints[] = ['Reading the receipt…', 'wait'];
            return $hints;
        }
        if (! ($check['is_receipt'] ?? false)) {
            $hints[] = ['This doesn\'t look like a GCash receipt', 'warn'];
        }
        if (isset($check['amount'])) {
            $hints[] = abs((float) $check['amount'] - $expected) < 0.005
                ? ['Amount on the receipt matches (₱' . number_format($expected, 2) . ')', 'ok']
                : ['Receipt shows ₱' . number_format((float) $check['amount'], 2) . ', not ₱' . number_format($expected, 2), 'warn'];
        } elseif ($check['is_receipt'] ?? false) {
            $hints[] = ['Couldn\'t read the amount on the receipt', 'warn'];
        }
        if (! empty($check['reference'])) {
            $hints[] = $check['reference'] === $first->payment_reference
                ? ['Ref No. on the receipt matches', 'ok']
                : ['Receipt shows Ref No. ' . $check['reference'] . ', the buyer typed ' . $first->payment_reference, 'warn'];
        }

        return $hints;
    }

    private function remind(Collection $orders): void
    {
        $first = $orders->first();
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            UserNotification::create([
                'user_id' => $adminId, 'type' => 'warning', 'title' => 'GCash payment waiting over ' . self::REMIND_AFTER_HOURS . ' hours',
                'body'    => 'Ref No. ' . $first->payment_reference . ' (' . $orders->map->number()->implode(', ') . ') has not been checked yet.',
                'link'    => route('admin.gcash.index', [], false),
            ]);
        }
        UserNotification::create([
            'user_id' => $first->user_id, 'type' => 'info', 'title' => 'Still checking your GCash payment',
            'body'    => 'ALVY is still checking your GCash payment for ' . $orders->map->number()->implode(', ') . '. Thanks for waiting; we\'ll tell you as soon as it\'s confirmed.',
            'link'    => route('profile.orders.show', $first->id, false),
        ]);
        $this->save($orders, ['reminded_at' => now()->toIso8601String()]);
    }

    private function save(Collection $orders, array $values): void
    {
        foreach ($orders as $order) {
            $order->forceFill(['payment_check' => array_merge($order->payment_check ?? [], $values,
                isset($values['read']) ? ['read_at' => now()->toIso8601String()] : [])])->saveQuietly();
        }
    }
}
