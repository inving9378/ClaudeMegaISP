<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('slug', 60)->index();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->json('fields_schema');
            $table->json('styling')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->string('thank_you_redirect_url', 500)->nullable();
            $table->foreignId('assign_to_source_id')->nullable()->constrained('marketing_lead_sources')->onDelete('set null');
            $table->unsignedBigInteger('assign_to_user_id')->nullable()->index();
            $table->foreignId('assign_to_pipeline_id')->nullable()->constrained('marketing_pipelines')->onDelete('set null');
            $table->boolean('auto_scoring')->default(true);
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(5);
            $table->unsignedSmallInteger('rate_limit_per_hour')->default(50);
            $table->boolean('recaptcha_enabled')->default(false);
            $table->string('recaptcha_site_key', 100)->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_forms');
    }
};
