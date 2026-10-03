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
    //  FLOATING CHAT WIDGET — JSON endpoints (no page reload)
    // ══════════════════════════════════════════════════════════════

    /** Return all threads (order + direct) as JSON for the chat list. */
    public function widgetThreads()
    {
        $userId = auth()->id();
        $me     = auth()->user();

        // Order threads
        $orderIds = Message::whereNotNull('order_id')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->pluck('order_id')->unique();

        $threads = collect();

        foreach ($orderIds as $oid) {
            $last = Message::where('order_id', $oid)->latest()->first();
            if (! $last) continue;
            $threads->push([
                'type'      => 'order',
                'key'       => 'order:' . $oid,
                'label'     => 'Order #' . str_pad($oid, 6, '0', STR_PAD_LEFT),
                'position'  => 'Order',
                'last_body' => $last->body,
                'last_at'   => $last->created_at->diffForHumans(),
                'ts'        => $last->created_at->timestamp,
                'unread'    => Message::where('order_id', $oid)->where('receiver_id', $userId)->where('is_read', false)->count(),
            ]);
        }

        // Direct threads (existing conversations)
        $seenUserIds = [];
        $keys = Message::whereNull('order_id')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->pluck('thread_key')->unique();

        foreach ($keys as $key) {
            $last = Message::where('thread_key', $key)->latest()->first();
            if (! $last) continue;
            $otherId   = $last->sender_id === $userId ? $last->receiver_id : $last->sender_id;
            $otherUser = User::find($otherId);
            $seenUserIds[] = $otherId;
            $threads->push([
                'type'      => 'direct',
                'key'       => 'direct:' . $key,
                'label'     => $otherUser?->name ?? 'Unknown',
                'position'  => $this->roleLabel($otherUser?->role),
                'last_body' => $last->body,
                'last_at'   => $last->created_at->diffForHumans(),
                'ts'        => $last->created_at->timestamp,
                'unread'    => Message::where('thread_key', $key)->where('receiver_id', $userId)->where('is_read', false)->count(),
            ]);
        }

        // Admins and sellers see every account they can message, even with no
        // conversation yet. Admin -> everyone; others -> their allowed recipients.
        if ($me->isAdmin() || $me->isSeller() || $me->isCourier() || $me->isSortingCenter()) {
            if ($me->isAdmin()) {
                $contactIds = User::where('id', '!=', $userId)
                    ->whereIn('role', ['admin', 'seller', 'buyer', 'sorting_center', 'courier'])
                    ->pluck('id')->all();
            } else {
                // Reuse the allowed-recipient rules for this role.
                $contactIds = array_keys($this->getComposeTargets());
            }

            User::whereIn('id', $contactIds)
                ->orderBy('name')
                ->get()
                ->each(function ($u) use (&$threads, $seenUserIds, $userId) {
                    if (in_array($u->id, $seenUserIds)) return; // already has a thread
                    $threads->push([
                        'type'      => 'direct',
                        'key'       => 'direct:' . Message::threadKey($userId, $u->id),
                        'label'     => $u->name,
                        'position'  => $this->roleLabel($u->role),
                        'last_body' => '',
                        'last_at'   => '',
                        'ts'        => 0,
                        'unread'    => 0,
                    ]);
                });
        }

        // Existing conversations first (newest), then the rest alphabetically.
        $threads = $threads
            ->sortBy(fn ($t) => [$t['ts'] === 0 ? 1 : 0, -$t['ts'], strtolower($t['label'])])
            ->values();

        return response()->json([
            'threads' => $threads,
            'unread'  => Message::where('receiver_id', $userId)->where('is_read', false)->count(),
        ]);
    }

    /** Human-friendly label for a user role. */
    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin'          => 'Admin',
            'seller'         => 'Seller',
            'buyer'          => 'Buyer',
            'sorting_center' => 'Sorting Center',
            'courier'        => 'Courier',
            default          => $role ? ucfirst($role) : 'User',
        };
    }

    /** Return the messages of a single thread as JSON + mark them read. */
    public function widgetThread(string $type, string $id)
    {
        $userId = auth()->id();

        if ($type === 'order') {
            $order = Order::findOrFail((int) $id);
            $this->authorizeOrderAccess($order);

            $messages = Message::where('order_id', $order->id)->with('sender')->oldest()->get();
            Message::where('order_id', $order->id)->where('receiver_id', $userId)
                ->where('is_read', false)->update(['is_read' => true]);

            $title = 'Order #' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
        } else {
            [$a, $b] = explode('_', $id);
            if ((int) $a !== $userId && (int) $b !== $userId) abort(403);
            $otherId   = (int) $a === $userId ? (int) $b : (int) $a;
            $otherUser = User::findOrFail($otherId);
            $this->authorizeDirectMessage($otherUser);

            $messages = Message::where('thread_key', $id)->with('sender')->oldest()->get();
            Message::where('thread_key', $id)->where('receiver_id', $userId)
                ->where('is_read', false)->update(['is_read' => true]);

            $title = $otherUser->name;
        }

        return response()->json([
            'title'    => $title,
            'messages' => $messages->map(fn ($m) => [
                'body'  => $m->body,
                'mine'  => $m->sender_id === $userId,
                'name'  => $m->sender->name ?? '',
                'at'    => $m->created_at->format('M d, H:i'),
            ]),
        ]);
    }

    /** Send a message from the widget. */
    public function widgetSend(Request $request, string $type, string $id)
    {
        $request->validate(['body' => 'required|string|max:1000']);
        $userId = auth()->id();

        if ($type === 'order') {
            $order = Order::findOrFail((int) $id);
            $this->authorizeOrderAccess($order);

            // Receiver = the other participant of this order thread
            $lastFromOther = Message::where('order_id', $order->id)
                ->where('sender_id', '!=', $userId)->latest()->first();
            $receiverId = $lastFromOther?->sender_id
                ?? ($order->user_id === $userId ? optional($order->items()->with('book')->first())->book->seller_id : $order->user_id);

            Message::create([
                'order_id'    => $order->id,
                'sender_id'   => $userId,
                'receiver_id' => $receiverId,
                'body'        => $request->body,
            ]);
        } else {
            [$a, $b] = explode('_', $id);
            if ((int) $a !== $userId && (int) $b !== $userId) abort(403);
            $otherId   = (int) $a === $userId ? (int) $b : (int) $a;
            $otherUser = User::findOrFail($otherId);
            $this->authorizeDirectMessage($otherUser);

            Message::create([
                'order_id'    => null,
                'thread_key'  => $id,
                'sender_id'   => $userId,
                'receiver_id' => $otherId,
                'body'        => $request->body,
            ]);
        }

        return response()->json(['ok' => true]);
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

        // Courier ↔ (buyer/seller of an order they're assigned to deliver)
        if ($me->isCourier() && in_array($other->id, $this->courierContactIds($me), true)) return;

        abort(403, 'You are not allowed to message this user.');
    }

    /**
     * IDs of users a courier is allowed to message: the admin(s), plus the
     * buyer and seller(s) of every order assigned to that courier's deliveries.
     * Couriers can only reach people tied to their own assigned orders.
     */
    private function courierContactIds(User $courierUser): array
    {
        $courier = $courierUser->courier;
        if (! $courier) return [];

        $ids = User::where('role', 'admin')->pluck('id')->all();

        $orderIds = \App\Models\Delivery::where('courier_id', $courier->id)->pluck('order_id');

        // Buyers of those orders
        $ids = array_merge($ids, Order::whereIn('id', $orderIds)->pluck('user_id')->all());

        // Sellers of the books in those orders
        $sellerIds = \App\Models\OrderItem::whereIn('order_id', $orderIds)
            ->with('book')->get()
            ->pluck('book.seller_id')->filter()->all();
        $ids = array_merge($ids, $sellerIds);

        return array_values(array_unique(array_filter($ids)));
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
        } elseif ($me->isCourier()) {
            // Courier can message admin + buyers/sellers of their assigned orders
            $ids = $this->courierContactIds($me);
            User::whereIn('id', $ids)->where('id', '!=', $me->id)
                ->orderBy('role')->orderBy('name')
                ->get()
                ->each(function ($u) use (&$targets) {
                    $label = match ($u->role) {
                        'admin'  => ' (Admin)',
                        'seller' => ' (Seller)',
                        default  => ' (Buyer)',
                    };
                    $targets[$u->id] = $u->name . $label;
                });
        }

        return $targets;
    }
}
