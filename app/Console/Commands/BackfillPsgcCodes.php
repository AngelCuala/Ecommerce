<?php

namespace App\Console\Commands;

use App\Http\Controllers\PsgcController;
use App\Services\ParcelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time: fill missing PSGC municipality/province codes on existing orders and seller
 * applications by matching the stored names against the official PSGC lists.
 *
 * - Only fills codes that are missing; never changes names, statuses or delivery routes.
 * - A record is matched only when the province AND the municipality each match exactly one
 *   PSGC entry. Anything ambiguous or not found is reported and left untouched.
 * - Uses DB::table() so updated_at / history is not changed.
 */
class BackfillPsgcCodes extends Command
{
    protected $signature = 'routing:backfill-psgc-codes {--dry-run : Report matches without saving}';

    protected $description = 'Match existing orders and seller applications to official PSGC municipality codes';

    private array $municipalityCache = [];

    public function handle(PsgcController $psgc, ParcelService $parcels): int
    {
        $dry = (bool) $this->option('dry-run');

        // Official province list; only real numeric PSGC codes count (the offline fallback list does not).
        $provinces = collect($psgc->provinces()->getData(true))
            ->filter(fn ($p) => preg_match('/^\d{9,10}$/', (string) ($p['code'] ?? '')));

        if ($provinces->isEmpty()) {
            $this->error('The PSGC province list is unavailable (offline?). Nothing was changed.');
            return self::FAILURE;
        }

        $provinceIndex = $provinces->groupBy(fn ($p) => $this->normalizeProvince($p['name']));

        $targets = [
            'orders'              => ['name' => 'city',         'label' => fn ($r) => 'Order #' . str_pad($r->id, 6, '0', STR_PAD_LEFT)],
            'seller_applications' => ['name' => 'municipality', 'label' => fn ($r) => "Seller application #{$r->id} (user {$r->user_id})"],
        ];

        $matched = [];
        $unmatched = [];

        foreach ($targets as $table => $cfg) {
            $rows = DB::table($table)->whereNull('municipality_code')
                ->get(['id', 'user_id', $cfg['name'] . ' as municipality', 'province']);

            foreach ($rows as $row) {
                $label = ($cfg['label'])($row);
                $where = trim("{$row->municipality}, {$row->province}", ', ');

                $provMatches = $provinceIndex->get($this->normalizeProvince($row->province), collect());
                if ($provMatches->count() !== 1) {
                    $unmatched[] = [$label, $where, $provMatches->isEmpty() ? 'province not found in PSGC list' : 'province name is ambiguous'];
                    continue;
                }
                $province = $provMatches->first();

                $munKey  = $parcels->normalizeMunicipality($row->municipality);
                $munList = $this->municipalities($psgc, $province['code']);
                if ($munList === null) {
                    $unmatched[] = [$label, $where, 'municipality list unavailable for this province'];
                    continue;
                }
                $candidates = $munKey === '' ? collect() : $munList->filter(fn ($m) => $parcels->normalizeMunicipality($m['name']) === $munKey);

                if ($candidates->count() !== 1) {
                    $unmatched[] = [$label, $where, $candidates->isEmpty() ? 'municipality not found in that province' : 'municipality name is ambiguous'];
                    continue;
                }
                $mun = $candidates->first();

                $matched[] = [$label, $where, "{$mun['name']} ({$mun['code']}), {$province['name']} ({$province['code']})"];
                if (! $dry) {
                    DB::table($table)->where('id', $row->id)->whereNull('municipality_code')->update([
                        'municipality_code' => $mun['code'],
                        'province_code'     => $province['code'],
                    ]);
                }
            }
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . count($matched) . ' matched' . ($dry ? ' (not saved)' : ' and saved') . ':');
        $matched ? $this->table(['Record', 'Stored address', 'PSGC match'], $matched) : $this->line('  (none)');

        $this->warn(count($unmatched) . ' could not be matched (left unchanged):');
        $unmatched ? $this->table(['Record', 'Stored address', 'Reason'], $unmatched) : $this->line('  (none)');

        $this->line('Delivery routes and statuses were not changed.');
        return self::SUCCESS;
    }

    private function municipalities(PsgcController $psgc, string $provinceCode)
    {
        if (! array_key_exists($provinceCode, $this->municipalityCache)) {
            $list = collect($psgc->municipalities($provinceCode)->getData(true))
                ->filter(fn ($m) => preg_match('/^\d{9,10}$/', (string) ($m['code'] ?? '')));
            $this->municipalityCache[$provinceCode] = $list->isEmpty() ? null : $list;
        }
        return $this->municipalityCache[$provinceCode];
    }

    private function normalizeProvince(?string $name): string
    {
        $n = strtolower(trim((string) $name));
        $n = preg_replace('/[^a-z0-9ñ ]/u', ' ', $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }
}
