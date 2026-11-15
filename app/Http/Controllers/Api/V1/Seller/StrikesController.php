<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\StrikesController as WebStrikes;
use App\Models\ContactStrike;
use App\Services\ContactStrikes;
use Illuminate\Http\JsonResponse;

/** The website's Contact strikes page for the app: the seller's strikes and appeals. */
class StrikesController extends Controller
{
    use CallsWeb;

    public function index(): JsonResponse
    {
        $d = $this->pageData($this->web(WebStrikes::class, 'index'));

        return response()->json([
            'count' => (int) $d['count'], 'limit' => ContactStrikes::LIMIT, 'window_days' => ContactStrikes::WINDOW_DAYS,
            'strikes' => collect($d['list']->items())->map(fn (ContactStrike $s) => [
                'id' => $s->id, 'at' => $s->created_at?->toIso8601String(), 'kinds' => $s->kindsLabel(),
                'message' => $s->message?->body, 'counted' => $s->appeal_status !== 'accepted' && $s->created_at?->gte(now()->subDays(ContactStrikes::WINDOW_DAYS)),
                'appeal_status' => $s->appeal_status, 'appeal_label' => $s->appealLabel(), 'appeal_reason' => $s->appeal_reason,
                'appeal_note' => $s->appeal_note, 'can_appeal' => $s->canAppeal(),
            ])->values(),
        ]);
    }

    /** reason (10–500 characters). */
    public function appeal(ContactStrike $strike): JsonResponse
    {
        $json = $this->actionJson($this->web(WebStrikes::class, 'appeal', ['strike' => $strike]));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->index()->getData(true));
        }
        return $json;
    }
}
