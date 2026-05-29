<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN license ENUM('cc0','cc_by','owned','licensed','ai_generated','pexels_free') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN license ENUM('cc0','cc_by','owned','licensed','ai_generated') NOT NULL");
    }
};
