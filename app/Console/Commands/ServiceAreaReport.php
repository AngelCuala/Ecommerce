<?php

namespace App\Console\Commands;

use App\Models\Courier;
use App\Models\SellerApplication;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\ServiceArea;
use Illuminate\Console\Command;

/**
 * Lists records saved before the service area (Laguna) was enforced that lie outside it.
 * Read-only: nothing is changed or deleted. They are blocked from new use (checkout, approval,
 * dispatch) and must be moved into the area when next edited.
 */
class ServiceAreaReport extends Command
{
    protected $signature = 'service-area:report';
    protected $description = 'List addresses, stores, couriers and sorting centers outside the service area (read-only)';

    public function handle(): int
    {
        if (! ServiceArea::enabled()) {
            $this->info('No service area is configured (SERVICE_AREA_PROVINCES is empty).');
            return self::SUCCESS;
        }
        $this->info('Service area: ' . ServiceArea::label());

        $sets = [
            'Buyer addresses' => UserAddress::with('user:id,email')->get()
                ->reject(fn ($a) => ServiceArea::covers($a->municipality_code, $a->province, $a->city, $a->province_code))
                ->map(fn ($a) => [$a->id, $a->user->email ?? '-', "{$a->city}, {$a->province}"]),
            'Seller stores' => SellerApplication::with('user:id,email')->get()
                ->reject(fn ($s) => ServiceArea::covers($s->municipality_code, $s->province, $s->municipality))
                ->map(fn ($s) => [$s->id, $s->user->email ?? '-', "{$s->shop_name}: {$s->municipality}, {$s->province} ({$s->status})"]),
            'Couriers' => Courier::with('user:id,email')->get()
                ->reject(fn ($c) => ServiceArea::covers(null, $c->province, $c->municipality))
                ->map(fn ($c) => [$c->id, $c->user->email ?? '-', "{$c->municipality}, {$c->province} ({$c->status})"]),
            'Sorting centers' => User::where('role', 'sorting_center')->get()
                ->reject(fn ($u) => ServiceArea::coversLocality($u->assigned_municipality_code))
                ->map(fn ($u) => [$u->id, $u->email, ($u->assigned_municipality ?: 'not assigned') . ', ' . ($u->assigned_province ?: '-')]),
        ];

        foreach ($sets as $title => $rows) {
            $this->line('');
            $this->line("<comment>{$title}: {$rows->count()} outside</comment>");
            if ($rows->isNotEmpty()) {
                $this->table(['ID', 'Account', 'Location'], $rows->values()->all());
            }
        }

        return self::SUCCESS;
    }
}
