<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_ai_agent_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('name', 100);
            $table->foreignId('channel_id')->constrained('marketing_channels')->onDelete('cascade');
            $table->text('system_prompt');
            $table->text('persona')->nullable();
            $table->longText('knowledge_base')->nullable();
            $table->unsignedTinyInteger('max_turns_before_human')->default(5);
            $table->unsignedBigInteger('auto_assign_to_user_id')->nullable();
            $table->string('model', 50)->default('claude-opus-4-7');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedSmallInteger('max_tokens')->default(1024);
            $table->json('tools_enabled')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_ai_agent_configs');
    }
};
