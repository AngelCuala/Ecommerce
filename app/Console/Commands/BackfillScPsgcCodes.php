<?php

namespace App\Console\Commands;

use App\Services\PsgcDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One-time: fill the PSGC chain (region/province/municipality/barangay 9-digit codes) on
 * existing sorting-center coverage areas and riders.
 *
 * - Coverage areas: resolved from their stored barangay_code, or — for legacy rows without
 *   one — from an exact, unique barangay-name match within the SC's assigned municipality.
 * - Riders: copied from their coverage area once that area resolves.
 * - The barangay must belong to the SC's CURRENT assigned municipality; anything else
 *   (mismatch, ambiguous, not found) is reported and left untouched.
 * - Only fills missing codes; never changes names, area links, statuses or deliveries.
 *   Uses DB::table() so updated_at is not changed.
 */
class BackfillScPsgcCodes extends Command
{
    protected $signature = 'sc:backfill-psgc-codes {--dry-run : Report what would change without saving}';

    protected $description = 'Fill PSGC codes on existing sorting-center coverage areas and riders';

    public function handle(PsgcDirectory $psgc): int
    {
        $dry = (bool) $this->option('dry-run');
        $centers = DB::table('users')->where('role', 'sorting_center')->get()->keyBy('id');

        $areaChain = []; // area id => resolved chain
        $done = [];
        $skipped = [];

        foreach (DB::table('delivery_areas')->whereNotNull('sorting_center_id')->get() as $area) {
            $label = "Area #{$area->id} {$area->name} (SC {$area->sorting_center_id})";
            $sc = $centers->get($area->sorting_center_id);
            if (! $sc || ! $sc->assigned_municipality_code) {
                $skipped[] = [$label, 'sorting center has no assigned municipality'];
                continue;
            }
            if ($area->municipality_code && $area->municipality_code !== $sc->assigned_municipality_code) {
                $skipped[] = [$label, "area municipality {$area->municipality_code} differs from the SC's {$sc->assigned_municipality_code}"];
                continue;
            }

            $in = ['barangay_code' => (string) $area->barangay_code];
            if (! $area->barangay_code) {
                $matches = array_values(array_filter(
                    $psgc->barangaysByMunicipality($sc->assigned_municipality_code) ?? [],
                    fn ($b) => strcasecmp($b['name'], trim($area->name)) === 0
                ));
                if (count($matches) !== 1) {
                    $skipped[] = [$label, count($matches) ? 'barangay name is ambiguous' : 'barangay name not found in the municipality'];
                    continue;
                }
                $in = ['barangay_psgc' => $matches[0]['psgc']];
            }

            try {
                $chain = $psgc->resolveBarangayIn($sc->assigned_municipality_code, $in);
            } catch (ValidationException) {
                $skipped[] = [$label, "barangay code {$area->barangay_code} is not a barangay of the SC's municipality"];
                continue;
            }
            if (! $chain['barangay_code']) {
                $skipped[] = [$label, 'PSA publishes no correspondence code for this barangay'];
                continue;
            }
            $areaChain[$area->id] = $chain;

            $fill = $this->missing($area, [
                'region_code'       => $chain['region_code'],
                'province_code'     => $chain['province_code'],
                'municipality_code' => $chain['municipality_code'],
                'barangay_code'     => $chain['barangay_code'],
            ]);
            if ($fill) {
                $done[] = [$label, $this->describe($fill)];
                if (! $dry) DB::table('delivery_areas')->where('id', $area->id)->update($fill);
            }
        }

        foreach (DB::table('riders')->get() as $rider) {
            $label = "Rider #{$rider->id} {$rider->full_name} (SC {$rider->sorting_center_id})";
            $area = $rider->area_id ? DB::table('delivery_areas')->find($rider->area_id) : null;
            if (! $area) {
                if (! $rider->barangay_code) $skipped[] = [$label, 'no coverage area linked'];
                continue;
            }
            if ((int) $area->sorting_center_id !== (int) $rider->sorting_center_id) {
                $skipped[] = [$label, "linked area #{$area->id} belongs to another sorting center"];
                continue;
            }
            $chain = $areaChain[$area->id] ?? null;
            if (! $chain) {
                if (! $rider->barangay_code) $skipped[] = [$label, "linked area #{$area->id} could not be resolved (see above)"];
                continue;
            }

            $fill = $this->missing($rider, [
                'region_code'       => $chain['region_code'],
                'province_code'     => $chain['province_code'],
                'municipality_code' => $chain['municipality_code'],
                'barangay_code'     => $chain['barangay_code'],
            ]);
            if ($fill) {
                $done[] = [$label, $this->describe($fill) . " ({$chain['barangay']})"];
                if (! $dry) DB::table('riders')->where('id', $rider->id)->update($fill);
            }
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . count($done) . ' record(s) ' . ($dry ? 'would be updated' : 'updated') . ':');
        $done ? $this->table(['Record', 'Codes filled'], $done) : $this->line('  (none)');

        $this->warn(count($skipped) . ' record(s) left unchanged:');
        $skipped ? $this->table(['Record', 'Reason'], $skipped) : $this->line('  (none)');

        $this->line('Area links, statuses and deliveries were not changed.');
        return self::SUCCESS;
    }

    /** Only the columns that are currently empty. */
    private function missing(object $row, array $values): array
    {
        return array_filter($values, fn ($v, $col) => $v !== null && empty($row->{$col}), ARRAY_FILTER_USE_BOTH);
    }

    private function describe(array $fill): string
    {
        return collect($fill)->map(fn ($v, $k) => "{$k}={$v}")->implode(', ');
    }
}
