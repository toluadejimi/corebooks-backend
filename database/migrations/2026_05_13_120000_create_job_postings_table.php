<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job postings for the mobile "Jobs" feed.
 *
 * Two sources can author a row:
 *   - admin: a platform admin posts a vacancy directly (status defaults to `approved`).
 *   - business: a tenant business submits a vacancy request (status defaults to `pending`,
 *     becomes `approved` after admin review, or `rejected` with a reason).
 *
 * The mobile feed only surfaces `approved` rows. Submitters can always see their own
 * submissions regardless of status, so they can track review progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Where the row came from. Admin-posted rows have null business_id.
            $table->string('source', 16)->default('admin')->index();
            $table->foreignId('submitted_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Content
            $table->string('title', 191);
            $table->string('company_name', 191);
            $table->text('description');

            // Location — `location_state` is the Nigerian state used for the mobile filter.
            $table->string('location_state', 64)->index();
            $table->string('location_city', 120)->nullable();

            // Employment & comp
            $table->string('employment_type', 24)->default('full_time'); // full_time | part_time | contract | internship | temporary
            $table->decimal('salary_min', 15, 2)->nullable();
            $table->decimal('salary_max', 15, 2)->nullable();
            $table->string('salary_period', 16)->nullable(); // hourly | daily | weekly | monthly | annually
            $table->string('currency', 8)->default('NGN');

            // How to apply
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('apply_url', 500)->nullable();

            // Workflow
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | closed
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('expires_at')->nullable()->index();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'location_state']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
