<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('public_shop_enabled')->default(false)->after('settings');
            $table->string('public_shop_slug', 64)->nullable()->after('public_shop_enabled');
            $table->unique('public_shop_slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('available_online')->default(false)->after('image_url');
            $table->json('gallery_urls')->nullable()->after('available_online');
            $table->json('variations')->nullable()->after('gallery_urls');
            $table->index(['business_id', 'available_online']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'available_online']);
            $table->dropColumn(['available_online', 'gallery_urls', 'variations']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['public_shop_slug']);
            $table->dropColumn(['public_shop_enabled', 'public_shop_slug']);
        });
    }
};
