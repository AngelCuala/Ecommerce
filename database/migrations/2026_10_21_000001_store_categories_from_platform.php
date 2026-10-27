<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store categories are now picked from the ALVY categories (a seller who sells motor parts and makeup adds
 * "Automotive & Motorcycle" and "Health & Beauty"), and a product is tagged with the store category of its
 * ALVY category. Existing products get their seller's store category for their category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_categories', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable()->after('parent_id')->constrained('categories')->nullOnDelete();
            $t->index(['seller_id', 'category_id']);
        });

        // Each seller's products: one store category per ALVY category they sell in, then the tag.
        $now = now();
        $rows = DB::table('products')->whereNull('deleted_at')->whereNotNull('seller_id')->whereNotNull('category_id')
            ->select('id', 'seller_id', 'category_id')->orderBy('id')->get();
        $names = DB::table('categories')->pluck('name', 'id');
        $made = [];
        foreach ($rows as $p) {
            $key = $p->seller_id . ':' . $p->category_id;
            if (! isset($made[$key])) {
                $existing = DB::table('store_categories')->where('seller_id', $p->seller_id)->where('category_id', $p->category_id)->value('id');
                $made[$key] = $existing ?: DB::table('store_categories')->insertGetId([
                    'seller_id'   => $p->seller_id,
                    'parent_id'   => null,
                    'category_id' => $p->category_id,
                    'name'        => $names[$p->category_id] ?? 'Category',
                    'sort_order'  => (int) DB::table('store_categories')->where('seller_id', $p->seller_id)->whereNull('parent_id')->max('sort_order') + 1,
                    'is_hidden'   => false,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            DB::table('product_store_category')->insertOrIgnore(['product_id' => $p->id, 'store_category_id' => $made[$key]]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('store_categories')->whereNotNull('category_id')->pluck('id');
        DB::table('product_store_category')->whereIn('store_category_id', $ids)->delete();
        DB::table('store_categories')->whereIn('id', $ids)->delete();
        Schema::table('store_categories', function (Blueprint $t) {
            $t->dropIndex(['seller_id', 'category_id']);
            $t->dropConstrainedForeignId('category_id');
        });
    }
};
