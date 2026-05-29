<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('type', [
                'created', 'message_sent', 'message_received', 'score_updated',
                'stage_changed', 'assigned', 'note', 'call', 'meeting',
                'conversion', 'lost', 'custom',
            ]);
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->foreignId('channel_id')->nullable()->constrained('marketing_channels')->onDelete('set null');
            $table->timestamp('happened_at')->index();
            $table->timestamps();
            $table->index(['lead_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_activities');
    }
};
