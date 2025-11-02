<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id('appointment_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('schedule_id'); 
            $table->date('appointment_date');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->timestamps();

            $table->foreign('patient_id')->references('user_id')->on('users');
            $table->foreign('service_id')->references('service_id')->on('services');
            $table->foreign('schedule_id')->references('schedule_id')->on('schedules');
            $table->unique(['schedule_id', 'appointment_date']); 
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
