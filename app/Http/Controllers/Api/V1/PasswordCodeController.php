<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PasswordChangeController;
use Illuminate\Http\JsonResponse;

/**
 * The code step of a password change, for every account type (the website's PasswordChangeController).
 * Each role's password endpoint answers code_sent: true; the app then sends the emailed code here.
 */
class PasswordCodeController extends Controller
{
    use CallsWeb;

    /** code (6 digits). */
    public function verify(): JsonResponse { return $this->actionJson($this->web(PasswordChangeController::class, 'verify')); }

    public function resend(): JsonResponse { return $this->actionJson($this->web(PasswordChangeController::class, 'resend')); }

    public function cancel(): JsonResponse { return $this->actionJson($this->web(PasswordChangeController::class, 'cancel')); }

    /**
     * Before the form: where a code can go (email always; a text message when SMS is set up and the account
     * has a mobile number — send code_via=sms with the password). After: whether a code is waiting and where it went.
     */
    public function status(): JsonResponse
    {
        $user = auth()->user();
        $number = \App\Services\Sms::numberOf($user);

        return response()->json([
            'pending' => (bool) $user->pending_password,
            'email'   => $user->email,
            'sms'     => PasswordChangeController::smsPossible($user) ? \App\Services\Sms::mask($number) : null,
        ] + ($user->pending_password ? ['sent_to' => PasswordChangeController::sentTo($user)] : []));
    }

    /** What each role's password endpoint adds to its answer. */
    public static function sentFlag(): array
    {
        $user = auth()->user()?->fresh();

        return ['code_sent' => (bool) $user?->pending_password] + ($user?->pending_password ? ['sent_to' => PasswordChangeController::sentTo($user)] : []);
    }
}
