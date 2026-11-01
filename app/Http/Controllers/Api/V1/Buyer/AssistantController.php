<?php

namespace App\Http\Controllers\Api\V1\Buyer;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\AssistantController as WebAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** The web's ALVY Assistant chat for the app (guests and buyers): history, ask, confirm a prepared action, start over. */
class AssistantController extends Controller
{
    use CallsWeb;

    public function history(): mixed { return $this->web(WebAssistant::class, 'history'); }

    /** message. */
    public function ask(): mixed { return $this->web(WebAssistant::class, 'ask'); }

    public function confirm(string $token): mixed { return $this->web(WebAssistant::class, 'confirm', ['token' => $token]); }

    public function reset(): mixed { return $this->web(WebAssistant::class, 'reset'); }
}
