<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('birth_date');
            $table->string('mobile_number')->nullable()->after('gender');
            $table->boolean('is_resident')->default(true)->after('mobile_number');
            $table->boolean('is_senior_citizen')->default(false)->after('is_resident');
            $table->string('security_question')->nullable()->after('is_senior_citizen');
            $table->string('security_answer')->nullable()->after('security_question');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'birth_date',
                'gender',
                'mobile_number',
                'is_resident',
                'is_senior_citizen',
                'security_question',
                'security_answer'
            ]);
        });
    }
};
