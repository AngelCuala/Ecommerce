<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\AccountController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\EarningsController;
use App\Http\Controllers\Seller\ReportController;
use App\Http\Controllers\Seller\ReviewController;
use App\Http\Controllers\Seller\StatusController;
use App\Http\Controllers\Seller\StoreSettingsController;
use App\Models\Review;
use App\Support\ApiJson;
use App\Support\SellerJson;
use Illuminate\Http\JsonResponse;

/** Seller dashboard, reports, earnings, reviews, account, store settings and application status. */
class PanelController extends Controller
{
    use CallsWeb;

    public function dashboard(): JsonResponse
    {
        $d = $this->pageData($this->web(DashboardController::class, 'index'));

        $money = $d['money'];
        $chart = $d['earningsChart'];

        return response()->json([
            // period: today, week, month (default), custom with from / to (Y-m-d).
            'period'           => $d['range']->period,
            'range_label'      => $d['range']->label(),
            'from'             => $d['range']->from->toDateString(),
            'to'               => $d['range']->to->toDateString(),
            'money'            => $money,   // gross, refunds, commission, net, pending_commission, finalized_commission, lines
            'product_counts'   => $d['productCounts'],
            'best_sellers'     => collect($d['bestSellers'])->map(fn ($b) => ['product_id' => $b->product_id, 'title' => $b->title, 'units' => $b->units, 'sales' => $b->sales])->values(),
            'total_earnings'   => (float) $money['net'],
            'total_revenue'    => (float) $money['gross'],
            'total_commission' => (float) $money['commission'],
            'total_orders'     => (int) $d['orderCounts']['total'],
            'order_counts'     => $d['orderCounts'],
            'commission_rate'  => (float) $d['commissionRate'],
            'this_month'       => (float) ($chart->last()->value ?? 0),
            'last_month'       => (float) ($chart->slice(-2, 1)->first()->value ?? 0),
            'chart'            => $chart->map(fn ($m) => ['label' => $m->label, 'value' => round($m->value, 2)])->values(),
            'to_ship'          => (int) $d['toShip'],
            'product_count'    => $d['books']->count(),
            'low_stock'        => $d['lowStock']->map(fn ($p) => SellerJson::productRow($p))->values(),
            // The warning at the top of the dashboard: listings at 10 or fewer, sold out, and the bell's count.
            'low_stock_threshold'  => (int) config('marketplace.low_stock_threshold', 10),
            'low_stock_count'      => (int) $d['productCounts']['low_stock'],
            'sold_out_count'       => (int) $d['productCounts']['out_of_stock'],
            'unread_notifications' => auth()->user()->notifications()->whereNull('read_at')->count(),
            'recent_orders'    => $d['recentOrders']->map(fn ($i) => [
                'order_id' => $i->order_id, 'number' => $i->order?->number(), 'buyer' => $i->order?->user?->name,
                'product' => $i->product?->title, 'quantity' => (int) $i->quantity, 'earning' => (float) $i->seller_earning,
                'status' => $i->order?->status, 'created_at' => $i->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * The website's Commission details: every order line with the rate stored at purchase, the commission and the net,
     * by period (today, week, month, custom from/to) and status (pending, finalized, refunded, reversed, unpaid) · page.
     */
    public function commissions(): JsonResponse
    {
        request()->query->remove('export');
        $d = $this->pageData($this->web(\App\Http\Controllers\Seller\CommissionController::class, 'index'));
        $c = $d['commission'];

        return response()->json(ApiJson::page($d['items'], fn ($i) => [
            'order_id' => $i->order_id, 'order_number' => $i->order?->number(), 'placed_at' => $i->order?->created_at?->toIso8601String(),
            'product' => $i->product->title ?? 'Removed product', 'option' => $i->variation_name, 'quantity' => (int) $i->quantity,
            'gross' => \App\Services\SellerCommission::gross($i), 'rate' => (float) $i->commission_rate,
            'commission' => (float) $i->commission_amount, 'net' => (float) $i->seller_earning,
            'status' => $c->statusOf($i), 'status_label' => \App\Services\SellerCommission::STATUSES[$c->statusOf($i)] ?? $c->statusOf($i),
        ]) + [
            'period' => $d['range']->period, 'range_label' => $d['range']->label(),
            'from' => $d['range']->from->toDateString(), 'to' => $d['range']->to->toDateString(),
            'status' => $d['status'], 'statuses' => \App\Services\SellerCommission::STATUSES,
            'summary' => $d['summary'], 'configured_rate' => (float) $d['configuredRate'],
        ]);
    }

    /** The commission lines as a file (format: csv / xlsx), like the website's export. */
    public function exportCommissions(): mixed
    {
        request()->validate(['format' => 'required|in:csv,xlsx'], ['format.in' => 'Choose CSV or Excel (XLSX).']);
        request()->query->set('export', request()->input('format'));
        $response = $this->web(\App\Http\Controllers\Seller\CommissionController::class, 'index');

        return $response instanceof \Symfony\Component\HttpFoundation\Response && ! $response instanceof \Illuminate\Http\RedirectResponse
            ? $response
            : $this->actionJson($response);
    }

    /** from, to (Y-m-d). */
    public function reports(): JsonResponse
    {
        request()->query->remove('export');   // the app shows the report; the website exports files
        $d = $this->pageData($this->web(ReportController::class, 'index'));

        return response()->json([
            'from' => $d['from'], 'to' => $d['to'],
            'total_revenue'       => round((float) $d['totalRevenue'], 2),
            'total_seller_funded' => round((float) $d['totalSellerFunded'], 2),
            'total_commission'    => round((float) $d['totalCommission'], 2),
            'total_earnings'      => round((float) $d['totalEarnings'], 2),
            'total_orders'        => (int) $d['totalOrders'],
            'commission_rate'     => (float) $d['commissionRate'],
            'by_product' => $d['byProduct']->map(fn ($p) => ['title' => $p['title'], 'qty' => (int) $p['qty'],
                'revenue' => round($p['revenue'], 2), 'earnings' => round($p['earnings'], 2)])->values(),
            'by_date'    => $d['byDate']->map(fn ($v, $day) => ['day' => $day, 'earnings' => round($v, 2)])->values(),
        ]);
    }

    /** The report as a file, exactly like the website's export: format (pdf / csv / xlsx), from, to. */
    public function exportReport(): mixed
    {
        request()->validate(['format' => 'required|in:' . implode(',', \App\Support\TableExport::FORMATS)],
            ['format.in' => 'Choose PDF, CSV or Excel (XLSX).']);
        request()->query->set('export', request()->input('format'));
        $response = $this->web(ReportController::class, 'index');

        return $response instanceof \Symfony\Component\HttpFoundation\Response && ! $response instanceof \Illuminate\Http\RedirectResponse
            ? $response
            : $this->actionJson($response);
    }

    public function earnings(): JsonResponse
    {
        $d = $this->pageData($this->web(EarningsController::class, 'index'));

        return response()->json(ApiJson::page($d['orders'], fn ($o) => [
            'id' => $o->id, 'number' => $o->number(), 'status' => $o->status, 'created_at' => $o->created_at?->toIso8601String(),
            'earning' => round((float) $o->items->sum('seller_earning'), 2), 'completed' => $o->status === \App\Models\Order::COMPLETED,
        ]) + [
            'balances' => $d['balances'],
            'minimum_payout' => \App\Models\SellerPayout::MINIMUM,
            'payouts'  => $d['payouts']->map(fn ($p) => ['id' => $p->id, 'amount' => (float) $p->amount, 'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(), 'paid_at' => $p->paid_at?->toIso8601String()])->values(),
            'request_token' => $d['token'],
        ]);
    }

    /** amount, request_token (one request per token, like the website). */
    public function requestPayout(): JsonResponse
    {
        $json = $this->actionJson($this->web(EarningsController::class, 'requestPayout'));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['balances' => app(EarningsController::class)->balances(auth()->id())]);
        }
        return $json;
    }

    /** stars (1–5), replied (yes/no). */
    public function reviews(): JsonResponse
    {
        $d = $this->pageData($this->web(ReviewController::class, 'index'));

        return response()->json(ApiJson::page($d['reviews'], fn ($r) => ApiJson::review($r) + [
            'product' => $r->product?->title, 'product_slug' => $r->product?->slug, 'variation' => $r->variation_name,
            'seller_replied_at' => $r->seller_replied_at?->toIso8601String(),
        ]) + ['counts' => $d['counts']]);
    }

    /** reply (1–1,000 characters). One reply per review; edit it afterwards. */
    public function reply(Review $review): JsonResponse
    {
        return $this->actionJson($this->web(ReviewController::class, $review->seller_reply === null ? 'reply' : 'updateReply', ['review' => $review]));
    }

    // ── Account, store settings, application status ──────────────

    public function account(): JsonResponse
    {
        $d = $this->pageData($this->web(AccountController::class, 'index'));
        $user = $d['user'];

        return response()->json(['user' => AuthController::userPayload($user) + [
            'address' => $user->address, 'city' => $user->city, 'zip' => $user->zip, 'country' => $user->country,
            'shop_name' => $d['application']?->shop_name,
        ]]);
    }

    /** name, email, phone, address, city, zip, country, shop_name, avatar. */
    public function updateAccount(): JsonResponse
    {
        $json = $this->actionJson($this->web(AccountController::class, 'update'));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->account()->getData(true));
        }
        return $json;
    }

    /** current_password, password, password_confirmation. */
    public function password(): JsonResponse
    {
        return $this->actionJson($this->web(AccountController::class, 'updatePassword'), \App\Http\Controllers\Api\V1\PasswordCodeController::sentFlag());
    }

    public function storeSettings(): JsonResponse
    {
        $d = $this->pageData($this->web(StoreSettingsController::class, 'edit'));
        $a = $d['application'];

        return response()->json(['store' => [
            'shop_name' => $a->shop_name, 'description' => $a->description, 'phone' => $a->phone,
            'region' => $a->region, 'province' => $a->province, 'municipality' => $a->municipality, 'barangay' => $a->barangay,
            'street' => $a->street, 'zip_code' => $a->zip_code, 'province_code' => $a->province_code, 'municipality_code' => $a->municipality_code,
            'logo_url' => ApiJson::file($a->logo_path), 'banner_url' => ApiJson::file($a->banner_path),
            'is_on_vacation' => (bool) $a->is_on_vacation, 'address' => $a->address,
        ]]);
    }

    /** The web's store settings form (multipart for logo / banner). */
    public function updateStoreSettings(): JsonResponse
    {
        $json = $this->actionJson($this->web(StoreSettingsController::class, 'update'));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->storeSettings()->getData(true));
        }
        return $json;
    }

    /** The application status page for a seller who isn't approved yet. */
    public function status(): JsonResponse
    {
        $user = auth()->user();
        if ($user->isApproved()) {
            return response()->json(['status' => 'approved', 'message' => 'Your store is approved.']);
        }
        $d = $this->pageData($this->web(StatusController::class, 'show'));
        $app = $d['application'];

        return response()->json([
            'status' => $app?->status ?? $user->approval_status,
            'rejection_reason' => $app?->rejection_reason,
            'shop_name' => $app?->shop_name,
            'submitted_at' => $app?->created_at?->toIso8601String(),
            'documents' => collect(['government_id' => 'Government-issued ID', 'dti_certificate' => 'DTI Certificate',
                'sec_certificate' => 'SEC Certificate', 'bir_2303' => 'BIR Form 2303'])
                ->map(fn ($label, $field) => ['field' => $field, 'label' => $label, 'uploaded' => (bool) $app?->{$field . '_path'}])->values(),
        ]);
    }

    /** Rejected application: upload corrected documents (government_id, dti_certificate, sec_certificate, bir_2303). */
    public function resubmit(): JsonResponse
    {
        return $this->actionJson($this->web(StatusController::class, 'resubmit'));
    }
}
