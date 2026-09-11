<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CatalogCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Pet Supplies',
            'Kids & Baby',
            'Electronics & Gadgets',
            "Women's Apparel",
            'Sports & Outdoors',
            'Home & Garden',
            "Men's Apparel",
            'Health & Beauty',
            'Books & Media',
            'Food & Gourmet',
            'Furniture & Office',
            'Jewelry & Watches',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        $this->command->info('Catalog categories seeded: ' . count($categories));
    }
}
