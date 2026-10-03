<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'action_label',
        'subject_type', 'subject_id', 'description', 'status', 'ip_address',
    ];

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
            'action'       => $action,
            'action_label' => $label,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'description'  => $description,
            'status'       => $status,
            'ip_address'   => request()->ip(),
        ]);
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
