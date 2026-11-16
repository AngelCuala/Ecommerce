<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Models\HubRemittance;
use App\Models\HubRemittanceItem;
use App\Services\HubRemittances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The website's ALVY Remittance for the app: deliveries ready to remit (with the report lines), what was sent, and Send. */
class AlvyRemittanceController extends Controller
{
    public function __construct(private HubRemittances $remit) {}

    private static function line(array $l): array
    {
        return ['tracking' => $l['tracking_number'], 'order_number' => $l['order_number'], 'delivered_at' => $l['delivered_at']?->toIso8601String(),
            'cod' => $l['cod'], 'cod_expected' => $l['cod_expected'], 'cod_collected' => $l['cod_collected'], 'shipping_fee' => $l['shipping_fee'],
            'amount_due' => $l['amount_due'], 'mismatch' => $l['mismatch']];
    }

    private static function remittance(HubRemittance $r): array
    {
        return ['id' => $r->id, 'reference' => $r->reference(), 'amount' => (float) $r->amount, 'status' => $r->status, 'status_label' => $r->statusLabel(),
            'deposit_reference' => $r->deposit_reference, 'received_amount' => $r->received_amount !== null ? (float) $r->received_amount : null, 'note' => $r->note,
            'lines' => $r->items_count ?? $r->items()->count(), 'created_at' => $r->created_at?->toIso8601String(), 'confirmed_at' => $r->confirmed_at?->toIso8601String()];
    }

    /** History filters (only this company's): status (submitted/confirmed/flagged), from, to. */
    public function index(): JsonResponse
    {
        $r = request();
        $r->validate(['status' => 'nullable|in:submitted,confirmed,flagged', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $tz = config('app.business_timezone');
        $ready = $this->remit->ready((int) auth()->id());
        $sent = HubRemittance::where('sorting_center_id', auth()->id())->withCount('items')
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->when($r->from, fn ($q, $d) => $q->where('created_at', '>=', \Carbon\Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($r->to, fn ($q, $d) => $q->where('created_at', '<=', \Carbon\Carbon::parse($d, $tz)->endOfDay()->utc()))
            ->latest('id')->limit(50)->get();

        return response()->json([
            'ready' => $ready->map(fn ($l) => self::line($l))->values(),
            'total' => round($ready->sum('amount_due'), 2),
            'sent' => $sent->map(fn ($r) => self::remittance($r))->values(),
        ]);
    }

    /** deposit_reference: the hub's deposit to ALVY's bank (not needed when ALVY owes the hub). */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate(['deposit_reference' => 'nullable|string|min:4|max:60']);
        try {
            $r = $this->remit->submit(auth()->user(), auth()->user(), isset($data['deposit_reference']) ? trim($data['deposit_reference']) : null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['deposit_reference' => [$e->getMessage()]]], 422);
        }

        return response()->json(['message' => "Remittance {$r->reference()} sent to ALVY with " . $r->items()->count() . ' line(s).', 'remittance' => self::remittance($r)]
            + $this->index()->getData(true));
    }

    public function show(HubRemittance $remittance): JsonResponse
    {
        abort_unless((int) $remittance->sorting_center_id === (int) auth()->id(), 404);

        return response()->json(['remittance' => self::remittance($remittance) + [
            'items' => $remittance->items->map(fn (HubRemittanceItem $i) => ['tracking' => $i->tracking_number, 'cod' => $i->cod,
                'cod_expected' => (float) $i->cod_expected, 'cod_collected' => (float) $i->cod_collected, 'shipping_fee' => (float) $i->shipping_fee,
                'amount_due' => (float) $i->amount_due, 'mismatch' => $i->mismatch])->values(),
        ]]);
    }
}
