<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 48);
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 64)->nullable();
            $table->text('client_address')->nullable();
            $table->string('status', 24)->default('draft');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 8)->default('NGN');
            $table->decimal('subtotal_ex_vat', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 512);
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('vat_percent', 6, 2)->nullable();
            $table->decimal('line_subtotal_ex_vat', 15, 2)->default(0);
            $table->decimal('line_vat', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['quotation_id', 'sort_order']);
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->longText('body_html');
            $table->text('ai_prompt')->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('proposals');
    }
};
