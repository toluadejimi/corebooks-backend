<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_services', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('fee_amount_ngn', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('extra_service_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_service_id')->constrained('extra_services')->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->text('applicant_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        DB::table('extra_services')->insert([
            [
                'slug' => 'cac_certificate',
                'title' => 'Apply for CAC certificate',
                'description' => 'Corporate Affairs Commission registration and certificate support.',
                'fee_amount_ngn' => 0,
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'scuml',
                'title' => 'Apply for SCUML registration',
                'description' => 'Special Control Unit Against Money Laundering (SCUML) registration assistance.',
                'fee_amount_ngn' => 0,
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'tax_id',
                'title' => 'Apply for Tax ID (TIN)',
                'description' => 'Federal Inland Revenue Service Tax Identification Number support.',
                'fee_amount_ngn' => 0,
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_service_applications');
        Schema::dropIfExists('extra_services');
    }
};
