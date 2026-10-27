<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MessageController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/** Chat (B14 and every role): the web's message threads, with the same rules on who may message whom. */
class MessagesController extends Controller
{
    use CallsWeb;

    public function threads(): JsonResponse
    {
        return $this->actionJson($this->web(MessageController::class, 'widgetThreads'));
    }

    /** type: order (id = order id) or direct (id = "12_34"). Opening a thread marks it read. */
    public function thread(string $type, string $id): JsonResponse
    {
        return $this->actionJson($this->web(MessageController::class, 'widgetThread', ['type' => $type, 'id' => $id]));
    }

    /** body (1–1,000 characters). */
    public function send(string $type, string $id): JsonResponse
    {
        return $this->actionJson($this->web(MessageController::class, 'widgetSend', ['type' => $type, 'id' => $id]));
    }

    /** Start (or open) a chat with receiver_id, e.g. a seller from a product or order page. */
    public function start(): JsonResponse
    {
        $response = $this->web(MessageController::class, 'directNew');
        if ($response instanceof RedirectResponse && preg_match('#/messages/direct/(\d+_\d+)#', $response->getTargetUrl(), $m)) {
            return response()->json(['type' => 'direct', 'id' => $m[1]]);
        }

        return $this->actionJson($response);
    }
}
