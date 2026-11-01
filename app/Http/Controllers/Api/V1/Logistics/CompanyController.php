<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\LogisticsController as WebLogistics;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\DeliveryArea;
use App\Support\ApiJson;
use Illuminate\Http\JsonResponse;

/** The company pages (the web's sc.areas, rates, reports, activity, chat and account). */
class CompanyController extends Controller
{
    use CallsWeb;

    private function with(mixed $response, callable $page): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $page()->getData(true));
        }
        return $json;
    }

    // ── Coverage (barangays of the company's municipality) ───────

    public function areas(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'areas'));

        return response()->json([
            'municipality'     => $d['sc']->assigned_municipality,
            'has_municipality' => $d['sc']->hasAssignedMunicipality(),
            'areas' => $d['areas']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'barangay_code' => $a->barangay_code,
                'riders' => (int) $a->riders_count])->values(),
            'barangays' => collect($d['barangays'])->map(fn ($b) => ['psgc' => $b['psgc'], 'code' => $b['code'] ?? null, 'name' => $b['name']])->values(),
            'barangay_total' => (int) $d['barangayTotal'],
        ]);
    }

    /** barangay_psgc, barangay_code. */
    // Just the message: the app reloads the page (it carries every barangay of the municipality, which is large).
    public function storeArea(): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'storeArea'));
    }

    public function destroyArea(DeliveryArea $area): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'destroyArea', ['area' => $area]));
    }

    // ── Shipping rates and zones (L7) ────────────────────────────

    public function rates(): JsonResponse
    {
        $d = $this->pageData($this->web(WebLogistics::class, 'rates'));

        return response()->json([
            'rates' => collect($d['rates'])->map(fn ($r, $route) => ['route' => $route, 'label' => str_replace('-', ' → ', $route),
                'first_kg' => (float) $r[0], 'additional_kg' => (float) $r[1]])->values(),
            'zones' => $d['zones']->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'province' => $z->province_name,
                'first_kg' => (float) $z->first_kg, 'additional_kg' => (float) $z->additional_kg])->values(),
            'uncovered' => collect($d['uncovered'])->map(fn ($name, $code) => ['code' => (string) $code, 'name' => $name])->values(),
        ]);
    }

    /** route, first_kg, additional_kg. */
    public function updateRate(): JsonResponse
    {
        return $this->with($this->web(WebLogistics::class, 'updateRate'), fn () => $this->rates());
    }

    /** province_code, name, first_kg, additional_kg. */
    public function storeZone(): JsonResponse
    {
        return $this->with($this->web(WebLogistics::class, 'storeZone'), fn () => $this->rates());
    }

    // ── Reports ──────────────────────────────────────────────────

    /** from, to (Y-m-d). */
    public function reports(): JsonResponse
    {
        request()->query->remove('export');
        $d = $this->pageData($this->web(WebSc::class, 'reports'));

        return response()->json([
            'from'   => $d['from']->toDateString(),
            'to'     => $d['to']->toDateString(),
            'daily'  => collect($d['dailyData'])->map(fn ($x) => (array) $x)->values(),
            'riders' => collect($d['riderPerformance'])->map(fn ($x) => (array) $x)->values(),
            'areas'  => collect($d['areaSummary'])->map(fn ($x) => (array) $x)->values(),
            'cod'    => collect($d['codRows'])->map(fn ($x) => (array) $x)->values(),
        ]);
    }

    /** The website's export file: tab (daily, rider, area, cod), format (pdf / csv / xlsx), from, to. */
    public function exportReport(): mixed
    {
        request()->validate(['format' => 'required|in:' . implode(',', \App\Support\TableExport::FORMATS), 'tab' => 'nullable|in:daily,rider,area,cod'], ['format.in' => 'Choose PDF, CSV or Excel (XLSX).']);
        request()->query->set('export', request()->input('format'));
        $response = $this->web(WebSc::class, 'reports');

        return $response instanceof \Symfony\Component\HttpFoundation\Response && ! $response instanceof \Illuminate\Http\RedirectResponse
            ? $response : $this->actionJson($response);
    }

    // ── Activity log (read only) ─────────────────────────────────

    public function activity(): JsonResponse
    {
        $d = $this->pageData($this->web(WebLogistics::class, 'activity'));

        return response()->json(ApiJson::page($d['logs'], fn ($l) => ['id' => $l->id, 'action' => $l->action_label ?: $l->action, 'description' => $l->description,
            'old_value' => $l->old_value, 'new_value' => $l->new_value, 'reason' => $l->reason, 'ip' => $l->ip_address,
            'created_at' => $l->created_at?->toIso8601String()]));
    }

    // ── Chat (riders, couriers, sellers, admin) ──────────────────

    /** contact_id: open a conversation (marks it read). */
    public function chat(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'chat'));
        $unread = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)
            ->selectRaw('sender_id, COUNT(*) as n')->groupBy('sender_id')->pluck('n', 'sender_id');

        return response()->json([
            'contacts' => $d['contacts']->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role,
                'unread' => (int) ($unread[$u->id] ?? 0)])->values(),
            'contact'  => $d['activeContact'] ? ['id' => $d['activeContact']->id, 'name' => $d['activeContact']->name, 'role' => $d['activeContact']->role] : null,
            'messages' => $d['messages']->map(fn ($m) => self::message($m))->values(),
        ]);
    }

    /** receiver_id, body. */
    public function send(): JsonResponse
    {
        $json = $this->actionJson($this->web(WebSc::class, 'sendMessage'));
        if ($json->getStatusCode() === 200) {
            request()->query->set('contact_id', request()->input('receiver_id'));
            $json->setData($json->getData(true) + $this->chat()->getData(true));
        }
        return $json;
    }

    /** contact_id, after_id: new messages since the last one shown. */
    public function poll(): JsonResponse
    {
        $response = $this->web(WebSc::class, 'pollMessages');
        $rows = $response instanceof JsonResponse ? collect($response->getData(true)) : collect();

        return response()->json(['messages' => $rows->map(fn ($m) => ['id' => $m['id'], 'body' => $m['body'],
            'mine' => (int) $m['sender_id'] === (int) auth()->id(), 'created_at' => $m['created_at']])->values()]);
    }

    private static function message($m): array
    {
        return ['id' => $m->id, 'body' => $m->body, 'mine' => (int) $m->sender_id === (int) auth()->id(), 'created_at' => $m->created_at?->toIso8601String()];
    }

    // ── Account ──────────────────────────────────────────────────

    public function account(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'account'));
        $sc = $d['sc'];

        return response()->json([
            'user' => AuthController::userPayload($sc),
            'company' => ['name' => $sc->name, 'email' => $sc->email, 'municipality' => $sc->assigned_municipality,
                'business_name' => \App\Models\SortingCenterApplication::where('user_id', $sc->id)->where('status', 'approved')->value('business_name')],
            'summary' => $d['summary'],
            // A new password waiting for the emailed code (send it to /account/password/verify).
            'password_code_pending' => (bool) $sc->fresh()->pending_password,
        ]);
    }

    /** name, email; current_password + password + password_confirmation to change the password. */
    public function updateAccount(): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'updateAccount'), fn () => $this->account());
    }
}
