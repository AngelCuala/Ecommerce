<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Products created while the gallery table was missing only have their main
 * photo in products.image. Copy it into product_images so the edit page shows
 * it as the first (cover) photo and it counts toward the photo limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNotNull('image')->where('image', '!=', '')
            ->whereNotExists(fn ($q) => $q->from('product_images')->whereColumn('product_images.product_id', 'products.id'))
            ->get(['id', 'image']);

        foreach ($products as $p) {
            DB::table('product_images')->insert([
                'product_id' => $p->id,
                'path'       => $p->image,
                'label'      => 'Main',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the rows mirror products.image.
    }
};
