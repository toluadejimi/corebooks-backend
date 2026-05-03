<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->text('value');
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('token_balance')->default(0)->after('version');
        });

        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 64);
            $table->integer('amount');
            $table->unsignedBigInteger('balance_after');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });

        $now = now();
        DB::table('platform_settings')->insert([
            ['key' => 'token_proposal_ai_cost', 'value' => '10', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'token_app_search_cost', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('token_transactions');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('token_balance');
        });
        Schema::dropIfExists('platform_settings');
    }
};
