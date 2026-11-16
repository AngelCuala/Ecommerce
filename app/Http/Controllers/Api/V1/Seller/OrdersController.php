<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\OrderController;
use App\Models\Order;
use App\Support\ApiJson;
use App\Support\SellerJson;
use Illuminate\Http\JsonResponse;

/** Order Management (S8–S10): New → Accepted → Packed → Ready for Pickup, cancel, returns. */
class OrdersController extends Controller
{
    use CallsWeb;

    /** tab: all, new, to_ship, shipping, completed, cancelled, return_refund · date_from, date_to, q (order no.). */
    public function index(): JsonResponse
    {
        $d = $this->pageData($this->web(OrderController::class, 'index'));

        return response()->json(ApiJson::page($d['orders'], fn ($o) => SellerJson::orderRow($o)) + [
            'tab'  => $d['tab'],
            'tabs' => collect(['all' => ['label' => 'All']] + Order::SELLER_TABS)
                ->reject(fn ($t, $key) => $key === 'return_refund' && ! Order::returnsEnabled() && ! ($d['counts'][$key] ?? 0))
                ->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'count' => $d['counts'][$key] ?? 0])->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['order' => SellerJson::orderDetail($this->pageData($this->web(OrderController::class, 'show', ['id' => $id])))]);
    }

    public function accept(int $id): JsonResponse   { return $this->then($id, $this->web(OrderController::class, 'accept', ['id' => $id])); }
    public function pack(int $id): JsonResponse     { return $this->then($id, $this->web(OrderController::class, 'pack', ['id' => $id])); }

    /** reason (from the list), details (optional message to the buyer). */
    public function cancel(int $id): JsonResponse   { return $this->then($id, $this->web(OrderController::class, 'cancel', ['id' => $id])); }

    /** Ready for Pickup: sorting_center_id, pickup_scheduled_at, notes. */
    public function handover(int $id): JsonResponse { return $this->then($id, $this->web(OrderController::class, 'handover', ['id' => $id])); }

    public function handedOver(int $id): JsonResponse { return $this->then($id, $this->web(OrderController::class, 'markHandedOver', ['id' => $id])); }

    /** decision: approve / reject · seller_note (required to reject). */
    public function decideReturn(int $id): JsonResponse { return $this->then($id, $this->web(OrderController::class, 'decideReturn', ['id' => $id])); }

    public function returnedReceived(int $id): JsonResponse { return $this->then($id, $this->web(OrderController::class, 'returnedReceived', ['id' => $id])); }

    /** What the printed waybill shows. */
    public function waybill(int $id): JsonResponse
    {
        $d = $this->pageData($this->web(OrderController::class, 'waybill', ['id' => $id]));
        $order = $d['order'];

        return response()->json(['waybill' => [
            'order_number' => $order->number(),
            'payment_method' => $order->payment_method,
            'buyer_note'   => $order->buyer_note,
            'tracking'     => $d['tracking'],
            'waybill'      => $d['waybill'] ?? null,   // what the website's QR code holds (hubs scan it)
            'weight_kg'    => $d['weight'] !== null ? (float) $d['weight'] : null,
            'seller'       => ['name' => $d['seller']?->storeName() ?? $d['seller']?->name,
                'phone' => $d['seller']?->sellerApplication?->phone ?? $d['seller']?->contact_no, 'address' => $d['seller']?->sellerApplication?->address],
            'buyer'        => ['name' => $order->full_name, 'phone' => $order->phone, 'address' => $order->fullAddress()],
            'cod'          => $order->payment_method === 'COD' && $order->payment_status !== 'Paid' ? (float) $order->total_price : null,
            'items'        => collect($d['items'])->map(fn ($i) => ['title' => $i->product->title ?? 'Item', 'variation' => $i->variation_name, 'quantity' => (int) $i->quantity])->values(),
        ]]);
    }

    /** An order action's message, plus the order page as it is now. */
    private function then(int $id, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $page = $this->web(OrderController::class, 'show', ['id' => $id]);
            if ($page instanceof \Illuminate\Contracts\View\View) {
                $json->setData($json->getData(true) + ['order' => SellerJson::orderDetail($page->getData())]);
            }
        }
        return $json;
    }
}
