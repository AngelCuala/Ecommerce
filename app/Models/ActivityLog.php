<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /*
     * One row per important action by any account (buyer, seller, courier, sorting center,
     * admin). "admin_id" / "admin_name" hold whoever did it. Most rows are written by the
     * LogUserActivity middleware; controllers add detail with note().
     */
    protected $fillable = [
        'admin_id', 'admin_name', 'user_role', 'action', 'action_label',
        'subject_type', 'subject_id', 'description', 'status', 'ip_address',
    ];

    /** The visitor's real IP: Cloudflare's header when behind Cloudflare, else the proxy-aware IP. */
    public static function clientIp(): ?string
    {
        $cf = request()->header('CF-Connecting-IP');

        return ($cf && filter_var($cf, FILTER_VALIDATE_IP)) ? $cf : request()->ip();
    }

    /**
     * Add detail (and optionally a subject) to the log row the middleware writes for this
     * request, e.g. ActivityLog::note('Order #000123 · 2 items · ₱2,200', $order).
     */
    public static function note(string $description, $subject = null): void
    {
        request()->attributes->set('activity.description', $description);
        if ($subject instanceof Model) {
            request()->attributes->set('activity.subject', $subject);
        }
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Record an administrative action. This is the single entry point for
     * activity logging — it deliberately stores only non-sensitive metadata
     * (never passwords, OTPs, tokens, or credentials).
     *
     * @param  string  $action   Machine key, e.g. "seller_approved".
     * @param  string  $label    Human label, e.g. "Seller approved".
     */
    public static function record(
        string $action,
        string $label,
        ?string $description = null,
        $subject = null,
        string $status = 'success'
    ): void {
        $user = auth()->user();

        $subjectType = null;
        $subjectId   = null;
        if ($subject instanceof Model) {
            $subjectType = class_basename($subject);
            $subjectId   = $subject->getKey();
        }

        static::create([
            'admin_id'     => $user?->id,
            'admin_name'   => $user?->name,
            'user_role'    => $user?->role,
            'action'       => $action,
            'action_label' => $label,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'description'  => $description,
            'status'       => $status,
            'ip_address'   => static::clientIp(),
        ]);

        // This request is logged; the middleware won't add a second, generic row.
        request()->attributes->set('activity.logged', true);
    }

    /** Distinct action keys present in the log (for filter dropdowns). */
    public static function actionOptions(): array
    {
        return static::query()->select('action', 'action_label')
            ->distinct()->orderBy('action')->get()
            ->mapWithKeys(fn ($r) => [$r->action => $r->action_label ?: $r->action])
            ->all();
    }
}
