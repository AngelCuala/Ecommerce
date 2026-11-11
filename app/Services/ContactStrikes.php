<?php

namespace App\Services;

use App\Models\ContactStrike;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;

/**
 * Three strikes for sellers who try to take a buyer off ALVY (contact details in a buyer chat):
 *   1st  warning
 *   2nd  final warning
 *   3rd  an administrator reviews the store (Compliance); nothing happens to the account automatically,
 *        because a number can be hidden by mistake. The administrator decides (warning, suspension…).
 * Strikes count over the last WINDOW_DAYS; one chat gives at most one strike a day (a burst of messages is one).
 */
class ContactStrikes
{
    public const LIMIT = 3;
    public const WINDOW_DAYS = 90;

    public function record(User $seller, Message $message, array $kinds): ?ContactStrike
    {
        $thread = $message->order_id ? 'order:' . $message->order_id : 'direct:' . $message->thread_key;
        if (ContactStrike::where('user_id', $seller->id)->where('thread', $thread)->where('created_at', '>=', now()->subDay())->exists()) {
            return null;
        }
        $strike = ContactStrike::create(['user_id' => $seller->id, 'message_id' => $message->id, 'thread' => $thread,
            'kinds' => implode(',', array_intersect($kinds, \App\Support\ContactGuard::HIDDEN))]);
        $n = $this->count($seller);

        [$title, $body, $type] = match (true) {
            $n === 1 => ['Warning: keep deals on ALVY',
                'Contact details you sent a buyer were hidden. Sharing numbers, emails, links or social accounts to deal outside ALVY is not allowed. This is strike 1 of ' . self::LIMIT . '.', 'warning'],
            $n === 2 => ['Final warning: keep deals on ALVY',
                'Contact details you sent a buyer were hidden again. This is strike 2 of ' . self::LIMIT . '. One more and your store will be reviewed by ALVY.', 'warning'],
            default  => ['Your store is under review',
                'You reached ' . $n . ' strikes for sharing contact details with buyers. An ALVY administrator will review your store.', 'error'],
        };
        UserNotification::create(['user_id' => $seller->id, 'title' => $title, 'body' => $body . ' If this was a mistake, you can appeal it.',
            'type' => $type, 'link' => route('seller.strikes')]);

        if ($n === self::LIMIT) {
            foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
                UserNotification::create(['user_id' => $adminId, 'type' => 'warning', 'title' => 'Seller to review: off-platform contact',
                    'body' => ($seller->storeName() ?? $seller->name) . ' reached ' . self::LIMIT . ' strikes for sharing contact details with buyers.',
                    'link' => route('admin.compliance.index') . '#contact-strikes']);
            }
        }
        \App\Models\ActivityLog::note('Contact details hidden in a buyer chat (strike ' . $n . '): ' . $strike->kindsLabel(), $seller);

        return $strike;
    }

    /** Strikes in the last WINDOW_DAYS (an accepted appeal doesn't count). */
    public function count(User $seller): int
    {
        return ContactStrike::where('user_id', $seller->id)->counted()->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))->count();
    }

    /** The seller says a strike was a mistake; the administrators are told. One appeal per strike. */
    public function appeal(ContactStrike $strike, User $seller, string $reason): void
    {
        if ((int) $strike->user_id !== (int) $seller->id) {
            throw new \RuntimeException('This strike is not yours.');
        }
        if (! $strike->canAppeal()) {
            throw new \RuntimeException($strike->appeal_status ? 'You already appealed this strike.' : 'This strike is too old to appeal.');
        }
        $strike->update(['appeal_status' => 'pending', 'appeal_reason' => $reason, 'appealed_at' => now()]);
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            UserNotification::create(['user_id' => $adminId, 'type' => 'info', 'title' => 'Strike appeal to review',
                'body' => $seller->storeName() . ' appealed a contact strike: "' . \Illuminate\Support\Str::limit($reason, 120) . '"',
                'link' => route('admin.compliance.index') . '#contact-strikes']);
        }
        \App\Models\ActivityLog::note('Appealed contact strike #' . $strike->id, $seller);
    }

    /** An administrator accepts (the strike no longer counts) or rejects the appeal; the seller is told why. */
    public function decide(ContactStrike $strike, User $admin, bool $accept, string $note): void
    {
        if ($strike->appeal_status !== 'pending') {
            throw new \RuntimeException('There is no appeal waiting on this strike.');
        }
        $strike->update(['appeal_status' => $accept ? 'accepted' : 'rejected', 'appeal_note' => $note,
            'appeal_decided_by' => $admin->id, 'appeal_decided_at' => now(), 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        UserNotification::create(['user_id' => $strike->user_id, 'type' => $accept ? 'success' : 'warning',
            'title' => $accept ? 'Strike appeal accepted' : 'Strike appeal rejected',
            'body' => ($accept ? 'The strike from ' : 'The strike from ') . $strike->created_at->copy()->tz(config('app.business_timezone'))->format('M j, Y')
                . ($accept ? ' no longer counts. ' : ' still counts. ') . 'ALVY: ' . $note,
            'link' => route('seller.strikes')]);
        \App\Models\ActivityLog::note(($accept ? 'Accepted' : 'Rejected') . ' strike appeal #' . $strike->id . ': ' . $note, $strike->user);
    }
}
