<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_video_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('description', 255)->nullable();
            $table->enum('category', [
                'plan_promo', 'coverage_announcement', 'testimonial', 'payment_reminder',
                'welcome', 'flash_promo', 'maintenance_notice', 'generic',
            ]);
            $table->json('aspect_ratios');
            $table->unsignedSmallInteger('duration_seconds')->default(15);
            $table->json('schema');
            $table->string('preview_thumbnail', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_video_templates');
    }
};
