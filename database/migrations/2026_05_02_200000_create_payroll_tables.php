<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->date('period_on');
            $table->string('status', 32)->default('draft');
            $table->timestamps();

            $table->unique(['business_id', 'period_on']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('housing_allowance', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('other_allowances', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('pension_employee', 15, 2)->default(0);
            $table->decimal('pension_employer', 15, 2)->default(0);
            $table->decimal('nhf', 15, 2)->default(0);
            $table->decimal('paye', 15, 2)->default(0);
            $table->decimal('cra_annual', 15, 2)->nullable();
            $table->decimal('chargeable_income_annual', 15, 2)->nullable();
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
