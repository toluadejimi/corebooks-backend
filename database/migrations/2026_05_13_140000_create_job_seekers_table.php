<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job seekers — people looking for work, created and curated by platform admins.
 *
 * Businesses (the mobile app users) browse approved seekers, view their photo
 * and CV, contact them directly, and optionally shortlist them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_seekers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identity / contact
            $table->string('full_name', 191);
            $table->string('headline', 191)->nullable(); // e.g. "Frontend developer", "Sales executive"
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();

            // Media (stored on the public disk like product images)
            $table->string('photo_url', 500)->nullable();
            $table->string('cv_url', 500)->nullable();
            $table->string('cv_filename', 191)->nullable();

            // Where they are / want to work
            $table->string('location_state', 64)->index();
            $table->string('location_city', 120)->nullable();
            $table->boolean('open_to_relocate')->default(false);

            // Career profile
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->string('employment_type', 24)->default('full_time'); // full_time | part_time | contract | internship | temporary
            $table->decimal('expected_salary_min', 15, 2)->nullable();
            $table->decimal('expected_salary_max', 15, 2)->nullable();
            $table->string('salary_period', 16)->nullable();   // monthly | annually | hourly ...
            $table->string('currency', 8)->default('NGN');

            // Free-form fields
            $table->text('about')->nullable();
            $table->text('skills')->nullable();        // comma-separated or short text
            $table->text('education')->nullable();
            $table->string('linkedin_url', 500)->nullable();

            // Moderation
            $table->string('status', 16)->default('active'); // active | hidden | archived
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'location_state']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_seekers');
    }
};
