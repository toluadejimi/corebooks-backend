<?php

use App\Models\Business;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            // The auto-seeded "Walk-in customer" row per business — protected from delete on the API.
            $table->boolean('is_walk_in')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'is_walk_in']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['business_id', 'customer_id']);
        });

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reason')->nullable();
            $table->decimal('refund_total', 15, 2)->default(0);
            $table->string('refund_method', 32)->default('cash');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();

            $table->index(['business_id', 'returned_at']);
        });

        Schema::create('sales_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('refund_amount', 15, 2);
            $table->boolean('restock')->default(true);
            $table->timestamps();
        });

        // Seed one walk-in customer per existing business so POS works immediately on upgrade.
        Business::query()->orderBy('id')->each(function (Business $business): void {
            $exists = \DB::table('customers')
                ->where('business_id', $business->id)
                ->where('is_walk_in', true)
                ->exists();
            if ($exists) {
                return;
            }
            \DB::table('customers')->insert([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'name' => 'Walk-in customer',
                'is_walk_in' => true,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['business_id', 'customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::dropIfExists('customers');
    }
};
