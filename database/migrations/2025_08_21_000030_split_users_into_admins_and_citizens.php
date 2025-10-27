<?php

use Illuminate\Database\Migrations\Migration;
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
        // Copy admins (role admin/super_admin) into admins table, preserving IDs
        $admins = DB::table('users')->whereIn('role', ['admin', 'super_admin'])->get();
        foreach ($admins as $admin) {
            // Resolve department_id by name match if possible
            $departmentId = null;
            if (!empty($admin->department)) {
                $department = DB::table('departments')->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($admin->department))])->first();
                if ($department) {
                    $departmentId = $department->id;
                }
            }

            DB::table('admins')->updateOrInsert(
                ['id' => $admin->id],
                [
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'password' => $admin->password,
                    'role' => in_array($admin->role, ['admin', 'super_admin']) ? $admin->role : 'admin',
                    'department_id' => $departmentId,
                    'position' => $admin->position,
                    'is_active' => (bool)($admin->is_active ?? true),
                    'last_login_at' => $admin->last_login_at,
                    'remember_token' => $admin->remember_token,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ]
            );
        }

        // Copy regular users into citizens table, preserving IDs
        $citizens = DB::table('users')->where('role', 'user')->get();
        foreach ($citizens as $citizen) {
            DB::table('citizens')->updateOrInsert(
                ['id' => $citizen->id],
                [
                    'name' => $citizen->name,
                    'first_name' => $citizen->first_name,
                    'last_name' => $citizen->last_name,
                    'email' => $citizen->email,
                    'password' => $citizen->password,
                    'birth_date' => $citizen->birth_date,
                    'gender' => $citizen->gender,
                    'mobile_number' => $citizen->mobile_number,
                    'is_resident' => (bool)($citizen->is_resident ?? true),
                    'is_senior_citizen' => (bool)($citizen->is_senior_citizen ?? false),
                    'security_question' => $citizen->security_question,
                    'security_answer' => $citizen->security_answer,
                    'remember_token' => $citizen->remember_token,
                    'created_at' => $citizen->created_at,
                    'updated_at' => $citizen->updated_at,
                ]
            );
        }

        // Backfill queue_numbers.citizen_id where possible
        // Set citizen_id equal to user_id if that user is a citizen we just created
        DB::statement('UPDATE queue_numbers q INNER JOIN citizens c ON q.user_id = c.id SET q.citizen_id = c.id WHERE q.user_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Best-effort rollback: clear copied data (do not drop tables here)
        DB::table('admins')->truncate();
        DB::table('citizens')->truncate();
        // Clear citizen_id backfill
        DB::table('queue_numbers')->update(['citizen_id' => null]);
    }
};


