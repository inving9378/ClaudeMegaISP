<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN category VARCHAR(100) NOT NULL DEFAULT 'general'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN category ENUM('stock','brand','music_library','sfx','voiceover','ai_generated') NOT NULL");
    }
};
