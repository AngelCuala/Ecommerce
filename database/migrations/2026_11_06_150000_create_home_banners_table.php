<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The home page's big banner: slides the admin adds for events and sales (website and app). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->string('image');                         // public disk: banners/…
            $table->string('eyebrow', 40)->nullable();       // small label above the headline, e.g. "11.11 Sale"
            $table->string('title', 80);                     // headline
            $table->string('highlight', 40)->nullable();     // last words, underlined in orange
            $table->string('subtitle', 160)->nullable();
            $table->string('button_label', 30)->default('Shop now');
            $table->string('category_slug')->nullable();     // where "Shop now" goes (null = all categories)
            $table->string('focus', 20)->default('center');  // which part of the photo stays in view: top, center, bottom
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
