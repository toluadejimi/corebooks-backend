<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Asset GL account (cash on hand or a bank account) that funded this expense.
            // Nullable so historical rows keep working — controller falls back to
            // CODE_CASH (1010) when this is empty.
            $table->foreignId('gl_account_id')
                ->nullable()
                ->after('location_id')
                ->constrained('gl_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gl_account_id');
        });
    }
};
