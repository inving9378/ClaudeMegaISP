<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_generated_content
            MODIFY COLUMN `type` ENUM('copy','image','video','audio_voiceover','ai_response') NOT NULL");

        DB::statement("ALTER TABLE marketing_generated_content
            MODIFY COLUMN `status` ENUM('pending','generating','ready','failed','used','completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_generated_content
            MODIFY COLUMN `type` ENUM('copy','image','video','audio_voiceover') NOT NULL");

        DB::statement("ALTER TABLE marketing_generated_content
            MODIFY COLUMN `status` ENUM('pending','generating','ready','failed','used') NOT NULL DEFAULT 'pending'");
    }
};
