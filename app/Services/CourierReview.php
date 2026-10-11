<?php

namespace App\Services;

use App\Mail\CourierApprovedMail;
use App\Mail\CourierRejectedMail;
use App\Models\Courier;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Approving or rejecting a courier application. Used by the sorting center the courier
 * applied to (its own applicants) and by ALVY admins (any applicant).
 */
class CourierReview
{
    public function approve(Courier $courier, User $reviewer): void
    {
        $courier->update([
            'status'           => 'approved',
            'rejection_reason' => null,
            'reviewed_at'      => now(),
            'reviewed_by'      => $reviewer->id,
        ]);
        // Accounts that signed up as couriers are still "pending" until this approval.
        $courier->user->update(['role' => 'courier', 'approval_status' => 'approved']);

        $this->notify($courier, 'Courier application approved',
            'Congratulations! Your courier application has been approved. You can now log in and start accepting deliveries.',
            'info', route('courier.dashboard'));

        $this->email(fn () => Mail::to($courier->user->email)->send(new CourierApprovedMail($courier)));
    }

    public function reject(Courier $courier, User $reviewer, ?string $reason): void
    {
        $courier->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at'      => now(),
            'reviewed_by'      => $reviewer->id,
        ]);

        // An existing (approved) buyer goes back to buying and can re-apply. An account
        // that signed up as a courier stays an applicant and sees the rejection on its status page.
        if ($courier->user->isApproved()) {
            $courier->user->update(['role' => 'buyer']);
        }

        $this->notify($courier, 'Courier application rejected',
            'Your courier application was not approved.' . ($reason ? ' Reason: ' . $reason : '') . ' You may re-apply from your account.',
            'warning', route('courier.register'));

        $this->email(fn () => Mail::to($courier->user->email)->send(new CourierRejectedMail($courier)));
    }

    /** Create an in-app notification for the courier's user. */
    private function notify(Courier $courier, string $title, string $body, string $type, ?string $link): void
    {
        UserNotification::create([
            'user_id' => $courier->user_id,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    /** Send an email but never let a mail failure break the review action. */
    private function email(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Courier review email failed: ' . $e->getMessage());
        }
    }
}
