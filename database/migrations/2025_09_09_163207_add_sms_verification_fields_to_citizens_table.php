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
        Schema::table('citizens', function (Blueprint $table) {
            // Add phone_number field (different from mobile_number for SMS verification)
            $table->string('phone_number')->nullable()->after('mobile_number');

            // Add birthday field (different from birth_date for consistency)
            $table->date('birthday')->nullable()->after('birth_date');

            // Add address field
            $table->text('address')->nullable()->after('is_senior_citizen');

            // Add verification status
            $table->boolean('is_verified')->default(false)->after('address');

            // Add unique constraint for phone_number
            $table->unique('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['phone_number', 'birthday', 'address', 'is_verified']);
        });
    }
};
