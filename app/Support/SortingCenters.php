<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Sorting centers (hubs) a courier can apply to. */
class SortingCenters
{
    /**
     * Approved sorting centers with a display name and hub address. The name and address
     * come from the approved application when there is one, else from the account itself.
     *
     * @return Collection<int, array{id: int, name: string, hub_address: ?string}>
     */
    public static function forCouriers(): Collection
    {
        return User::query()
            ->where('users.role', 'sorting_center')
            ->where('users.approval_status', 'approved')
            ->leftJoin('sorting_center_applications as sca', function ($join) {
                $join->on('sca.user_id', '=', 'users.id')->where('sca.status', 'approved');
            })
            ->orderBy('users.name')
            ->get([
                'users.id', 'users.name', 'users.street', 'users.barangay', 'users.municipality', 'users.province',
                'sca.business_name as hub_name', 'sca.street as hub_street', 'sca.barangay as hub_barangay',
                'sca.municipality as hub_municipality', 'sca.province as hub_province',
            ])
            ->unique('id')
            ->map(function ($p) {
                $address = $p->hub_municipality
                    ? [$p->hub_street, $p->hub_barangay, $p->hub_municipality, $p->hub_province]
                    : [$p->street, $p->barangay, $p->municipality, $p->province];

                return [
                    'id'          => $p->id,
                    'name'        => $p->hub_name ?: $p->name,
                    'hub_address' => implode(', ', array_filter($address)) ?: null,
                ];
            })
            ->values();
    }

    /** Validation: the chosen id must be an approved sorting center. */
    public static function existsRule(): Exists
    {
        return Rule::exists('users', 'id')
            ->where('role', 'sorting_center')
            ->where('approval_status', 'approved');
    }
}
