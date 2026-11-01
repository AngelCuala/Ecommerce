<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;

/**
 * Text messages through Semaphore (PH SMS gateway). Off until SEMAPHORE_API_KEY is set in .env;
 * then password-change codes can go to the account's mobile number as well as its email.
 */
class Sms
{
    public static function enabled(): bool
    {
        return filled(config('services.semaphore.key'));
    }

    /** The account's mobile number (09XXXXXXXXX), or null when it has none. */
    public static function numberOf(\App\Models\User $user): ?string
    {
        foreach ([$user->phone, $user->contact_no] as $n) {
            $n = PhoneNumber::normalize($n);
            if ($n && PhoneNumber::isValid($n)) {
                return $n;
            }
        }
        return null;
    }

    /** 0917*****67 — enough for the owner to recognise it. */
    public static function mask(string $number): string
    {
        return substr($number, 0, 4) . str_repeat('*', max(0, strlen($number) - 6)) . substr($number, -2);
    }

    /** Sends one text; throws when the gateway refuses it. */
    public static function send(string $number, string $message): void
    {
        $response = Http::asForm()->timeout(15)->post(config('services.semaphore.url'), array_filter([
            'apikey'     => config('services.semaphore.key'),
            'number'     => $number,
            'message'    => $message,
            'sendername' => config('services.semaphore.sender'),
        ]));

        if (! $response->successful() || isset($response->json()['error']) || (is_array($response->json()) && empty($response->json()))) {
            throw new \RuntimeException('SMS not sent: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 200));
        }
    }
}
