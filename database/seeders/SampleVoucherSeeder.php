<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

/**
 * Sample vouchers for trying the checkout:
 *   php artisan db:seed --class=SampleVoucherSeeder
 *
 * Safe to run again: vouchers are matched by code and updated, never duplicated
 * (usage counts of vouchers that were already used are left as they are).
 */
class SampleVoucherSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->value('id');

        // ── ALVY vouchers (any seller, paid by ALVY) ──────────────────
        $platform = [
            ['code' => 'WELCOME100', 'name' => 'Welcome to ALVY — ₱100 off your first order',
             'discount_type' => 'fixed',   'discount_value' => 100, 'min_spend' => 500,  'per_user_limit' => 1],
            ['code' => 'ALVY10',     'name' => '10% off everything (up to ₱150)',
             'discount_type' => 'percent', 'discount_value' => 10,  'max_discount' => 150, 'min_spend' => 300, 'per_user_limit' => 3],
            ['code' => 'PAYDAY15',   'name' => 'Payday Sale — 15% off (up to ₱300)',
             'discount_type' => 'percent', 'discount_value' => 15,  'max_discount' => 300, 'min_spend' => 1000, 'per_user_limit' => 1,
             'usage_limit' => 200, 'ends_at' => now()->addDays(14)->endOfDay()],
            ['code' => 'FLASH50',    'name' => 'Flash Deal — ₱50 off, today only',
             'discount_type' => 'fixed',   'discount_value' => 50,  'min_spend' => 0,    'per_user_limit' => 1,
             'usage_limit' => 100, 'starts_at' => now()->startOfDay(), 'ends_at' => now()->endOfDay()],
            ['code' => 'BIGSPEND500', 'name' => '₱500 off orders of ₱5,000+',
             'discount_type' => 'fixed',   'discount_value' => 500, 'min_spend' => 5000, 'per_user_limit' => 1, 'usage_limit' => 50],
            // Scheduled: shows as "Scheduled" for admins; buyers see it once 11.11 starts.
            ['code' => 'SALE1111',   'name' => '11.11 Mega Sale — 11% off (up to ₱1,111)',
             'discount_type' => 'percent', 'discount_value' => 11,  'max_discount' => 1111, 'min_spend' => 0, 'per_user_limit' => 1,
             'starts_at' => '2026-11-11 00:00:00', 'ends_at' => '2026-11-11 23:59:59'],
            ['code' => 'SALE1212',   'name' => '12.12 Christmas Sale — ₱212 off',
             'discount_type' => 'fixed',   'discount_value' => 212, 'min_spend' => 1212, 'per_user_limit' => 1,
             'starts_at' => '2026-12-12 00:00:00', 'ends_at' => '2026-12-12 23:59:59'],
        ];
        foreach ($platform as $v) {
            $this->save($v + ['scope' => Voucher::SCOPE_PLATFORM, 'seller_id' => null, 'created_by' => $admin]);
        }

        // ── Shipping vouchers (ALVY pays part of the shipping) ──
        foreach ([
            ['code' => 'FREESHIP50', 'name' => 'Free shipping up to ₱50 (min ₱300)',
             'discount_type' => 'fixed', 'discount_value' => 50, 'min_spend' => 300, 'per_user_limit' => 5],
            ['code' => 'FREESHIP120', 'name' => 'Free shipping up to ₱120 (min ₱1,500)',
             'discount_type' => 'fixed', 'discount_value' => 120, 'min_spend' => 1500, 'per_user_limit' => 2],
        ] as $v) {
            $this->save($v + ['scope' => Voucher::SCOPE_SHIPPING, 'seller_id' => null, 'created_by' => $admin]);
        }

        // ── Store vouchers (only that seller's products, paid by the seller) ──
        $stores = [
            'seller@alvy.trade' => [
                ['code' => 'TESTSHOP150', 'name' => '₱150 off when you spend ₱2,000',
                 'discount_type' => 'fixed',   'discount_value' => 150, 'min_spend' => 2000, 'per_user_limit' => 1],
                ['code' => 'TESTSHOP50',  'name' => '₱50 off when you spend ₱500',
                 'discount_type' => 'fixed',   'discount_value' => 50,  'min_spend' => 500,  'per_user_limit' => 2],
                ['code' => 'TESTSHOP5',   'name' => '5% off anything in the store (up to ₱500)',
                 'discount_type' => 'percent', 'discount_value' => 5,   'max_discount' => 500, 'min_spend' => 0, 'per_user_limit' => 1],
            ],
            'tech@store.com' => [
                ['code' => 'TECH300', 'name' => '₱300 off gadgets ₱3,000+',
                 'discount_type' => 'fixed', 'discount_value' => 300, 'min_spend' => 3000, 'per_user_limit' => 1],
            ],
            'fashion@hub.com' => [
                ['code' => 'FASHION20', 'name' => '20% off fashion (up to ₱250)',
                 'discount_type' => 'percent', 'discount_value' => 20, 'max_discount' => 250, 'min_spend' => 800, 'per_user_limit' => 1],
            ],
            'home@essentials.com' => [
                ['code' => 'HOME100', 'name' => '₱100 off home items ₱1,500+',
                 'discount_type' => 'fixed', 'discount_value' => 100, 'min_spend' => 1500, 'per_user_limit' => 1],
            ],
        ];
        foreach ($stores as $email => $vouchers) {
            $seller = User::where('email', $email)->where('role', 'seller')->first();
            if (! $seller) {
                $this->command?->warn("Skipped store vouchers for {$email}: no such seller.");
                continue;
            }
            foreach ($vouchers as $v) {
                $this->save($v + ['scope' => Voucher::SCOPE_SELLER, 'seller_id' => $seller->id, 'created_by' => $seller->id]);
            }
        }

        $this->command?->info('Sample vouchers ready: ' . Voucher::count() . ' in total.');
    }

    private function save(array $data): void
    {
        $data += ['max_discount' => null, 'usage_limit' => null, 'starts_at' => null, 'ends_at' => null, 'is_active' => true];

        Voucher::updateOrCreate(['code' => $data['code']], $data);
    }
}
