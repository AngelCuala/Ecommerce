<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\UserNotification;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Push notifications to the ALVY Shop app with Firebase Cloud Messaging (HTTP v1). Every in-app notification
 * (UserNotification) is also sent to the user's signed-in phones, so it shows even when the app is closed.
 *
 * Needs the Firebase project's service-account key at config('services.fcm.credentials')
 * (storage/app/private/firebase-service-account.json by default — never committed). Without it nothing is sent.
 */
class FcmPush
{
    private ?array $account = null;

    public function enabled(): bool
    {
        return $this->account() !== null;
    }

    /** Sends one in-app notification to the user's phones; phones Firebase no longer knows are removed. */
    public function notification(UserNotification $n): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $devices = PushDevice::where('user_id', $n->user_id)->get();
        $sent = 0;
        foreach ($devices as $device) {
            $sent += $this->send($device, $n->title, (string) $n->body, [
                'notification_id' => (string) $n->id,
                'link'            => (string) ($n->link ?? ''),
                'type'            => (string) ($n->type ?? 'info'),
            ]) ? 1 : 0;
        }

        return $sent;
    }

    public function send(PushDevice $device, string $title, string $body, array $data = []): bool
    {
        $token = $this->accessToken();
        if (! $token) {
            return false;
        }

        try {
            $res = Http::withToken($token)->timeout(5)->post(
                'https://fcm.googleapis.com/v1/projects/' . $this->account()['project_id'] . '/messages:send',
                ['message' => [
                    'token'        => $device->token,
                    'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 400)],
                    'data'         => array_map('strval', $data),
                    // High priority so a rider's job offer (60 seconds) arrives at once; the app's own channel.
                    'android'      => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'alvy_default', 'sound' => 'default']],
                ]]
            );
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($res->status() === 404 || str_contains((string) $res->body(), 'UNREGISTERED') || str_contains((string) $res->body(), 'INVALID_ARGUMENT')) {
            $device->delete();   // the app was uninstalled or the token changed
            return false;
        }
        if (! $res->successful()) {
            report(new \RuntimeException('FCM ' . $res->status() . ': ' . mb_substr($res->body(), 0, 300)));
            return false;
        }

        return true;
    }

    /** An OAuth token for FCM from the service account (a signed JWT), kept for 50 minutes. */
    private function accessToken(): ?string
    {
        $account = $this->account();
        if (! $account) {
            return null;
        }

        $key = 'fcm-access-token:' . $account['client_email'];
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $token = (function () use ($account) {
            $now = time();
            $jwt = JWT::encode([
                'iss'   => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $account['private_key'], 'RS256');

            $res = Http::asForm()->timeout(8)->post($account['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);
            if (! $res->successful()) {
                report(new \RuntimeException('FCM auth ' . $res->status() . ': ' . mb_substr($res->body(), 0, 300)));
                return null;
            }

            return $res->json('access_token');
        })();

        if ($token) {
            Cache::put($key, $token, now()->addMinutes(50));   // a failure isn't kept, so the next push tries again
        }

        return $token;
    }

    private function account(): ?array
    {
        if ($this->account !== null) {
            return $this->account ?: null;
        }
        $path = (string) config('services.fcm.credentials');
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $this->account = is_array($json) && ! empty($json['project_id']) && ! empty($json['private_key']) && ! empty($json['client_email']) ? $json : [];

        return $this->account ?: null;
    }
}
