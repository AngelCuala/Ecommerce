<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Sales per seller for a date range. Used by Sales Reports and by the seller list on
 * Analytics & Reports, so both pages always show the same numbers.
 *
 * Counted: orders placed in the range that were not cancelled. Gross sales are after store
 * vouchers (paid by the seller); ALVY vouchers are paid by ALVY and don't change these.
 */
class SellerSales
{
    /**
     * Every seller account (also those with no sales), plus anyone whose products sold in the
     * range even if their role changed since (e.g. a suspended seller), plus ALVY's own listings.
     *
     * @param array{0:string,1:string} $dateFilter ['Y-m-d 00:00:00', 'Y-m-d 23:59:59']
     * @return Collection<int, object{id:int,name:string,shop:?string,email:string,orders:int,units:int,gross:float,commission:float,payout:float}>
     */
    public function totals(array $dateFilter): Collection
    {
        $sales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', $dateFilter)
            ->whereNotIn('orders.status', \App\Models\Order::NOT_SALES)
            ->groupBy('products.seller_id')
            ->selectRaw('products.seller_id,
                COUNT(DISTINCT order_items.order_id) as orders,
                SUM(order_items.quantity) as units,
                SUM(order_items.quantity * order_items.price - order_items.seller_discount) as gross,
                SUM(order_items.commission_amount) as commission,
                SUM(order_items.seller_earning) as payout')
            ->get()
            ->keyBy('seller_id');

        $rows = User::where('role', 'seller')
            ->orWhereIn('id', $sales->keys()->filter())
            ->with('sellerApplication')
            ->orderBy('name')
            ->get()
            ->map(function ($seller) use ($sales) {
                $row = $sales->get($seller->id);

                return (object) [
                    'id'         => $seller->id,
                    'name'       => $seller->name,
                    'shop'       => $seller->sellerApplication->shop_name ?? null,
                    'email'      => $seller->email,
                    'orders'     => (int) ($row->orders ?? 0),
                    'units'      => (int) ($row->units ?? 0),
                    'gross'      => (float) ($row->gross ?? 0),
                    'commission' => (float) ($row->commission ?? 0),
                    'payout'     => (float) ($row->payout ?? 0),
                ];
            });

        // Products listed by ALVY itself (no seller).
        if ($own = $sales->get(null) ?? $sales->get('')) {
            $rows->push((object) [
                'id' => 0, 'name' => 'ALVY (no seller)', 'shop' => null, 'email' => '',
                'orders' => (int) $own->orders, 'units' => (int) $own->units, 'gross' => (float) $own->gross,
                'commission' => (float) $own->commission, 'payout' => (float) $own->payout,
            ]);
        }

        return $rows;
    }
}
