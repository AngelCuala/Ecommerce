<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelPickup;
use App\Models\ParcelTransfer;
use App\Models\Rider;
use App\Models\ShipmentEvent;
use App\Models\ShipmentException;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ServiceArea;
use App\Support\SortingCenters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sorting-center custody of a parcel: receiving it (explicitly, after a scan), sorting, transfers
 * between hubs (create → dispatch → receive), delays, exceptions and their resolution.
 *
 * Every action locks the parcel (and transfer) row inside a transaction and re-checks the state,
 * so two employees acting at once can't both succeed. A refused action throws RuntimeException
 * with the reason and changes nothing. Status changes are recorded as tracking events by the
 * Parcel model (ShipmentTracker); steps without a status change are recorded here.
 */
class ShipmentWorkflow
{
    public function __construct(private ShipmentTracker $tracker, private ParcelService $parcels) {}

    /** Hours a dispatched transfer may take before it is flagged as delayed. */
    public static function transferHours(): int
    {
        return (int) config('logistics.transfer_receipt_hours', 24);
    }

    // ── Finding a parcel by scan ─────────────────────────────────

    /**
     * A scanned / typed waybill or tracking number, among the parcels this center may handle:
     * ones it holds or expects (first-mile), and ones in transit to it. Scanning only finds it.
     */
    public function lookup(User $sc, string $code): ?Parcel
    {
        $code = strtoupper(trim($code));
        $parcel = Parcel::where(fn ($q) => $q->where('tracking_number', $code)->orWhere('waybill_number', $code))->first();
        if (! $parcel) {
            return null;
        }
        $mine = (int) $parcel->current_sorting_center_id === (int) $sc->id;
        $incoming = ParcelTransfer::where('parcel_id', $parcel->id)->where('status', 'in_transit')->where('to_sorting_center_id', $sc->id)->exists();
        // A parcel in transit to another hub that physically turned up here: found, so it can be reported.
        $strayed = $parcel->status === 'in_transfer';

        return $mine || $incoming || $strayed ? $parcel : null;
    }

    // ── Receiving ───────────────────────────────────────────────

    /**
     * Staff confirm the parcel is physically here after checking it. First-mile arrival or an
     * incoming transfer; a parcel addressed to another hub is recorded as a wrong-center exception.
     * Returns a message for the employee.
     */
    public function confirmReceipt(Parcel $parcel, User $sc, User $actor, string $condition = 'good', ?string $notes = null): string
    {
        // The order was cancelled before pickup: the parcel is closed (committed), and nothing is received.
        $closed = DB::transaction(function () use ($parcel) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            if (! $parcel->order || $parcel->order->status !== Order::CANCELLED || ! in_array($parcel->status, ['pending_pickup', 'pickup_approved'], true)) {
                return false;
            }
            $parcel->update(['status' => 'cancelled', 'failure_reason' => 'Order was cancelled.']);
            $this->parcels->notifySeller($parcel, 'Pickup cancelled', "{$parcel->tracking_number}: the order was cancelled, so the parcel was not received.", 'warning');
            return true;
        });
        if ($closed) {
            throw new RuntimeException("{$parcel->tracking_number}: the order was cancelled, so it can't be received.");
        }

        return DB::transaction(function () use ($parcel, $sc, $actor, $condition, $notes) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            $transfer = ParcelTransfer::where('parcel_id', $parcel->id)->whereIn('status', ParcelTransfer::ACTIVE)->lockForUpdate()->latest('id')->first();

            // Already here: an accidental second scan records nothing.
            if ($parcel->isAtHub() && (int) $parcel->current_sorting_center_id === (int) $sc->id && $parcel->received_at) {
                throw new RuntimeException("{$parcel->tracking_number} was already received here on " . $parcel->received_at->format('M j, g:i A') . '. Nothing was recorded again.');
            }

            if ($parcel->status === 'in_transfer') {
                if (! $transfer || $transfer->status !== 'in_transit') {
                    throw new RuntimeException("{$parcel->tracking_number} has no dispatched transfer to receive.");
                }
                if ((int) $transfer->to_sorting_center_id !== (int) $sc->id) {
                    return $this->wrongCenter($parcel, $transfer, $sc, $actor);
                }
                $transfer->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now()]);
                $context = ['transfer_id' => $transfer->id, 'origin_id' => $transfer->from_sorting_center_id, 'destination_id' => $sc->id];
            } elseif (in_array($parcel->status, ['pickup_approved', 'in_transit_to_hub'], true)) {
                if ((int) $parcel->current_sorting_center_id !== (int) $sc->id) {
                    throw new RuntimeException("{$parcel->tracking_number} is not addressed to your sorting center.");
                }
                $context = [];
            } else {
                throw new RuntimeException("{$parcel->tracking_number} is {$parcel->statusLabel()}; it can't be received now.");
            }

            $parcel->withTracking($context + ['meta' => ['condition' => $condition, 'notes' => $notes], 'location_id' => $sc->id])
                ->forceFill(['current_sorting_center_id' => $sc->id, 'transfer_status' => null, 'area_id' => null, 'sorted_at' => null])
                ->update(['status' => 'picked_up', 'received_at' => now(), 'received_by' => $actor->id]);

            // The rider who brought it in has finished the pickup ("At Hub").
            ParcelPickup::where('parcel_id', $parcel->id)->whereIn('status', ParcelPickup::OPEN)
                ->update(['status' => 'received', 'received_at' => now(), 'received_by' => $actor->id]);
            ParcelPickup::where('parcel_id', $parcel->id)->where('status', 'received')->whereNull('picked_up_at')->update(['picked_up_at' => now()]);
            $this->parcels->syncOrder($parcel);

            $message = "{$parcel->tracking_number} received at " . SortingCenters::displayName($sc) . '.';
            if ($condition === 'damaged') {
                $this->openException($parcel, 'damaged_on_arrival', $notes ?: 'Damaged on arrival.', $sc, $actor, $transfer?->id);
                $message .= ' The damage was recorded as an exception for review.';
            }
            if (! $this->tracker->atDestinationCenter($parcel) && ! $this->nextHub($parcel, $sc)) {
                $this->openException($parcel, 'no_route', 'No sorting center in the service area serves ' . ($parcel->destination_municipality ?: 'the destination') . '.', $sc, $actor);
                $message .= ' No sorting center serves the destination yet, so it was flagged for review.';
            }

            return $message;
        });
    }

    private function wrongCenter(Parcel $parcel, ParcelTransfer $transfer, User $sc, User $actor): string
    {
        $intended = SortingCenters::displayName($transfer->toSortingCenter);
        $transfer->update(['status' => 'exception', 'exception_reason' => 'Arrived at ' . SortingCenters::displayName($sc) . " instead of {$intended}."]);
        $this->openException($parcel, 'wrong_center', "Transfer {$transfer->reference} was addressed to {$intended} but arrived at " . SortingCenters::displayName($sc) . '.', $sc, $actor, $transfer->id);

        return "{$parcel->tracking_number} is addressed to {$intended}, not your center. It was NOT received here: a wrong-center exception was recorded and an administrator must resolve it. Keep the parcel aside.";
    }

    // ── Sorting ─────────────────────────────────────────────────

    public function startSorting(Parcel $parcel, User $sc, User $actor): void
    {
        DB::transaction(function () use ($parcel, $sc, $actor) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            $this->assertHeldBy($parcel, $sc);
            if ($parcel->status !== 'picked_up') {
                throw new RuntimeException("{$parcel->tracking_number} is {$parcel->statusLabel()}; only a received parcel can start sorting.");
            }
            $parcel->withTracking(['location_id' => $sc->id])->update(['status' => 'sorting', 'sorting_started_at' => now(), 'sorted_by' => $actor->id]);
        });
    }

    /**
     * Where the parcel goes next from this hub: last-mile delivery from here, a transfer to the hub
     * serving the buyer, or nothing (no hub serves the destination → review).
     *
     * @return array{type: string, center: ?User, label: string}
     */
    public function nextDestination(Parcel $parcel, User $sc): array
    {
        if ($this->parcels->coversDestination($parcel, $sc)) {
            return ['type' => 'local', 'center' => null, 'label' => 'Last-mile delivery from this center'];
        }
        if ($hub = $this->nextHub($parcel, $sc)) {
            return ['type' => 'transfer', 'center' => $hub, 'label' => 'Transfer to ' . SortingCenters::displayName($hub)];
        }
        return ['type' => 'review', 'center' => null, 'label' => 'No sorting center serves ' . ($parcel->destination_municipality ?: 'the destination') . ' — needs review'];
    }

    /** The approved hub in the service area that serves the buyer's municipality (not this one). */
    public function nextHub(Parcel $parcel, User $sc): ?User
    {
        $code = $parcel->order?->municipality_code;
        return $this->eligibleHubs($sc)->first(fn (User $h) => ($code && $h->assigned_municipality_code === $code)
            || ($parcel->destination_municipality && $this->parcels->normalizeMunicipality($h->assigned_municipality) === $this->parcels->normalizeMunicipality($parcel->destination_municipality)));
    }

    /** Hubs a parcel may be transferred to: approved, with a municipality, inside the service area. */
    public function eligibleHubs(User $from): Collection
    {
        return User::where('role', 'sorting_center')->where('approval_status', 'approved')
            ->whereKeyNot($from->id)->whereNotNull('assigned_municipality')
            ->when(ServiceArea::enabled(), fn ($q) => $q->whereIn('assigned_municipality_code', ServiceArea::localityCodes()))
            ->orderBy('name')->get();
    }

    // ── Transfers ───────────────────────────────────────────────

    public function createTransfer(Parcel $parcel, User $sc, User $actor, int $destinationId, ?int $transporterId = null, ?string $reason = null): ParcelTransfer
    {
        return DB::transaction(function () use ($parcel, $sc, $actor, $destinationId, $transporterId, $reason) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            $this->assertHeldBy($parcel, $sc);
            if (! in_array($parcel->status, ['picked_up', 'sorting', 'sorted'], true) || ! $parcel->received_at) {
                throw new RuntimeException("{$parcel->tracking_number} is {$parcel->statusLabel()}; only a parcel received at your hub can be transferred.");
            }
            if (ParcelTransfer::where('parcel_id', $parcel->id)->whereIn('status', ParcelTransfer::ACTIVE)->lockForUpdate()->exists()) {
                throw new RuntimeException("{$parcel->tracking_number} already has an active transfer.");
            }
            if (\App\Models\ParcelDelivery::where('parcel_id', $parcel->id)->whereIn('status', \App\Models\ParcelDelivery::OPEN)->exists()) {
                throw new RuntimeException("{$parcel->tracking_number} is with a delivery rider.");
            }

            $destination = $this->eligibleHubs($sc)->firstWhere('id', $destinationId);
            if (! $destination) {
                throw new RuntimeException('Choose one of the listed sorting centers (approved, inside ' . ServiceArea::label() . ').');
            }
            $suggested = $this->nextHub($parcel, $sc);
            if ($suggested && $suggested->id !== $destination->id && trim((string) $reason) === '') {
                throw new RuntimeException(SortingCenters::displayName($suggested) . ' serves this address. Give a reason to route it through ' . SortingCenters::displayName($destination) . ' instead.');
            }

            $transporter = null;
            if ($transporterId) {
                $transporter = Rider::where('sorting_center_id', $sc->id)->whereKey($transporterId)->first();
                if (! $transporter || ! $transporter->isApproved() || ! $transporter->is_active || ! $transporter->can_transport_transfers) {
                    throw new RuntimeException('Choose one of your active riders allowed to transport hub transfers.');
                }
            }

            $transfer = ParcelTransfer::create([
                'parcel_id' => $parcel->id, 'from_sorting_center_id' => $sc->id, 'to_sorting_center_id' => $destination->id,
                'initiated_by' => $actor->id, 'reason' => $reason, 'status' => 'pending', 'transporter_rider_id' => $transporter?->id,
            ]);
            $parcel->update(['transfer_status' => 'outgoing']);
            \App\Models\RiderJobOffer::where('parcel_id', $parcel->id)->where('status', 'pending')->update(['status' => 'cancelled']);

            $this->tracker->record($parcel, 'transfer_created', [
                'transfer_id' => $transfer->id, 'origin_id' => $sc->id, 'destination_id' => $destination->id, 'note' => $reason,
            ]);
            if ($transporter) {
                $this->notifyTransporter($transfer->fresh(), $transporter, $parcel);
            }

            return $transfer;
        });
    }

    public function dispatchTransfer(ParcelTransfer $transfer, User $sc, User $actor): ParcelTransfer
    {
        return DB::transaction(function () use ($transfer, $sc, $actor) {
            $transfer = ParcelTransfer::lockForUpdate()->findOrFail($transfer->id);
            $parcel = Parcel::lockForUpdate()->findOrFail($transfer->parcel_id);
            if ((int) $transfer->from_sorting_center_id !== (int) $sc->id) {
                throw new RuntimeException('Only the sending sorting center can dispatch this transfer.');
            }
            if ($transfer->status !== 'pending') {
                throw new RuntimeException("Transfer {$transfer->reference} is already " . strtolower($transfer->statusLabel()) . '.');
            }
            $this->assertHeldBy($parcel, $sc);
            if (! in_array($parcel->status, ['picked_up', 'sorting', 'sorted'], true) || ! $parcel->received_at) {
                throw new RuntimeException("{$parcel->tracking_number} is {$parcel->statusLabel()}; it must be received at your hub before dispatch.");
            }

            $transfer->update(['status' => 'in_transit', 'dispatched_at' => now(), 'dispatched_by' => $actor->id,
                'expected_by' => now()->addHours(self::transferHours())]);
            $parcel->withTracking(['transfer_id' => $transfer->id, 'origin_id' => $sc->id, 'destination_id' => $transfer->to_sorting_center_id,
                'location_id' => $sc->id, 'note' => 'Transfer ' . $transfer->reference])
                ->update(['status' => 'in_transfer', 'area_id' => null]);

            return $transfer;
        });
    }

    /** Before dispatch only: the parcel never left and stays at the sending hub. */
    public function cancelTransfer(ParcelTransfer $transfer, User $sc, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($transfer, $sc, $actor, $reason) {
            $transfer = ParcelTransfer::lockForUpdate()->findOrFail($transfer->id);
            if ((int) $transfer->from_sorting_center_id !== (int) $sc->id) {
                throw new RuntimeException('Only the sending sorting center can cancel this transfer.');
            }
            if ($transfer->status !== 'pending') {
                throw new RuntimeException("Transfer {$transfer->reference} was already " . strtolower($transfer->statusLabel())
                    . ($transfer->status === 'in_transit' ? '. A dispatched transfer can\'t be cancelled; report an exception instead.' : '.'));
            }
            $transfer->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'exception_reason' => $reason]);
            $parcel = Parcel::lockForUpdate()->findOrFail($transfer->parcel_id);
            $parcel->update(['transfer_status' => null]);
            $this->tracker->record($parcel, 'transfer_cancelled', ['transfer_id' => $transfer->id, 'origin_id' => $sc->id,
                'destination_id' => $transfer->to_sorting_center_id, 'note' => $reason]);
        });
    }

    /** Dispatched transfers past their expected time and still not received: flagged once each. */
    public function flagDelayedTransfers(): int
    {
        $n = 0;
        foreach (ParcelTransfer::where('status', 'in_transit')->whereNull('delayed_at')->where('expected_by', '<', now())->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$n) {
                $transfer = ParcelTransfer::lockForUpdate()->find($id);
                if (! $transfer || $transfer->status !== 'in_transit' || $transfer->delayed_at) {
                    return;
                }
                $transfer->update(['delayed_at' => now()]);
                $parcel = $transfer->parcel;
                $this->tracker->record($parcel, 'transfer_delayed', [
                    'transfer_id' => $transfer->id, 'origin_id' => $transfer->from_sorting_center_id, 'destination_id' => $transfer->to_sorting_center_id,
                    'location_id' => null, 'dedupe_key' => 'transfer:' . $transfer->id . ':delayed',
                    'note' => 'Not yet confirmed received by the destination sorting center; under investigation',
                ]);
                $this->openException($parcel, 'delayed_transfer', "Transfer {$transfer->reference} dispatched " . $transfer->dispatched_at?->format('M j, g:i A')
                    . ' was not confirmed received within ' . self::transferHours() . ' hours.', null, null, $transfer->id);
                foreach ([$transfer->from_sorting_center_id, $transfer->to_sorting_center_id] as $hub) {
                    $this->parcels->notify((int) $hub, 'Transfer delayed', "Transfer {$transfer->reference} ({$parcel->tracking_number}) is overdue. Please check where it is.", 'warning', route('sc.tracking', ['tab' => 'delayed']));
                }
                $n++;
            });
        }
        return $n;
    }

    // ── Exceptions ──────────────────────────────────────────────

    public function openException(Parcel $parcel, string $type, string $details, ?User $sc, ?User $actor, ?int $transferId = null): ShipmentException
    {
        $existing = ShipmentException::where('parcel_id', $parcel->id)->where('type', $type)->where('status', 'open')
            ->when($transferId, fn ($q) => $q->where('transfer_id', $transferId))->first();
        if ($existing) {
            return $existing;   // one open case per problem
        }
        $exception = ShipmentException::create([
            'parcel_id' => $parcel->id, 'transfer_id' => $transferId, 'type' => $type, 'details' => $details,
            'sorting_center_id' => $sc?->id, 'reported_by' => $actor?->id, 'status' => 'open',
        ]);
        $this->tracker->record($parcel, 'exception_reported', ['location_id' => $sc?->id ?? $parcel->current_sorting_center_id,
            'transfer_id' => $transferId, 'note' => ShipmentException::TYPES[$type] ?? $type, 'meta' => ['exception_id' => $exception->id, 'details' => $details],
            'dedupe_key' => 'exception:' . $exception->id . ':reported']);
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            $this->parcels->notify((int) $adminId, 'Shipment exception', "{$parcel->tracking_number}: " . $exception->typeLabel() . '. ' . $details, 'warning', route('admin.logistics.shipments.show', $parcel));
        }
        return $exception;
    }

    /**
     * An administrator resolves an exception with a recorded reason. Actions:
     *   note      record the resolution only
     *   custody   the parcel is physically at $centerId: that hub takes custody (received there)
     *   lost      the parcel is declared lost
     */
    public function resolveException(ShipmentException $exception, User $admin, string $action, string $reason, ?int $centerId = null): void
    {
        DB::transaction(function () use ($exception, $admin, $action, $reason, $centerId) {
            $exception = ShipmentException::lockForUpdate()->findOrFail($exception->id);
            if (! $exception->isOpen()) {
                throw new RuntimeException('This exception was already resolved.');
            }
            $parcel = Parcel::lockForUpdate()->findOrFail($exception->parcel_id);
            $transfer = $exception->transfer_id ? ParcelTransfer::lockForUpdate()->find($exception->transfer_id) : null;

            if ($action === 'custody') {
                $hub = User::where('role', 'sorting_center')->find($centerId);
                if (! $hub || ! ServiceArea::coversLocality($hub->assigned_municipality_code)) {
                    throw new RuntimeException('Choose a sorting center inside the service area.');
                }
                if ($transfer && in_array($transfer->status, ['in_transit', 'exception'], true)) {
                    $transfer->update(['status' => 'received', 'received_at' => now(), 'received_by' => $admin->id,
                        'exception_reason' => trim(($transfer->exception_reason ? $transfer->exception_reason . ' ' : '') . 'Resolved: custody at ' . SortingCenters::displayName($hub) . '.')]);
                }
                $parcel->withTracking(['location_id' => $hub->id, 'note' => 'Custody confirmed by an administrator: ' . $reason, 'transfer_id' => $transfer?->id])
                    ->forceFill(['current_sorting_center_id' => $hub->id, 'transfer_status' => null, 'area_id' => null, 'sorted_at' => null]);
                if ($parcel->status === 'in_transfer') {
                    $parcel->update(['status' => 'picked_up', 'received_at' => now(), 'received_by' => $admin->id]);
                } else {
                    $parcel->save();
                }
            } elseif ($action === 'lost') {
                $transfer?->update(['status' => 'exception', 'exception_reason' => 'Declared lost: ' . $reason]);
                $parcel->update(['status' => 'lost', 'failure_reason' => $reason, 'transfer_status' => null]);
            } elseif ($action !== 'note') {
                throw new RuntimeException('Choose how to resolve it.');
            }

            $exception->update(['status' => 'resolved', 'resolution' => $reason, 'resolved_by' => $admin->id, 'resolved_at' => now()]);
            $this->tracker->record($parcel, 'exception_resolved', ['note' => $reason, 'meta' => ['exception_id' => $exception->id, 'action' => $action],
                'dedupe_key' => 'exception:' . $exception->id . ':resolved']);
        });
    }

    /** An administrator corrects the tracking record: the original event stays, a correction is appended. */
    public function correctEvent(ShipmentEvent $event, User $admin, string $reason): ShipmentEvent
    {
        $parcel = $event->parcel;
        $subject = $parcel ?? $event->order;
        return $this->tracker->record($subject, 'correction', ['corrects_event_id' => $event->id, 'note' => $reason,
            'location_id' => $event->location_id, 'meta' => ['corrected' => $event->event_type, 'corrected_at' => $event->occurred_at?->toDateTimeString()]]);
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function assertHeldBy(Parcel $parcel, User $sc): void
    {
        if ((int) $parcel->current_sorting_center_id !== (int) $sc->id) {
            throw new RuntimeException("{$parcel->tracking_number} is not at your sorting center.");
        }
    }

    private function notifyTransporter(ParcelTransfer $transfer, Rider $rider, Parcel $parcel): void
    {
        UserNotification::firstOrCreate(['user_id' => $rider->user_id, 'dedupe_key' => 'transfer:' . $transfer->id . ':transporter'], [
            'title' => 'Transfer task: ' . $transfer->reference,
            'body'  => 'Carry ' . ($parcel->waybill_number ?: $parcel->tracking_number) . ' from ' . SortingCenters::displayName($transfer->fromSortingCenter)
                . ' to ' . SortingCenters::displayName($transfer->toSortingCenter) . '. Assigned ' . now()->format('M j, g:i A') . '.',
            'type'  => 'info', 'link' => route('courier.rider'),
        ]);
    }
}
