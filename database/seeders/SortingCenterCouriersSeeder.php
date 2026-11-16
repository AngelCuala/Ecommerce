<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One 4-wheel rider (a truck) for every sorting center (logistics company) that has a hub. A rider with a 4-wheeler
 * carries parcels in bulk from hub to hub (Linehaul Trips); motorcycles and tricycles deliver to buyers.
 *
 *   php artisan db:seed --class=SortingCenterCouriersSeeder
 *
 * Login: courier<n>@alvy.test / Courier@2026 (a rider account), where <n> matches logistics<n>@alvy.test;
 * other companies get courier.<company id>@alvy.test. Safe to run again: accounts are matched by email,
 * and a company that already has another 4-wheel rider keeps it. To be assigned a trip, the rider goes Online.
 */
class SortingCenterCouriersSeeder extends Seeder
{
    public const PASSWORD = 'Courier@2026';

    /** [first name, last name, sex] — picked in turn. */
    private const NAMES = [
        ['Roberto', 'Villanueva', 'Male'], ['Danilo', 'Aquino', 'Male'], ['Ernesto', 'Bautista', 'Male'], ['Rogelio', 'Castillo', 'Male'],
        ['Arnel', 'Domingo', 'Male'], ['Jesus', 'Ramos', 'Male'], ['Noel', 'Pascual', 'Male'], ['Edwin', 'Salazar', 'Male'],
    ];

    public const VEHICLE = 'Truck';

    public function run(): void
    {
        $companies = User::where('role', 'sorting_center')->where('approval_status', 'approved')->orderBy('id')->get();
        $made = [];

        foreach ($companies as $i => $sc) {
            $hub = LogisticsHub::where('sorting_center_id', $sc->id)->orderBy('id')->first();
            if (! $hub) {
                $this->command?->warn("Skipped {$sc->email}: it has no hub yet (add one under Hubs first).");
                continue;
            }

            preg_match('/^logistics(\d+)@alvy\.test$/', $sc->email, $m);
            $email = isset($m[1]) ? "courier{$m[1]}@alvy.test" : "courier.{$sc->id}@alvy.test";
            $already = Rider::where('sorting_center_id', $sc->id)->whereIn('vehicle_type', Rider::HUB_TO_HUB_VEHICLES)
                ->whereHas('user', fn ($q) => $q->where('email', '!=', $email))->exists();
            if ($already) {
                continue;   // the company already has its own 4-wheel rider
            }

            [$first, $last, $sex] = self::NAMES[$i % count(self::NAMES)];
            $phone = '0917' . str_pad((string) (3000000 + $sc->id), 7, '0', STR_PAD_LEFT);
            $birthday = '1985-0' . (($i % 9) + 1) . '-15';

            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => "$first $last", 'first_name' => $first, 'last_name' => $last, 'sex' => $sex,
                'contact_no' => $phone, 'phone' => $phone, 'birthday' => $birthday, 'age' => \Carbon\Carbon::parse($birthday)->age,
                'province' => $sc->assigned_province ?? 'Laguna', 'municipality' => $sc->assigned_municipality, 'barangay' => $sc->barangay,
                'password' => Hash::make(self::PASSWORD), 'role' => 'courier',
                'approval_status' => 'approved', 'is_active' => true, 'email_verified_at' => now(),
            ])->save();

            // A rider like any other; their 4-wheeler makes them hub to hub (App\Services\TruckRiders adds the truck record).
            $rider = Rider::firstOrNew(['user_id' => $user->id]);
            $rider->forceFill([
                'sorting_center_id' => $sc->id, 'full_name' => "$first $last", 'phone' => $phone,
                'vehicle_type' => self::VEHICLE, 'plate_number' => sprintf('T%s%04d', chr(65 + ($sc->id % 26)) . chr(65 + (($sc->id * 7) % 26)), 1000 + $sc->id),
                'license_number' => sprintf('C%02d-26-%06d', $sc->id % 100, 500000 + $sc->id), 'license_expiry' => now()->addYears(3)->toDateString(),
                'area_id' => $rider->area_id ?? DeliveryArea::where('sorting_center_id', $sc->id)->orderBy('id')->value('id'),
                'application_status' => 'approved', 'approved_at' => $rider->approved_at ?? now(), 'approved_by' => $sc->id, 'is_active' => true,
            ])->save();

            $made[] = "$email ({$hub->code}, " . self::VEHICLE . ')';
        }

        $this->command?->info($made ? '4-wheel riders ready (password ' . self::PASSWORD . '): ' . implode('; ', $made) : 'Every sorting center already has a 4-wheel rider.');
    }
}
