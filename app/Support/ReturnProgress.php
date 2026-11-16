<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Support\Carbon;

/**
 * What happened with a buyer's return, for the order page:
 *   - steps():   the short tracker (Requested → Seller's answer → [ALVY review] → Refunded → Picked up → Back to the seller)
 *   - history(): every event with its time, newest last (also merged into the order timeline).
 */
class ReturnProgress
{
    public function __construct(public ReturnRequest $return) {}

    public static function for(ReturnRequest $return): self
    {
        return new self($return->loadMissing(['shipment.pickupRider', 'shipment.sellerRider', 'shipment.sortingCenter', 'order']));
    }

    private function refundedAt(): ?Carbon
    {
        return $this->return->status === 'approved' ? ($this->return->admin_decided_at ?? $this->return->resolved_at) : null;
    }

    /** When the seller answered (lost once ALVY decides a dispute, which writes resolved_at again). */
    private function sellerAnsweredAt(): ?Carbon
    {
        $r = $this->return;
        if ($r->escalated_at) {
            return $r->resolved_at && $r->resolved_at->lte($r->escalated_at) ? $r->resolved_at : null;
        }

        return $r->resolved_at;
    }

    private function sellerAnswer(): ?string
    {
        $r = $this->return;
        if ($r->escalated_at) {
            return 'rejected';
        }

        return in_array($r->status, ['approved', 'rejected'], true) ? $r->status : null;
    }

    /** Where the money goes, in the buyer's words. */
    public function refundTo(): string
    {
        $order = $this->return->order;

        return $order && $order->isPaidOnline() ? 'back to your ' . $order->paymentMethodLabel() : 'to your refund account';
    }

    /** @return array<int, array{key:string,label:string,sub:?string,state:string}> state: done | current | failed | todo | skipped */
    public function steps(): array
    {
        $r = $this->return;
        $rs = $r->shipment;
        $answer = $this->sellerAnswer();
        $steps = [];

        $steps[] = ['key' => 'requested', 'label' => 'Requested', 'sub' => $r->created_at?->format('M j'), 'state' => 'done'];

        $steps[] = match ($answer) {
            'approved' => ['key' => 'seller', 'label' => 'Seller approved', 'sub' => $this->sellerAnsweredAt()?->format('M j'), 'state' => 'done'],
            'rejected' => ['key' => 'seller', 'label' => 'Seller rejected', 'sub' => $this->sellerAnsweredAt()?->format('M j'), 'state' => 'failed'],
            default    => ['key' => 'seller', 'label' => 'Seller reviewing', 'sub' => 'within ' . ReturnRequest::RESPOND_DAYS . ' days', 'state' => 'current'],
        };

        if ($r->escalated_at) {
            $steps[] = match ($r->status) {
                'approved' => ['key' => 'alvy', 'label' => 'ALVY: refund', 'sub' => $r->admin_decided_at?->format('M j'), 'state' => 'done'],
                'closed'   => ['key' => 'alvy', 'label' => 'ALVY: no refund', 'sub' => $r->admin_decided_at?->format('M j'), 'state' => 'failed'],
                default    => ['key' => 'alvy', 'label' => 'ALVY reviewing', 'sub' => 'since ' . $r->escalated_at->format('M j'), 'state' => 'current'],
            };
        }

        $ended = in_array($r->status, ['rejected', 'closed'], true);
        $steps[] = ['key' => 'refund', 'label' => 'Refunded', 'sub' => $this->refundedAt()?->format('M j') ?? ($ended ? 'no refund' : null),
            'state' => $this->refundedAt() ? 'done' : ($ended ? 'skipped' : 'todo')];

        // The item's trip back: picked up from the buyer, then handed to the seller.
        $cancelled = ! $rs || $rs->status === 'cancelled';
        $pickedUp = $rs?->picked_up_at;
        $steps[] = ['key' => 'pickup', 'label' => 'Item picked up',
            'sub' => $pickedUp?->format('M j') ?? ($cancelled ? 'not needed' : ($rs->status === 'pickup_assigned' ? 'rider on the way' : 'waiting for a rider')),
            'state' => $pickedUp ? 'done' : ($cancelled ? 'skipped' : (in_array($r->status, ['requested', 'approved'], true) ? 'current' : 'todo'))];
        $steps[] = ['key' => 'returned', 'label' => 'Back to the seller',
            'sub' => $rs?->returned_at?->format('M j') ?? ($cancelled && ! $pickedUp ? 'not needed' : ($rs && in_array($rs->status, ['at_hub', 'to_seller'], true) ? $rs->statusLabel() : null)),
            'state' => $rs?->returned_at ? 'done' : ($cancelled && ! $pickedUp ? 'skipped' : ($rs && in_array($rs->status, ['at_hub', 'to_seller'], true) ? 'current' : 'todo'))];

        // Only one "current": the first one wins (the rest wait).
        $seen = false;
        foreach ($steps as &$s) {
            if ($s['state'] === 'current') {
                if ($seen) {
                    $s['state'] = 'todo';
                }
                $seen = true;
            }
        }

        return $steps;
    }

    /** @return \Illuminate\Support\Collection<int, array{at:?Carbon,title:string,note:?string,tone:string}> oldest first. */
    public function history()
    {
        $r = $this->return;
        $rs = $r->shipment;
        $money = '₱' . number_format((float) $r->refund_amount, 2);
        $events = collect();
        $add = function (?Carbon $at, string $title, ?string $note = null, string $tone = 'info') use ($events) {
            $events->push(['at' => $at, 'title' => $title, 'note' => $note, 'tone' => $tone]);
        };

        $add($r->created_at, 'Return requested', $r->reason . ' · ' . $money . ($r->isPartial() ? ' (some items)' : '') . ($r->details ? ' — “' . $r->details . '”' : ''));
        if ($rs) {
            if ($rs->pickup_assigned_at) {
                $add($rs->pickup_assigned_at, 'Rider assigned to collect the item', $rs->pickupRider?->full_name);
            }
            if ($rs->picked_up_at) {
                $add($rs->picked_up_at, 'Item picked up from you', $rs->pickupRider?->full_name ? 'by ' . $rs->pickupRider->full_name : null, 'done');
            }
            if ($rs->received_at) {
                $add($rs->received_at, 'Item arrived at the hub', $rs->sortingCenter ? SortingCenters::displayName($rs->sortingCenter) : null);
            }
            if ($rs->seller_assigned_at) {
                $add($rs->seller_assigned_at, 'On the way back to the seller', $rs->sellerRider?->full_name ? 'rider ' . $rs->sellerRider->full_name : null);
            }
            if ($rs->returned_at) {
                $add($rs->returned_at, 'Item handed back to the seller', null, 'done');
            }
            if ($rs->status === 'cancelled') {
                $add($rs->updated_at, 'Return pickup cancelled', $rs->cancel_reason, 'muted');
            }
        }

        $answer = $this->sellerAnswer();
        if ($answer === 'approved' && ! $r->escalated_at) {
            $auto = str_starts_with((string) $r->seller_note, 'Approved automatically');
            $add($this->sellerAnsweredAt(), $auto ? 'Approved automatically' : 'Seller approved the return', $auto ? 'The seller did not answer in time.' : $r->seller_note, 'done');
        } elseif ($answer === 'rejected') {
            $add($this->sellerAnsweredAt() ?? $r->escalated_at, 'Seller rejected the return', $r->seller_note, 'bad');
        }
        if ($r->escalated_at) {
            $add($r->escalated_at, 'You asked ALVY to review it', $r->dispute_note);
        }
        if ($r->admin_decided_at) {
            $add($r->admin_decided_at, $r->admin_decision === 'refund' ? 'ALVY decided: refund' : 'ALVY decided: no refund', $r->admin_note, $r->admin_decision === 'refund' ? 'done' : 'bad');
        }
        if ($at = $this->refundedAt()) {
            $add($at, 'Refunded ' . $money, 'Goes ' . $this->refundTo() . '.', 'done');
        }

        // Stable sort by time; an event without a time keeps its place after the one before it.
        $last = null;

        return $events->map(function ($e, $i) use (&$last) {
            $last = $e['at'] ?? $last;

            return $e + ['sort' => [$last?->getTimestamp() ?? 0, $i]];
        })->sortBy([fn ($a, $b) => $a['sort'][0] <=> $b['sort'][0], fn ($a, $b) => $a['sort'][1] <=> $b['sort'][1]])->values();
    }

    /** The current state in one line, for the card header. */
    public function headline(): string
    {
        $r = $this->return;
        $rs = $r->shipment;

        return match (true) {
            $r->status === 'requested' => 'Waiting for the seller to answer',
            $r->status === 'disputed'  => 'ALVY is reviewing your return',
            $r->status === 'rejected'  => 'The seller rejected your return',
            $r->status === 'closed'    => 'Closed — no refund',
            $r->status === 'approved' && $rs && $rs->isOpen() => 'Refunded · the item still needs to be collected',
            default => 'Refunded',
        };
    }

    public function tone(): string
    {
        return match ($this->return->status) {
            'approved' => 'done', 'rejected', 'closed' => 'bad', default => 'wait',
        };
    }
}
