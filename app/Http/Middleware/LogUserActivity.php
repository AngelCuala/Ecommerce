<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\ActivityLabels;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every action that changes something (POST / PUT / PATCH / DELETE) by anyone —
 * buyer, seller, courier, sorting center or admin — in the activity log, with who, what,
 * the result and the visitor's IP. Page views (GET) are not logged.
 *
 * Only metadata is stored: never passwords, codes or form contents. Controllers can add
 * detail with ActivityLog::note(); a request that already called ActivityLog::record()
 * is not logged twice.
 */
class LogUserActivity
{
    /** Background calls that would only flood the log. */
    private const SKIP = ['storage.local.upload'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $before = $request->user();          // for sign-out, who it was before the request
        $response = $next($request);

        try {
            $this->write($request, $response, $before);
        } catch (\Throwable $e) {
            // Logging must never break the action itself.
            Log::warning('Activity log failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function write(Request $request, Response $response, $before): void
    {
        $route = $request->route();
        $name  = $route?->getName();

        if ($request->attributes->get('activity.logged') || in_array($name, self::SKIP, true)) {
            return;
        }

        $user = $request->user() ?? $before;
        $status = $this->failed($request, $response) ? 'failed' : 'success';

        $description = $request->attributes->get('activity.description')
            ?? $this->defaultDescription($request, $route?->parameters() ?? [], $user);

        $subject = $request->attributes->get('activity.subject');

        ActivityLog::create([
            'admin_id'     => $user?->id,
            'admin_name'   => $user?->name ?? 'Guest',
            'user_role'    => $user?->role ?? 'guest',
            'action'       => $name ?: Str::slug($request->method() . ' ' . $request->path(), '_'),
            'action_label' => ActivityLabels::for($name, $request->method(), $request->path()),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => Str::limit((string) $description, 500, '…'),
            'status'       => $status,
            'ip_address'   => ActivityLog::clientIp(),
            'old_value'    => Str::limit((string) $request->attributes->get('activity.old'), 250, '…') ?: null,
            'new_value'    => Str::limit((string) $request->attributes->get('activity.new'), 250, '…') ?: null,
            'reason'       => $request->attributes->get('activity.reason'),
        ]);
    }

    /** Errors shown to the user, an error flash, or an error status. */
    private function failed(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return true;
        }
        if ($response instanceof RedirectResponse && $request->hasSession()) {
            // Only what this request flashed — an error left over from the last page doesn't count.
            $flashed = (array) $request->session()->get('_flash.new', []);
            return in_array('errors', $flashed, true) || in_array('error', $flashed, true);
        }

        return false;
    }

    /** "#12" from the route, or the email typed on a sign-in / sign-up form (never the password). */
    private function defaultDescription(Request $request, array $params, $user): ?string
    {
        $bits = [];
        foreach ($params as $key => $value) {
            $id = is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value;
            if (is_scalar($id)) {
                $bits[] = Str::headline($key) . ' #' . $id;
            }
        }

        if (! $user && is_string($request->input('email'))) {
            $bits[] = 'Email: ' . Str::limit($request->input('email'), 120);
        }

        return $bits ? implode(' · ', $bits) : null;
    }
}
