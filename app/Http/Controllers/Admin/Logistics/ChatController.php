<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $contacts = User::where('id', '!=', auth()->id())
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->get();

        $activeContact = null;
        $messages      = collect();

        if ($request->contact_id) {
            $activeContact = User::findOrFail($request->contact_id);

            $messages = Message::where(function ($q) use ($activeContact) {
                    $q->where('sender_id',   auth()->id())
                      ->where('receiver_id', $activeContact->id);
                })
                ->orWhere(function ($q) use ($activeContact) {
                    $q->where('sender_id',   $activeContact->id)
                      ->where('receiver_id', auth()->id());
                })
                ->oldest()
                ->get();

            // Mark received messages as read
            Message::where('sender_id',   $activeContact->id)
                ->where('receiver_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        $view = request()->routeIs('admin.*') ? 'admin.chat.index' : 'logistics.chat.index';
        return view($view, compact('contacts', 'activeContact', 'messages'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body'        => 'required|string|max:2000',
        ]);

        Message::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'body'        => $request->body,
        ]);

        return back()->with('success', 'Message sent.');
    }

    public function poll(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:users,id',
            'after_id'   => 'nullable|integer',
        ]);

        $messages = Message::where(function ($q) use ($request) {
                $q->where('sender_id',   auth()->id())
                  ->where('receiver_id', $request->contact_id);
            })
            ->orWhere(function ($q) use ($request) {
                $q->where('sender_id',   $request->contact_id)
                  ->where('receiver_id', auth()->id());
            })
            ->when($request->after_id, fn ($q) => $q->where('id', '>', $request->after_id))
            ->oldest()
            ->get();

        return response()->json($messages);
    }
}
