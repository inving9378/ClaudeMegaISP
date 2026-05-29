<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->enum('type', ['meta_ads', 'google_ads', 'organic', 'email_blast', 'sms_blast', 'voice_blast', 'mixed', 'manual']);
            $table->foreignId('source_id')->nullable()->constrained('marketing_lead_sources')->onDelete('set null');
            $table->string('external_id', 100)->nullable()->index();
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('spent', 12, 2)->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'finished', 'canceled'])->default('draft');
            $table->json('config')->nullable();
            $table->json('goals')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
