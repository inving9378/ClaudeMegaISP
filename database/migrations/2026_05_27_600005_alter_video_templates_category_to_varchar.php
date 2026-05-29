<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_video_templates MODIFY COLUMN category VARCHAR(100) NOT NULL DEFAULT 'generic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_video_templates MODIFY COLUMN category ENUM('plan_promo','coverage_announcement','testimonial','payment_reminder','welcome','flash_promo','maintenance_notice','generic') NOT NULL DEFAULT 'generic'");
    }
};
