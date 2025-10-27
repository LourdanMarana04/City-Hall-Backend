<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('queue_numbers', 'citizen_name')) {
                $table->string('citizen_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('queue_numbers', 'property_address')) {
                $table->string('property_address')->nullable()->after('citizen_name');
            }
            if (!Schema::hasColumn('queue_numbers', 'assessment_value')) {
                $table->decimal('assessment_value', 15, 2)->nullable()->after('property_address');
            }
            if (!Schema::hasColumn('queue_numbers', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->nullable()->after('assessment_value');
            }
            if (!Schema::hasColumn('queue_numbers', 'assigned_staff')) {
                $table->string('assigned_staff')->nullable()->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('queue_numbers', 'assigned_staff')) {
                $table->dropColumn('assigned_staff');
            }
            if (Schema::hasColumn('queue_numbers', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('queue_numbers', 'assessment_value')) {
                $table->dropColumn('assessment_value');
            }
            if (Schema::hasColumn('queue_numbers', 'property_address')) {
                $table->dropColumn('property_address');
            }
            if (Schema::hasColumn('queue_numbers', 'citizen_name')) {
                $table->dropColumn('citizen_name');
            }
        });
    }
};


