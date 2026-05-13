<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'email')) {
                $table->string('email', 191)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('suppliers', 'address')) {
                $table->string('address', 500)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('suppliers', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::hasColumn('suppliers', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
