<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->foreignId('source_id')->nullable()->constrained('marketing_lead_sources')->onDelete('set null');
            $table->string('full_name', 150);
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('whatsapp', 30)->nullable()->index();
            $table->string('address', 255)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedBigInteger('plan_interested_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('score_reason')->nullable();
            $table->json('tags')->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'negotiating', 'won', 'lost', 'archived'])->default('new');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->timestamp('captured_at')->index();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->string('lost_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
