<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->unsignedInteger('duration_minutes')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'duration_minutes']);
        });
    }
};


