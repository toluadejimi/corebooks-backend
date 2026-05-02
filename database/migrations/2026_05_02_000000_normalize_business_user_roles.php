<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_user')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('business_user')->where('role', 'cashier')->update(['role' => 'sales']);
    }

    public function down(): void
    {
        DB::table('business_user')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('business_user')->where('role', 'sales')->update(['role' => 'cashier']);
        DB::table('business_user')->where('role', 'manager')->update(['role' => 'cashier']);
    }
};
