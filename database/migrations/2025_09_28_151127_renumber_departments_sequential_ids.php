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
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Renumber departments to sequential IDs 1-10
        $departments = DB::table('departments')->orderBy('id')->get();
        
        // Create a mapping of old IDs to new sequential IDs
        $idMapping = [];
        $newId = 1;
        foreach ($departments as $dept) {
            $idMapping[$dept->id] = $newId;
            $newId++;
        }
        
        // Update each department's ID to be sequential
        foreach ($idMapping as $oldId => $newId) {
            if ($oldId != $newId) {
                // First, update any foreign key references
                DB::table('transactions')->where('department_id', $oldId)->update(['department_id' => $newId]);
                DB::table('queue_numbers')->where('department_id', $oldId)->update(['department_id' => $newId]);
                DB::table('admins')->where('department_id', $oldId)->update(['department_id' => $newId]);
                
                // Then update the department ID
                DB::table('departments')->where('id', $oldId)->update(['id' => $newId]);
            }
        }
        
        // Reset auto-increment to 11 (next ID will be 11, which is correct for 10 departments)
        DB::statement('ALTER TABLE departments AUTO_INCREMENT = 11');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration cannot be easily reversed as it changes primary keys
        // A rollback would require restoring from backup
        throw new Exception('This migration cannot be rolled back. Please restore from backup if needed.');
    }
};
