<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The main colors seen in each product's picture (read once by the AI, see `products:tag-colors`), so a
 * search for "red" or "pula" finds red items even when no seller wrote the color down.
 *  - colors:       e.g. ["red","white"]; null = not checked yet
 *  - colors_image: the picture the colors were read from; a new picture is checked again
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->json('colors')->nullable()->after('image');
            $t->string('colors_image')->nullable()->after('colors');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['colors', 'colors_image']));
    }
};
