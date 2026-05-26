<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->foreignId('channel_id')->nullable()->constrained('marketing_channels')->onDelete('set null');
            $table->enum('touchpoint', ['first_touch', 'mid_touch', 'last_touch', 'conversion']);
            $table->decimal('weight', 4, 3)->default(1.000);
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'touchpoint']);
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_attributions');
    }
};
