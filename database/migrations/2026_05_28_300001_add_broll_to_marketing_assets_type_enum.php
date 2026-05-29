<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN type ENUM('image','video','audio','music','font','brand_logo','voiceover','broll') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_assets MODIFY COLUMN type ENUM('image','video','audio','music','font','brand_logo','voiceover') NOT NULL");
    }
};
