<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;  

return new class extends Migration
{
 public function up(): void
    {
        // Step 1: Convert old statuses
        DB::table('appointments')
            ->where('status', 'pending_payment')
            ->update(['status' => 'pending']);

        // Step 2: Change the ENUM definition
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'ongoing', 'completed'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        // Optional rollback
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'pending_payment', 'confirmed', 'cancelled', 'ongoing', 'completed'])
                  ->default('pending')
                  ->change();
        });
    }
};
