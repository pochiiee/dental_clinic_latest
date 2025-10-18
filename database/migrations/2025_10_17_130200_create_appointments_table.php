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
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->dateTime('schedule_datetime');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('patient_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('service_id')->on('services')->onDelete('cascade');
            $table->foreign('schedule_id')->references('schedule_id')->on('schedules')->onDelete('set null');
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
