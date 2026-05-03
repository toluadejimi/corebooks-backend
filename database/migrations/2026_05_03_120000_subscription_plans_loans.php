<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_amount_kobo');
            $table->string('billing_interval', 16)->default('monthly');
            $table->unsignedInteger('max_records')->default(5000);
            $table->json('features')->nullable();
            $table->boolean('feature_inventory')->default(true);
            $table->boolean('feature_accounting_reports')->default(true);
            $table->boolean('feature_tax_reports')->default(true);
            $table->boolean('feature_database_backup')->default(true);
            $table->boolean('feature_business_loan')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paystack_reference', 128)->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('amount_kobo');
            $table->string('authorization_url', 2048)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_loan_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('tax_id', 128)->nullable();
            $table->string('cac_registration_number', 128)->nullable();
            $table->string('cac_certificate_url', 2048)->nullable();
            $table->json('additional_documents')->nullable();
            $table->decimal('loan_amount_requested', 15, 2)->nullable();
            $table->text('purpose')->nullable();
            $table->text('business_summary')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('version')->constrained('subscription_plans')->nullOnDelete();
            $table->string('subscription_status', 32)->default('active')->after('subscription_plan_id');
            $table->timestamp('subscription_trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_current_period_end')->nullable()->after('subscription_trial_ends_at');
        });

        $now = now();

        DB::table('subscription_plans')->insert([
            [
                'slug' => 'simple',
                'name' => 'Simple Plan',
                'description' => 'Essential tools for small retail.',
                'price_amount_kobo' => 1000 * 100,
                'billing_interval' => 'monthly',
                'max_records' => 5000,
                'features' => json_encode([
                    'Item inventory',
                    'Accounting reports',
                    'Access to tax reports',
                    'Database backup',
                ]),
                'feature_inventory' => true,
                'feature_accounting_reports' => true,
                'feature_tax_reports' => true,
                'feature_database_backup' => true,
                'feature_business_loan' => false,
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro Plan',
                'description' => 'Growing businesses with loan access.',
                'price_amount_kobo' => 5000 * 100,
                'billing_interval' => 'monthly',
                'max_records' => 5000,
                'features' => json_encode([
                    'Item inventory',
                    'Accounting reports',
                    'Access to tax reports',
                    'Access to business loan',
                    'Database backup',
                ]),
                'feature_inventory' => true,
                'feature_accounting_reports' => true,
                'feature_tax_reports' => true,
                'feature_database_backup' => true,
                'feature_business_loan' => true,
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'pro_plus',
                'name' => 'Pro Plus Plan',
                'description' => 'High volume + priority features.',
                'price_amount_kobo' => 10000 * 100,
                'billing_interval' => 'monthly',
                'max_records' => 50000,
                'features' => json_encode([
                    'Item inventory',
                    'Accounting reports',
                    'Access to tax reports',
                    'Access to business loan',
                    'Database backup',
                    'Higher record limits',
                    'Priority support',
                ]),
                'feature_inventory' => true,
                'feature_accounting_reports' => true,
                'feature_tax_reports' => true,
                'feature_database_backup' => true,
                'feature_business_loan' => true,
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $simpleId = (int) DB::table('subscription_plans')->where('slug', 'simple')->value('id');

        DB::table('businesses')->update([
            'subscription_plan_id' => $simpleId,
            'subscription_status' => 'active',
            'subscription_trial_ends_at' => null,
            'subscription_current_period_end' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn([
                'subscription_plan_id',
                'subscription_status',
                'subscription_trial_ends_at',
                'subscription_current_period_end',
            ]);
        });

        Schema::dropIfExists('business_loan_applications');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_plans');
    }
};
