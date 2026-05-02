<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('name');
            $table->string('phone', 32)->nullable()->after('logo_url');
            $table->string('address_line1')->nullable()->after('phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 120)->nullable()->after('address_line2');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('country', 2)->default('NG')->after('state');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'phone',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'country',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
