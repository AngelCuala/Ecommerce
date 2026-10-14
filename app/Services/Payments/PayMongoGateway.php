<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayMongo Checkout Sessions (GCash, Maya, cards). Amounts are in centavos.
 * Needs PAYMONGO_SECRET_KEY (sk_test_… for PayMongo's test mode, sk_live_… for real payments).
 */
class PayMongoGateway implements PaymentGateway
{
    private const METHOD_TYPES = ['gcash' => ['gcash'], 'maya' => ['paymaya'], 'card' => ['card']];

    private function api(): PendingRequest
    {
        return Http::withBasicAuth((string) config('payments.paymongo.secret_key'), '')
            ->baseUrl(config('payments.paymongo.base_url'))
            ->acceptJson()->asJson()->timeout(20);
    }

    public function start(Payment $payment, Collection $orders): void
    {
        $lines = $orders->map(fn ($o) => [
            'name'     => 'ALVY order ' . $o->number(),
            'amount'   => (int) round($o->total_price * 100),
            'currency' => 'PHP',
            'quantity' => 1,
        ])->values()->all();

        $response = $this->api()->post('/checkout_sessions', ['data' => ['attributes' => [
            'line_items'           => $lines,
            'payment_method_types' => self::METHOD_TYPES[$payment->method] ?? ['card'],
            'success_url'          => route('payments.return', $payment),
            'cancel_url'           => route('payments.return', [$payment, 'cancelled' => 1]),
            'reference_number'     => 'ALVY-PAY-' . $payment->id,
            'description'          => 'ALVY checkout',
            'send_email_receipt'   => true,
            'show_line_items'      => true,
        ]]])->throw()->json('data');

        $payment->update([
            'provider_ref' => $response['id'],
            'checkout_url' => $response['attributes']['checkout_url'],
        ]);
    }

    public function fetch(Payment $payment): array
    {
        $session = $this->api()->get('/checkout_sessions/' . $payment->provider_ref)->throw()->json('data.attributes');

        $paid = collect($session['payments'] ?? [])->first(fn ($p) => ($p['attributes']['status'] ?? null) === 'paid');
        if ($paid) {
            return ['status' => 'paid', 'payment_id' => $paid['id'], 'reason' => null];
        }

        $intent = $session['payment_intent']['attributes'] ?? [];
        $error  = $intent['last_payment_error']['failed_message'] ?? $intent['last_payment_error']['failed_code'] ?? null;
        if ($error) {
            return ['status' => 'failed', 'payment_id' => null, 'reason' => (string) $error];
        }

        return ['status' => ($session['status'] ?? '') === 'expired' ? 'cancelled' : 'pending', 'payment_id' => null, 'reason' => null];
    }

    public function refund(Payment $payment, float $amount, string $reason): bool
    {
        if (! $payment->provider_payment_id) {
            return false;
        }
        try {
            $this->api()->post('/refunds', ['data' => ['attributes' => [
                'amount'     => (int) round($amount * 100),
                'payment_id' => $payment->provider_payment_id,
                'reason'     => 'requested_by_customer',
                'notes'      => mb_substr($reason, 0, 200),
            ]]])->throw();
            return true;
        } catch (\Throwable $e) {
            Log::error('PayMongo refund failed for payment ' . $payment->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
