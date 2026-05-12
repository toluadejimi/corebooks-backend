<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer credit facility:
 * - `customers` gains an enabled flag, a hard limit and a running balance the customer owes.
 * - `customer_credit_entries` is the immutable ledger of charges (credit sales) and payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'credit_enabled')) {
                $table->boolean('credit_enabled')->default(false)->after('is_walk_in');
            }
            if (! Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->default(0)->after('credit_enabled');
            }
            if (! Schema::hasColumn('customers', 'credit_balance')) {
                $table->decimal('credit_balance', 15, 2)->default(0)->after('credit_limit');
            }
        });

        Schema::create('customer_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();

            // 'charge' increases what the customer owes (sale on credit);
            // 'payment' is cash/transfer/pos received against outstanding balance.
            $table->string('type', 16);
            $table->string('method', 32)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference', 96)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['business_id', 'customer_id', 'occurred_at'], 'cce_biz_cust_occurred_idx');
            $table->index(['business_id', 'type'], 'cce_biz_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_entries');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['credit_enabled', 'credit_limit', 'credit_balance']);
        });
    }
};
