<?php

namespace App\Models;

use App\Support\ContactGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['order_id', 'thread_key', 'sender_id', 'receiver_id', 'body', 'safety_flag', 'attachment_path', 'product_id', 'is_read'];

    /** What the chat shows under a message with safety_flag (both people see it). */
    public const SAFETY_NOTES = [
        'hidden'   => 'ALVY hid contact details in this message. Keep orders and payments on ALVY: deals made outside ALVY have no buyer protection, refunds or tracking.',
        'reminder' => 'Reminder: keep orders and payments on ALVY. Deals made outside ALVY have no buyer protection, refunds or tracking.',
    ];

    /** Shown at the top of every buyer–seller chat. */
    public const PROTECTION = 'ALVY Buyer Protection: order and pay only through ALVY. Your payment is protected until you receive the item, every parcel has an ALVY waybill you can track, and returns and refunds are handled for you. Phone numbers, emails and links are hidden in chats.';

    /** Kinds found in the message just saved (read once by the sender's "sent" response). */
    public array $guardKinds = [];

    protected static function booted(): void
    {
        // A buyer–seller chat: contact details are hidden before the message is saved (the original is never stored).
        static::creating(function (Message $m) {
            if (! $m->body || ! self::isBuyerSellerChat($m)) {
                return;
            }
            $r = ContactGuard::scrub($m->body);
            $m->guardKinds = $r['kinds'];
            if (ContactGuard::hidesContact($r['kinds'])) {
                $m->body = $r['text'];
                $m->safety_flag = 'hidden';
            } elseif ($r['kinds']) {
                $m->safety_flag = 'reminder';
            }
        });
        // A seller who sent contact details gets a strike.
        static::created(function (Message $m) {
            if ($m->safety_flag === 'hidden' && ($sender = User::find($m->sender_id)) && $sender->isSeller()) {
                app(\App\Services\ContactStrikes::class)->record($sender, $m, $m->guardKinds);
            }
        });
    }

    private static function isBuyerSellerChat(Message $m): bool
    {
        $roles = User::whereKey([$m->sender_id, $m->receiver_id])->pluck('role', 'id');
        $isSeller = fn ($id) => $roles->has($id) && $roles[$id] === 'seller';
        $isBuyer = fn ($id) => $roles->has($id) && in_array($roles[$id], ['buyer', null, ''], true);

        return ($isSeller($m->sender_id) && $isBuyer($m->receiver_id)) || ($isBuyer($m->sender_id) && $isSeller($m->receiver_id));
    }

    public function safetyNote(): ?string
    {
        return self::SAFETY_NOTES[$this->safety_flag] ?? null;
    }

    /** The product the buyer was looking at when they started the chat (shown as a card). */
    public function product(): BelongsTo  { return $this->belongsTo(Product::class)->withTrashed(); }

    public function sender(): BelongsTo   { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'receiver_id'); }
    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }

    /** Generate a stable thread key from two user IDs */
    public static function threadKey(int $a, int $b): string
    {
        return min($a, $b) . '_' . max($a, $b);
    }
}
