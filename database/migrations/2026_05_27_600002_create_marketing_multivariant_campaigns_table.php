<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_multivariant_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->default(1)->index();

            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('campaign_type', 50);
            $table->enum('status', ['draft', 'generating', 'ready', 'partially_failed', 'failed'])->default('draft');

            $table->json('input_data');
            $table->json('creative_briefs')->nullable();
            $table->json('variant_content_ids')->nullable();

            $table->decimal('total_cost_usd', 10, 4)->default(0);
            $table->integer('variants_succeeded')->default(0);
            $table->integer('variants_failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_multivariant_campaigns');
    }
};
