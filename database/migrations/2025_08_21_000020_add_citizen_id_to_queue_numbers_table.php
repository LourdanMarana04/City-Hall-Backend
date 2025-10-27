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
            if (!Schema::hasColumn('queue_numbers', 'citizen_id')) {
                $table->unsignedBigInteger('citizen_id')->nullable()->after('user_id');
                $table->foreign('citizen_id')->references('id')->on('citizens')->onDelete('set null');
            }
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
            if (Schema::hasColumn('queue_numbers', 'citizen_id')) {
                $table->dropForeign(['citizen_id']);
                $table->dropColumn('citizen_id');
            }
        });
    }
};


