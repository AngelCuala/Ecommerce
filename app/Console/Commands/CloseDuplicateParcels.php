<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Parcel;
use Illuminate\Console\Command;

/**
 * One-time cleanup for orders created before the one-route rule, where "Schedule Courier
 * Pickup" opened BOTH a direct-courier delivery and a sorting-center parcel.
 *
 * The courier workflow is kept. The sorting-center parcel is set to 'cancelled' (never
 * deleted) — but only if it was never used: still pending pickup, never confirmed, received,
 * sorted, assigned, transferred or attempted. Anything else is reported for manual review.
 */
class CloseDuplicateParcels extends Command
{
    protected $signature = 'routing:close-duplicate-parcels {--dry-run : Report without changing anything}';

    protected $description = 'Close unused sorting-center parcels on orders already handled by a direct courier';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $parcels = Parcel::with('order')
            ->whereNotNull('order_id')
            ->whereNotIn('status', Parcel::CLOSED_STATUSES)
            ->withCount(['transfers', 'deliveries'])
            ->get();

        $rows = [];
        foreach ($parcels as $parcel) {
            $delivery = Delivery::where('order_id', $parcel->order_id)
                ->whereNotNull('courier_id')
                ->whereIn('status', array_merge(Delivery::OPEN_STATUSES, ['delivered']))
                ->latest('id')->first();
            if (! $delivery) continue; // not a duplicate of a courier delivery

            $unused = $parcel->status === 'pending_pickup'
                && ! $parcel->verified_at && ! $parcel->received_at && ! $parcel->sorted_at
                && ! $parcel->area_id && ! $parcel->transfer_status
                && $parcel->transfers_count === 0 && $parcel->deliveries_count === 0;

            $action = $unused ? ($dry ? 'would cancel' : 'cancelled') : 'LEFT UNCHANGED — parcel was used, review manually';

            $rows[] = [
                'Order #' . str_pad($parcel->order_id, 6, '0', STR_PAD_LEFT) . ' (' . ($parcel->order->status ?? '?') . ')',
                "#{$delivery->id} {$delivery->status} (accepted " . ($delivery->accepted_at?->format('M d H:i') ?? '—') . ', delivered ' . ($delivery->delivered_at?->format('M d H:i') ?? '—') . ')',
                "{$parcel->tracking_number} {$parcel->status} (created {$parcel->created_at?->format('M d H:i')}, SC " . ($parcel->current_sorting_center_id ?? 'none') . ", transfers {$parcel->transfers_count}, attempts {$parcel->deliveries_count})",
                $action,
            ];

            if ($unused && ! $dry) {
                $parcel->update([
                    'status'         => 'cancelled',
                    'failure_reason' => "Duplicate sorting-center request closed: order is handled by direct courier delivery #{$delivery->id} ({$delivery->status}). Closed " . now()->format('M d, Y H:i') . '.',
                ]);
            }
        }

        $rows
            ? $this->table(['Order', 'Courier delivery (kept)', 'Sorting-center parcel', 'Action'], $rows)
            : $this->info('No duplicate sorting-center parcels found.');

        return self::SUCCESS;
    }
}
