<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Refund;
use App\Services\Refunds;
use Illuminate\Http\Request;

/**
 * Refunds of approved returns (App\Services\Refunds).
 *   To send: the item is back with the seller. Send the money to the buyer's account now, then type the reference.
 *   Waiting: the buyer hasn't given their details yet, or the item is still on its way back.
 *   Sent:    done, with who sent it and the reference.
 * Details that don't work (the transfer bounced, the name doesn't match the buyer) go back to the buyer to fix.
 */
class RefundController extends Controller
{
    public function __construct(private Refunds $refunds) {}

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['waiting', 'sent'], true) ? $request->query('tab') : 'send';
        $with = ['order.seller', 'user:id,name,email,contact_no', 'sender:id,name'];

        $rows = match ($tab) {
            'send'    => Refund::with($with)->where('status', 'ready_to_send')->oldest('ready_at')->get(),
            'waiting' => Refund::with($with)->whereIn('status', ['awaiting_details', 'waiting_for_item'])->oldest()->get(),
            'sent'    => Refund::with($with)->where('status', 'sent')->latest('sent_at')->take(50)->get(),
        };
        $counts = [
            'send'    => Refund::where('status', 'ready_to_send')->count(),
            'waiting' => Refund::whereIn('status', ['awaiting_details', 'waiting_for_item'])->count(),
            'sent'    => Refund::where('status', 'sent')->count(),
        ];

        return view('admin.refunds.index', compact('rows', 'counts', 'tab'));
    }

    public function sent(Request $request, Refund $refund)
    {
        // A GCash Ref No. is 13 numbers; a Maya or bank reference is 6 to 20 letters or numbers.
        $request->merge(['reference' => preg_replace('/[\s-]/', '', (string) $request->input('reference'))]);
        $gcash = $refund->method === 'gcash';
        $data = $request->validate(['reference' => ['required', 'string', $gcash ? 'regex:/^\d{13}$/' : 'regex:/^[A-Za-z0-9]{6,20}$/']], [
            'reference.required' => 'Type the reference number of the transfer.',
            'reference.regex'    => $gcash ? 'The GCash Ref No. is 13 numbers.' : 'The reference is 6 to 20 letters or numbers.',
        ]);
        $data['reference'] = strtoupper($data['reference']);

        try {
            $this->refunds->markSent($refund, $request->user(), trim($data['reference']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund for ' . $refund->order->number() . ' marked as sent. The buyer was told.');
    }

    public function sendBack(Request $request, Refund $refund)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:200'],
            ['reason.required' => 'Tell the buyer what is wrong with the details.', 'reason.min' => 'The reason must be 10 to 200 characters.']);

        try {
            $this->refunds->sendBack($refund, $request->user(), trim($data['reason']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sent back to the buyer to fix their details.');
    }
}
