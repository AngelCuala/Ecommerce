<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\RiderWorkController as WebRiderWork;
use App\Models\Rider;
use App\Models\RiderWalletEntry;
use App\Services\RiderDispatch;
use App\Support\RiderJson;
use Illuminate\Http\JsonResponse;

/** The website's Rider Pay page for the app: pay waiting to be sent to riders' e-wallets, and GCash cash-outs. */
class RiderPayController extends Controller
{
    use CallsWeb;

    public function index(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRiderWork::class, 'riderPay'));
        $cashout = fn (RiderWalletEntry $c) => ['id' => $c->id, 'rider' => $c->rider?->full_name, 'rider_phone' => $c->rider?->phone,
            'amount' => abs((float) $c->amount), 'gcash_number' => $c->gcash_number, 'gcash_name' => $c->gcash_name, 'status' => $c->status,
            'status_label' => $c->statusLabel(), 'reference' => $c->reference, 'reject_reason' => $c->reject_reason,
            'requested_at' => $c->created_at?->toIso8601String(), 'processed_at' => $c->processed_at?->toIso8601String()];

        return response()->json([
            'pay' => ['pickup' => RiderDispatch::PAY_PICKUP, 'delivery' => RiderDispatch::PAY_DELIVERY],
            'riders' => $d['riders']->map(fn ($row) => ['id' => $row['rider']->id, 'name' => $row['rider']->full_name, 'vehicle' => $row['rider']->vehicle_type,
                'phone' => $row['rider']->phone, 'to_send' => (float) $row['to_send'], 'balance' => (float) $row['balance'],
                'jobs' => $row['jobs']->map(fn ($j) => RiderJson::walletRow($j))->values()])->values(),
            'total_to_send' => round((float) $d['riders']->sum('to_send'), 2),
            'cashouts' => $d['cashouts']->map($cashout)->values(),
            'cashouts_done' => $d['cashoutsDone']->map($cashout)->values(),
            'sent' => collect($d['sent']->items())->map(fn (RiderWalletEntry $e) => ['id' => $e->id, 'rider' => $e->rider?->full_name,
                'amount' => (float) $e->amount, 'jobs' => $e->jobs, 'by' => $e->sender?->name, 'at' => $e->created_at?->toIso8601String()])->values(),
        ]);
    }

    public function send(Rider $rider): JsonResponse
    {
        return $this->then($this->web(WebRiderWork::class, 'sendPay', ['rider' => $rider]));
    }

    /** reference (the GCash reference number of the transfer). */
    public function paid(RiderWalletEntry $entry): JsonResponse
    {
        return $this->then($this->web(WebRiderWork::class, 'cashoutPaid', ['entry' => $entry]));
    }

    /** reason. */
    public function reject(RiderWalletEntry $entry): JsonResponse
    {
        return $this->then($this->web(WebRiderWork::class, 'cashoutReject', ['entry' => $entry]));
    }

    private function then(mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->index()->getData(true));
        }
        return $json;
    }
}
