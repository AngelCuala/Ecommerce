<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer modules (B1–B14 test set):
 *  - verified email / phone changes, PSGC codes on saved addresses
 *  - product variants in the cart and on orders, per-buyer purchase limits, sale end dates,
 *    soft-deleted products (order history keeps them)
 *  - one order per store at checkout (checkout_group), per-store shipping, shipping vouchers,
 *    note to seller, order timeline, completion / return windows
 *  - wishlist, followed stores, claimed vouchers, reviews, return & refund requests
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('pending_email')->nullable()->after('email');
            $t->string('pending_phone', 20)->nullable()->after('phone');
            $t->string('contact_otp')->nullable();                 // hashed code for an email / phone change
            $t->timestamp('contact_otp_expires_at')->nullable();
            $t->unsignedTinyInteger('contact_otp_attempts')->default(0);
        });

        Schema::table('user_addresses', function (Blueprint $t) {
            $t->string('region', 120)->nullable()->after('barangay');
        });
        // PSGC codes (the shared database already has them from the app API's migrations).
        foreach (['user_addresses' => ['province_code', 'municipality_code', 'barangay_code'], 'orders' => ['barangay', 'barangay_code']] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->string($column, $column === 'barangay' ? 120 : 20)->nullable());
                }
            }
        }

        Schema::table('products', function (Blueprint $t) {
            $t->string('variation_labels', 60)->nullable()->after('specs');   // e.g. "Color, Size"
            $t->unsignedInteger('purchase_limit')->nullable()->after('stock'); // per buyer, all orders
            $t->timestamp('sale_ends_at')->nullable()->after('discount_percent');
            $t->softDeletes();
        });

        // Same product in another variant is a separate cart line.
        Schema::table('cart_items', function (Blueprint $t) {
            $t->foreignId('variation_id')->nullable()->after('product_id')->constrained('product_variations')->nullOnDelete();
            $t->index('user_id');
        });
        // The old one-line-per-product unique index (its name differs between databases).
        foreach (Schema::getIndexes('cart_items') as $index) {
            if ($index['unique'] && ! $index['primary'] && $index['columns'] === ['user_id', 'product_id']) {
                Schema::table('cart_items', fn (Blueprint $t) => $t->dropUnique($index['name']));
            }
        }

        Schema::table('order_items', function (Blueprint $t) {
            $t->foreignId('variation_id')->nullable()->after('product_id')->constrained('product_variations')->nullOnDelete();
            $t->string('variation_name', 120)->nullable()->after('variation_id');
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->uuid('checkout_group')->nullable()->after('user_id')->index();
            $t->foreignId('seller_id')->nullable()->after('checkout_group')->constrained('users')->nullOnDelete();
            $t->foreignId('shipping_voucher_id')->nullable()->after('seller_discount')->constrained('vouchers')->nullOnDelete();
            $t->decimal('shipping_discount', 10, 2)->default(0)->after('shipping_voucher_id');
            $t->decimal('shipping_weight_kg', 8, 2)->nullable()->after('shipping_fee');
            $t->string('buyer_note', 200)->nullable()->after('shipping_address');
            $t->string('region', 120)->nullable()->after('province');
            $t->foreignId('address_id')->nullable()->after('user_id')->constrained('user_addresses')->nullOnDelete();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
        });

        Schema::create('order_status_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('status', 40);
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['order_id', 'created_at']);
        });

        Schema::create('wishlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'product_id']);
        });

        Schema::create('store_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'seller_id']);
        });

        Schema::table('vouchers', function (Blueprint $t) {
            // false = code-only: not listed anywhere, works only when the buyer types the code.
            $t->boolean('is_claimable')->default(true)->after('is_active');
        });

        Schema::create('voucher_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'voucher_id']);
        });

        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('order_id')->constrained()->cascadeOnDelete();
                $t->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();   // one review per item
                $t->unsignedTinyInteger('rating');
                $t->text('comment')->nullable();
                $t->string('photo_path')->nullable();
                $t->string('variation_name', 120)->nullable();
                $t->unsignedTinyInteger('edit_count')->default(0);
                $t->text('seller_reply')->nullable();
                $t->timestamp('seller_replied_at')->nullable();
                $t->timestamps();
                $t->index(['product_id', 'created_at']);
            });
        }

        Schema::create('return_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason', 60);
            $t->text('details')->nullable();
            $t->string('evidence_path');
            $t->string('status', 20)->default('requested');   // requested | approved | rejected
            $t->text('seller_note')->nullable();
            $t->decimal('refund_amount', 10, 2)->default(0);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        Schema::table('messages', function (Blueprint $t) {
            $t->string('attachment_path')->nullable()->after('body');
            $t->foreignId('product_id')->nullable()->after('attachment_path')->constrained()->nullOnDelete();
        });

        // Existing orders: give them a seller (when all their items are one seller's) and a timeline start.
        if (DB::getDriverName() === 'sqlite') {
            return;   // test databases start empty
        }
        DB::statement('UPDATE orders o SET seller_id = (
            SELECT MIN(p.seller_id) FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = o.id
            HAVING COUNT(DISTINCT p.seller_id) = 1)');
        DB::statement("INSERT INTO order_status_events (order_id, status, note, created_at)
            SELECT id, 'Pending', 'Order placed', created_at FROM orders");
        DB::statement("UPDATE orders SET delivered_at = updated_at WHERE status = 'Delivered' AND delivered_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('product_id');
            $t->dropColumn('attachment_path');
        });
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('voucher_claims');
        Schema::table('vouchers', fn (Blueprint $t) => $t->dropColumn('is_claimable'));
        Schema::dropIfExists('store_follows');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('order_status_events');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('address_id');
            $t->dropConstrainedForeignId('shipping_voucher_id');
            $t->dropConstrainedForeignId('seller_id');
            $t->dropColumn(['checkout_group', 'shipping_discount', 'shipping_weight_kg', 'buyer_note', 'region',
                            'delivered_at', 'completed_at', 'cancelled_at']);
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('variation_id');
            $t->dropColumn('variation_name');
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->unique(['user_id', 'product_id'], 'cart_items_user_id_book_id_unique');
            $t->dropConstrainedForeignId('variation_id');
        });
        Schema::table('products', function (Blueprint $t) {
            $t->dropSoftDeletes();
            $t->dropColumn(['variation_labels', 'purchase_limit', 'sale_ends_at']);
        });
        Schema::table('user_addresses', fn (Blueprint $t) => $t->dropColumn('region'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'pending_email', 'pending_phone', 'contact_otp', 'contact_otp_expires_at', 'contact_otp_attempts',
        ]));
    }
};
