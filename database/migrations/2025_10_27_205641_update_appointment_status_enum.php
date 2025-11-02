<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum column safely
        DB::statement("
            ALTER TABLE appointments 
            MODIFY COLUMN status ENUM(
                'pending',
                'pending_payment',
                'confirmed',
                'completed',
                'cancelled',
                'no_show'
            ) DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        // Rollback to original enum definition
        DB::statement("
            ALTER TABLE appointments 
            MODIFY COLUMN status ENUM(
                'pending',
                'confirmed',
                'completed',
                'cancelled',
                'no_show'
            ) DEFAULT 'pending'
        ");
    }
};
