<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_services', function (Blueprint $table): void {
            $table->json('application_form')->nullable()->after('icon_url');
        });

        Schema::table('extra_service_applications', function (Blueprint $table): void {
            $table->json('applicant_payload')->nullable()->after('applicant_notes');
        });
    }

    public function down(): void
    {
        Schema::table('extra_service_applications', function (Blueprint $table): void {
            $table->dropColumn('applicant_payload');
        });

        Schema::table('extra_services', function (Blueprint $table): void {
            $table->dropColumn('application_form');
        });
    }
};
