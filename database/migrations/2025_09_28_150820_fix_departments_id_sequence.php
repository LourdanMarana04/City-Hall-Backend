<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        // First, create a temporary department with ID 2 to fill the gap
        DB::table('departments')->insert([
            'id' => 2,
            'name' => 'Temporary Department (To Be Deleted)',
            'description' => 'This department will be deleted to fix ID sequence',
            'active' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Delete the temporary department to create the gap again
        DB::table('departments')->where('id', 2)->delete();

        // Reset the auto-increment to 11 (so next ID will be 11, which is correct for 10 departments)
        DB::statement('ALTER TABLE departments AUTO_INCREMENT = 11');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reset auto-increment back to 12 (original value)
        DB::statement('ALTER TABLE departments AUTO_INCREMENT = 12');
    }
};
