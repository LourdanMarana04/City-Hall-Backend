<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix the superadmin@cityhall.com account to have the correct role
        DB::table('admins')
            ->where('email', 'superadmin@cityhall.com')
            ->update(['role' => 'super_admin']);
        
        // Also fix any other potential superadmin accounts that might have been created with wrong role
        // If there are any admins that should be super_admin but aren't, this will catch them
        // (You can add more email patterns here if needed)
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert the superadmin role back to admin (if needed)
        DB::table('admins')
            ->where('email', 'superadmin@cityhall.com')
            ->update(['role' => 'admin']);
    }
};