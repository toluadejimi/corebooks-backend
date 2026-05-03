<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_services', function (Blueprint $table): void {
            $table->string('icon_url', 2048)->nullable()->after('description');
        });

        // Published list fees (NGN) so the app shows amounts instead of “on request”.
        DB::table('extra_services')->where('slug', 'cac_certificate')->update([
            'fee_amount_ngn' => 45000,
            'updated_at' => now(),
        ]);
        DB::table('extra_services')->where('slug', 'scuml')->update([
            'fee_amount_ngn' => 75000,
            'updated_at' => now(),
        ]);
        DB::table('extra_services')->where('slug', 'tax_id')->update([
            'fee_amount_ngn' => 15000,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('extra_services', function (Blueprint $table): void {
            $table->dropColumn('icon_url');
        });
    }
};
