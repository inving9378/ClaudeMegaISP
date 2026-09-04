<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('talento_work_orders')) {
            return;
        }

        DB::statement("ALTER TABLE talento_work_orders MODIFY COLUMN status ENUM(
            'pending','in_progress','completed','validated','cancelled',
            'pending_activation','active','survey_pending','incidencia'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('talento_work_orders')) {
            return;
        }

        DB::statement("ALTER TABLE talento_work_orders MODIFY COLUMN status ENUM(
            'pending','in_progress','completed','validated','cancelled',
            'pending_activation','active','survey_pending'
        ) NOT NULL DEFAULT 'pending'");
    }
};
