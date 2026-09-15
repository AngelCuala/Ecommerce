<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    //  INBOX — shows all threads (order-based + direct)
    // ══════════════════════════════════════════════════════════════
    public function inbox()
    {
        $userId = auth()->id();

        // ── Order-scoped threads ─────────────────────────────────
        $orderIds = Message::whereNotNull('order_id')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->pluck('order_id')->unique();

        $orderThreads = Order::whereIn('id', $orderIds)
            ->with(['user', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest()
            ->get()
            ->map(function ($order) use ($userId) {
                $last   = $order->messages->first();
                $unread = Message::where('order_id', $order->id)
                    ->where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->count();
                return [
                    'type'       => 'order',
                    'id'         => $order->id,
                    'label'      => 'Order #' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                    'sublabel'   => $order->user->name ?? '—',
                    'last_body'  => $last?->body,
                    'last_name'  => $last?->sender->name ?? '',
                    'last_at'    => $last?->created_at,
                    'unread'     => $unread,
                    'route'      => route('messages.show', $order->id),
                ];
            });

        // ── Direct threads ───────────────────────────────────────
        $directThreadKeys = Message::whereNull('order_id')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->pluck('thread_key')->unique();

        $directThreads = collect();
        foreach ($directThreadKeys as $key) {
            $last = Message::where('thread_key', $key)->latest()->first();
            if (! $last) continue;

            $otherId   = $last->sender_id === $userId ? $last->receiver_id : $last->sender_id;
            $otherUser = User::find($otherId);
            $unread    = Message::where('thread_key', $key)
                ->where('receiver_id', $userId)->where('is_read', false)->count();

            $directThreads->push([
                'type'      => 'direct',
                'id'        => $key,
                'label'     => $otherUser?->name ?? 'Unknown',
                'sublabel'  => ucfirst($otherUser?->role ?? ''),
                'last_body' => $last->body,
                'last_name' => $last->sender->name ?? '',
                'last_at'   => $last->created_at,
                'unread'    => $unread,
                'route'     => route('messages.direct', $key),
            ]);
        }

        // Merge and sort by latest message
        $threads = $orderThreads->concat($directThreads)
            ->sortByDesc('last_at')
            ->values();

        $unreadCount = Message::where('receiver_id', $userId)->where('is_read', false)->count();

        // Who can the current user start a direct message with?
        $composeTargets = $this->getComposeTargets();

        return view('messages.inbox', compact('threads', 'unreadCount', 'composeTargets'));
    }

    // ══════════════════════════════════════════════════════════════
    //  ORDER THREAD — show & send
    // ══════════════════════════════════════════════════════════════
    public function show(int $orderId)
    {
        $order = Order::with(['user', 'delivery.courier.user', 'items.book.seller'])
            ->findOrFail($orderId);

        $this->authorizeOrderAccess($order);

        $participants = $this->getOrderParticipants($order);

        $messages = Message::where('order_id', $orderId)->with('sender')->oldest()->get();

        Message::where('order_id', $orderId)
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('messages.show', compact('order', 'messages', 'participants'));
    }

    public function store(Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);
        $this->authorizeOrderAccess($order);

        $request->validate([
            'body'        => 'required|string|max:1000',
            'receiver_id' => 'required|exists:users,id',
        ]);

        Message::create([
            'order_id'    => $orderId,
            'sender_id'   => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'body'        => $request->body,
        ]);

        return back()->with('success', 'Message sent.');
    }

    // ══════════════════════════════════════════════════════════════
    //  DIRECT THREAD — show & send (no order needed)
    // ══════════════════════════════════════════════════════════════
    public function directShow(string $threadKey)
    {
        $userId = auth()->id();
        [$a, $b] = explode('_', $threadKey);

        // Ensure current user is part of this thread
        if ((int)$a !== $userId && (int)$b !== $userId) {
            abort(403);
        }

        $otherId   = (int)$a === $userId ? (int)$b : (int)$a;
        $otherUser = User::findOrFail($otherId);

        // Validate allowed pairs
        $this->authorizeDirectMessage($otherUser);

        $messages = Message::where('thread_key', $threadKey)
            ->with('sender')
            ->oldest()
            ->get();

        Message::where('thread_key', $threadKey)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('messages.direct', compact('threadKey', 'otherUser', 'messages'));
    }

    public function directStore(Request $request, string $threadKey)
    {
        $userId = auth()->id();
        [$a, $b] = explode('_', $threadKey);

        if ((int)$a !== $userId && (int)$b !== $userId) {
            abort(403);
        }

        $otherId   = (int)$a === $userId ? (int)$b : (int)$a;
        $otherUser = User::findOrFail($otherId);
        $this->authorizeDirectMessage($otherUser);

        $request->validate(['body' => 'required|string|max:1000']);

        Message::create([
            'order_id'    => null,
            'thread_key'  => $threadKey,
            'sender_id'   => $userId,
            'receiver_id' => $otherId,
            'body'        => $request->body,
        ]);

        return back()->with('success', 'Message sent.');
    }

    /** Start a new direct conversation */
    public function directNew(Request $request)
    {
        $request->validate(['receiver_id' => 'required|exists:users,id']);

        $otherUser = User::findOrFail($request->receiver_id);
        $this->authorizeDirectMessage($otherUser);

        $threadKey = Message::threadKey(auth()->id(), $otherUser->id);

        return redirect()->route('messages.direct', $threadKey);
    }

    // ══════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════
    private function authorizeOrderAccess(Order $order): void
    {
        $userId  = auth()->id();
        $isBuyer  = $order->user_id === $userId;
        $isSeller = $order->items()->whereHas('book', fn ($q) => $q->where('seller_id', $userId))->exists();
        $isAdmin  = auth()->user()->isAdmin();

        if (! ($isBuyer || $isSeller || $isAdmin)) {
            abort(403);
        }
    }

    /**
     * Allowed direct-message pairs:
     *  Seller  ↔ Buyer  (buyer in one of seller's orders)
     *  Admin   ↔ Seller
     *  Admin   ↔ Buyer
     *  (anyone ↔ anyone for simplicity — restrict if needed)
     */
    private function authorizeDirectMessage(User $other): void
    {
        $me = auth()->user();

        // Admin can message anyone
        if ($me->isAdmin()) return;

        // Anyone can receive a message from Admin
        if ($other->isAdmin()) return;

        // Seller ↔ Buyer
        if (($me->isSeller() && $other->isBuyer()) ||
            ($me->isBuyer()  && $other->isSeller())) return;

        // Seller ↔ Seller (optional — allow for now)
        if ($me->isSeller() && $other->isSeller()) return;

        // Sorting center ↔ Admin (already covered above, but be explicit)
        if ($me->isSortingCenter() && $other->isAdmin()) return;

        abort(403, 'You are not allowed to message this user.');
    }

    private function getOrderParticipants(Order $order): array
    {
        $participants = [];
        if ($order->user) {
            $participants[$order->user->id] = $order->user->name . ' (Buyer)';
        }
        foreach ($order->items as $item) {
            if ($item->book?->seller && !isset($participants[$item->book->seller->id])) {
                $participants[$item->book->seller->id] = $item->book->seller->name . ' (Seller)';
            }
        }
        // Admin
        $admin = User::where('role', 'admin')->first();
        if ($admin && !isset($participants[$admin->id])) {
            $participants[$admin->id] = $admin->name . ' (Admin)';
        }

        unset($participants[auth()->id()]);
        return $participants;
    }

    /** Users the current user can start a direct conversation with */
    private function getComposeTargets(): array
    {
        $me      = auth()->user();
        $targets = [];

        if ($me->isAdmin()) {
            // Admin can message all sellers, buyers, and sorting centers
            User::whereIn('role', ['seller', 'buyer', 'sorting_center'])
                ->where('id', '!=', $me->id)
                ->orderBy('role')->orderBy('name')
                ->get()
                ->each(function ($u) use (&$targets) {
                    $label = match($u->role) {
                        'sorting_center' => $u->name . ' (Sorting Center)',
                        default          => $u->name . ' (' . ucfirst($u->role) . ')',
                    };
                    $targets[$u->id] = $label;
                });
        } elseif ($me->isSeller()) {
            // Seller can message admin and their buyers
            $admin = User::where('role', 'admin')->first();
            if ($admin) $targets[$admin->id] = $admin->name . ' (Admin)';

            // Buyers who ordered from this seller
            $buyerIds = \App\Models\OrderItem::whereHas('book', fn ($q) => $q->where('seller_id', $me->id))
                ->with('order.user')
                ->get()
                ->pluck('order.user')
                ->filter()
                ->unique('id');
            foreach ($buyerIds as $buyer) {
                $targets[$buyer->id] = $buyer->name . ' (Buyer)';
            }
        } elseif ($me->isBuyer()) {
            // Buyer can message sellers of their orders
            $sellerIds = \App\Models\OrderItem::whereHas('order', fn ($q) => $q->where('user_id', $me->id))
                ->with('book.seller')
                ->get()
                ->pluck('book.seller')
                ->filter()
                ->unique('id');
            foreach ($sellerIds as $seller) {
                $targets[$seller->id] = $seller->name . ' (Seller)';
            }
        } elseif ($me->isSortingCenter()) {
            // Sorting center can message admins
            User::where('role', 'admin')->get()
                ->each(fn ($u) => $targets[$u->id] = $u->name . ' (Admin)');
        }

        return $targets;
    }
}
