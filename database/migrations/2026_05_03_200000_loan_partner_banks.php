<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_partner_banks', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->decimal('min_amount_ngn', 15, 2);
            $table->decimal('max_amount_ngn', 15, 2);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('business_loan_applications', function (Blueprint $table) {
            $table->foreignId('loan_partner_bank_id')->nullable()->after('business_id')->constrained('loan_partner_banks')->restrictOnDelete();
        });

        $now = now();
        DB::table('loan_partner_banks')->insert([
            [
                'slug' => 'access_bank',
                'name' => 'Access Bank',
                'min_amount_ngn' => 100000,
                'max_amount_ngn' => 5000000,
                'notes' => 'Example limits — edit in platform admin.',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'gtbank',
                'name' => 'Guaranty Trust Bank',
                'min_amount_ngn' => 500000,
                'max_amount_ngn' => 15000000,
                'notes' => null,
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'first_bank',
                'name' => 'First Bank of Nigeria',
                'min_amount_ngn' => 250000,
                'max_amount_ngn' => 8000000,
                'notes' => null,
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('business_loan_applications', function (Blueprint $table) {
            $table->dropForeign(['loan_partner_bank_id']);
            $table->dropColumn('loan_partner_bank_id');
        });

        Schema::dropIfExists('loan_partner_banks');
    }
};
