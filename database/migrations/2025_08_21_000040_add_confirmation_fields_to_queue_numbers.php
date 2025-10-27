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
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->string('confirmation_code', 6)->nullable()->after('source');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_code');
            
            // Add unique constraint for confirmation codes
            $table->unique('confirmation_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->dropUnique(['confirmation_code']);
            $table->dropColumn(['confirmation_code', 'confirmed_at']);
        });
    }
}; 