<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets platform admins publish per-service requirements (documents, info needed,
 * eligibility) that the mobile app surfaces in a bottom sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('extra_services', 'requirements')) {
                $table->text('requirements')->nullable()->after('description');
            }
        });

        DB::table('extra_services')->where('slug', 'cac_certificate')->whereNull('requirements')->update([
            'requirements' => "Documents and info needed:\n- Two preferred company names (proposed)\n- Nature of business (one-line summary)\n- Registered address with city + state\n- Director(s) full name, email, phone, valid ID (NIN or international passport)\n- Director's date of birth and residential address\n- Signature specimen (clear photo)\n- Email address for the business",
            'updated_at' => now(),
        ]);

        DB::table('extra_services')->where('slug', 'scuml')->whereNull('requirements')->update([
            'requirements' => "Documents and info needed:\n- CAC certificate of incorporation (PDF / clear photo)\n- Memorandum & Articles of Association (if available)\n- Tax Identification Number (TIN), if already issued\n- Director(s) valid government ID + utility bill (not older than 3 months)\n- Letter of board resolution authorising registration (we provide a template)\n- Business contact details (phone, email, registered address)",
            'updated_at' => now(),
        ]);

        DB::table('extra_services')->where('slug', 'tax_id')->whereNull('requirements')->update([
            'requirements' => "Documents and info needed:\n- CAC certificate of incorporation\n- Memorandum & Articles of Association (where applicable)\n- Stamped letter on company letterhead requesting TIN\n- Director's valid ID (NIN slip / international passport)\n- Utility bill of business address (not older than 3 months)\n- Business email and phone for FIRS correspondence",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('extra_services', function (Blueprint $table): void {
            $table->dropColumn('requirements');
        });
    }
};
