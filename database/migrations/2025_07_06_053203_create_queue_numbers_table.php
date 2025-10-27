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
        Schema::create('queue_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('transaction_id');
            $table->integer('queue_number');
            $table->string('full_queue_number');
            $table->string('status')->default('waiting'); // waiting, pending_confirmation, completed, etc.
            $table->string('source')->nullable(); // 'web' or 'kiosk'
            $table->string('confirmation_code', 6)->nullable(); // 6-digit alphanumeric code
            $table->timestamp('confirmed_at')->nullable(); // when confirmed at kiosk
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Persisted reporting details (nullable)
            $table->string('citizen_name')->nullable();
            $table->string('property_address')->nullable();
            $table->decimal('assessment_value', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->string('assigned_staff')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
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
        Schema::dropIfExists('queue_numbers');
    }
};
