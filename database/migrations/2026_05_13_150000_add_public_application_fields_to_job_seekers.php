<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets members of the public submit their own job-seeker profile via a public
 * form on the website. New columns:
 *  - submitted_via:   'admin' (created in the admin panel) | 'public' (self-submitted)
 *  - tracking_token:  random opaque token used to look up application status
 *  - applied_at:      when the public form was submitted (separate from created_at for cleanliness)
 *  - rejection_reason: optional admin note shown to the applicant on the status page
 *  - applicant_ip / applicant_user_agent: light fingerprint for abuse triage
 *
 * The status column already exists; we now use additional values ('pending',
 * 'declined') alongside existing 'active' | 'hidden' | 'archived'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_seekers', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_seekers', 'submitted_via')) {
                $table->string('submitted_via', 16)->default('admin')->after('status');
            }
            if (! Schema::hasColumn('job_seekers', 'tracking_token')) {
                $table->string('tracking_token', 64)->nullable()->unique()->after('submitted_via');
            }
            if (! Schema::hasColumn('job_seekers', 'applied_at')) {
                $table->timestamp('applied_at')->nullable()->after('tracking_token');
            }
            if (! Schema::hasColumn('job_seekers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('applied_at');
            }
            if (! Schema::hasColumn('job_seekers', 'applicant_ip')) {
                $table->string('applicant_ip', 45)->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('job_seekers', 'applicant_user_agent')) {
                $table->string('applicant_user_agent', 500)->nullable()->after('applicant_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_seekers', function (Blueprint $table): void {
            foreach (
                [
                    'applicant_user_agent',
                    'applicant_ip',
                    'rejection_reason',
                    'applied_at',
                    'tracking_token',
                    'submitted_via',
                ] as $col
            ) {
                if (Schema::hasColumn('job_seekers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
