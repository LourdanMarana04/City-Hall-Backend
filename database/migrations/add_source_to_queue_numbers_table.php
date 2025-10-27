<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->string('source')->nullable()->after('priority');
        });
    }

    public function down()
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
