<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\AccountController;
use App\Http\Controllers\Courier\DashboardController;
use App\Http\Controllers\Courier\DeliveryController;
use App\Http\Controllers\Courier\ProfitController;
use App\Http\Controllers\Courier\RegistrationController;
use App\Support\ApiJson;
use App\Support\RiderJson;
use Illuminate\Http\JsonResponse;

/**
 * A direct (freelance) courier (the web's courier portal): available deliveries, pickup → out for delivery →
 * delivered, history, profit, account and the application status.
 */
class CourierController extends Controller
{
    use CallsWeb;

    public function dashboard(): JsonResponse
    {
        $response = $this->web(DashboardController::class, 'index');
        // A rider of a logistics company works from Hub Jobs (the web redirects there).
        if ($response instanceof \Illuminate\Http\RedirectResponse && str_contains($response->getTargetUrl(), '/courier/rider')) {
            return response()->json(['message' => 'You are a rider of a logistics company. Open Hub Jobs.', 'home' => 'rider'], 409);
        }
        $d = $this->pageData($response);

        return response()->json([
            'courier'        => ['name' => $d['courier']->fullName(), 'hub' => $d['courier']->hubName()],
            'available'      => $d['available']->map(fn ($x) => RiderJson::courierDelivery($x))->values(),
            'for_pickup'     => $d['forPickup']->map(fn ($x) => RiderJson::courierDelivery($x))->values(),
            'for_delivery'   => $d['forDelivery']->map(fn ($x) => RiderJson::courierDelivery($x))->values(),
            'today_done'     => (int) $d['todayDone'],
            'total_earnings' => round((float) $d['totalEarnings'], 2),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $d = $this->pageData($this->web(DeliveryController::class, 'show', ['deliveryId' => $id]));
        return response()->json(['delivery' => RiderJson::courierDelivery($d['delivery'])]);
    }

    public function accept(int $id): JsonResponse    { return $this->then($id, $this->web(DeliveryController::class, 'accept', ['deliveryId' => $id])); }
    public function pickup(int $id): JsonResponse    { return $this->then($id, $this->web(DeliveryController::class, 'pickup', ['deliveryId' => $id])); }
    public function inTransit(int $id): JsonResponse { return $this->then($id, $this->web(DeliveryController::class, 'inTransit', ['deliveryId' => $id])); }

    /** notes (optional). */
    public function complete(int $id): JsonResponse  { return $this->then($id, $this->web(DeliveryController::class, 'complete', ['deliveryId' => $id])); }

    private function then(int $id, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $page = $this->web(DeliveryController::class, 'show', ['deliveryId' => $id]);
            if ($page instanceof \Illuminate\Contracts\View\View) {
                $json->setData($json->getData(true) + ['delivery' => RiderJson::courierDelivery($page->getData()['delivery'])]);
            }
        }
        return $json;
    }

    /** q (order # or tracking no.), status (in_progress, delivered, failed), date (Y-m-d) · page. */
    public function history(): JsonResponse
    {
        $d = $this->pageData($this->web(DeliveryController::class, 'history'));
        // Which page the row opens: a hub delivery (Hub Jobs) or a courier-marketplace one.
        $open = fn ($x) => preg_match('#/courier/rider/deliveries/(\d+)#', (string) $x->url, $m) ? ['kind' => 'rider', 'id' => (int) $m[1]]
            : (preg_match('#/courier/deliveries/(\d+)#', (string) $x->url, $m) ? ['kind' => 'courier', 'id' => (int) $m[1]] : ['kind' => null, 'id' => null]);

        return response()->json(ApiJson::page($d['deliveries'], fn ($x) => $open($x) + [
            'order_number' => $x->order, 'tracking' => $x->tracking, 'buyer' => $x->buyer, 'place' => $x->place, 'seller' => $x->seller,
            'status' => $x->group === 'in_progress' ? 'in_progress' : $x->group, 'status_label' => $x->label, 'note' => $x->note,
            'attempt' => $x->attempt, 'cod' => $x->cod, 'fee' => $x->pay,
            'delivered_at' => $x->group === 'delivered' ? $x->at?->toIso8601String() : null, 'created_at' => $x->at?->toIso8601String(),
        ]) + ['summary' => $d['summary'],
            'statuses' => collect(DeliveryController::HISTORY_STATUSES)->map(fn ($s) => $s[0])->all()]);
    }

    public function profit(): JsonResponse
    {
        // The web's My Earnings: every paid job (hub pickups and deliveries, and courier-marketplace deliveries).
        $d = $this->pageData($this->web(ProfitController::class, 'index'));
        $s = $d['stats'];

        return response()->json([
            'total_earnings'   => (float) $s['total'],
            'total_deliveries' => (int) $s['deliveries'],
            'total_pickups'    => (int) $s['pickups'],
            'avg_per_delivery' => (float) $s['average'],
            'today'            => (float) $s['today'],
            'week'             => (float) $s['week'],
            'month'            => (float) $s['month'],
            'pending'          => (float) $s['pending'],
            'by_month'         => $d['months']->map(fn ($m) => ['month' => $m->label . ' ' . $m->year, 'count' => $m->jobs, 'total' => (float) $m->total])->values(),
            'recent'           => $d['recent']->map(fn ($r) => [
                'order_number' => $r['order'], 'buyer' => trim($r['type'] . ' · ' . ($r['tracking'] ?? '')), 'result' => $r['result'],
                'delivered_at' => $r['at']?->toIso8601String(), 'fee' => (float) $r['pay'],
            ])->values(),
        ]);
    }

    public function account(): JsonResponse
    {
        $d = $this->pageData($this->web(AccountController::class, 'index'));
        $c = $d['courier'];
        $rider = $d['rider'];

        return response()->json([
            'user'    => AuthController::userPayload($d['user']),
            // A rider added by the logistics company may have no courier record: its own name and number then.
            'courier' => [
                'name' => $c?->fullName() ?: ($rider?->full_name ?? $d['user']->name), 'masked_email' => $d['maskedEmail'],
                'contact_no' => $c?->contact_no ?? $rider?->phone ?? $d['user']->phone,
                'sex' => $c?->sex, 'age' => $c?->age, 'birthday' => $c?->birthday?->toDateString(), 'address' => $c?->fullAddress(),
                'vehicle_type' => $c?->vehicle_type ?? $rider?->vehicle_type, 'plate_number' => $c?->plate_number ?? $rider?->plate_number,
                'status' => $c?->status ?? 'approved', 'hub' => $c?->hubName() ?? $rider?->companyName(),
            ],
            // The website's Account page now holds the rider profile (vehicle, license, documents) and the header numbers.
            'rider'    => $rider ? \App\Support\RiderJson::rider($rider) : null,
            'stats'    => $d['stats'],   // earned, jobs, online
            'vehicles' => $d['vehicles'],
        ]);
    }

    /** Multipart: contact_no, avatar. */
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

    /** The courier application status (pending / rejected / approved). */
    public function status(): JsonResponse
    {
        $c = $this->pageData($this->web(RegistrationController::class, 'status'))['courier'];

        return response()->json(['application' => $c ? [
            'status' => $c->status, 'rejection_reason' => $c->rejection_reason, 'hub' => $c->hubName(),
            'name' => trim($c->first_name . ' ' . ($c->middle_initial ? $c->middle_initial . '. ' : '') . $c->last_name),
            'business_name' => $c->business_name, 'contact_no' => $c->contact_no, 'email' => auth()->user()->email,
            'vehicle_type' => $c->vehicle_type, 'plate_number' => $c->plate_number,
            'address' => implode(', ', array_filter([$c->street, $c->barangay, $c->municipality, $c->province])),
            'submitted_at' => ($c->submitted_at ?? $c->created_at)?->toIso8601String(),
        ] : null]);
    }
}
