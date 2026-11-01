<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Str;

/** Saved addresses and the checkout form, for tests that place orders. */
trait ChecksOut
{
    /** PSGC codes: Quezon City (NCR), Cebu City (Visayas), Itbayat (Batanes). */
    protected const QUEZON_CITY = '137404000';
    protected const CEBU_CITY   = '072217000';
    protected const ITBAYAT     = '020902000';

    protected function addressFor(User $buyer, string $municipalityCode = self::QUEZON_CITY, array $extra = []): UserAddress
    {
        $places = [
            self::QUEZON_CITY => ['NCR', 'Metro Manila', 'Quezon City', null],
            self::CEBU_CITY   => ['Region VII', 'Cebu', 'Cebu City', '072200000'],
            self::ITBAYAT     => ['Region II', 'Batanes', 'Itbayat', '020900000'],
        ];
        [$region, $province, $city, $provinceCode] = $places[$municipalityCode] ?? ['NCR', 'Metro Manila', 'Quezon City', null];

        return UserAddress::create($extra + [
            'user_id' => $buyer->id, 'label' => 'Home', 'full_name' => $buyer->name, 'phone' => '09171234567',
            'address_line' => '45 Kalayaan Ave.', 'barangay' => 'Central', 'city' => $city, 'province' => $province,
            'region' => $region, 'zip' => '1100', 'country' => 'Philippines',
            'municipality_code' => $municipalityCode, 'province_code' => $provinceCode,
            'is_default' => ! $buyer->addresses()->exists(),
        ]);
    }

    /** The fields the checkout page submits (a fresh one-time token every time). */
    protected function checkoutForm(UserAddress $address, array $extra = []): array
    {
        return $extra + [
            'address_id'     => $address->id,
            'payment_method' => 'cod',
            'checkout_token' => (string) Str::uuid(),
        ];
    }
}
