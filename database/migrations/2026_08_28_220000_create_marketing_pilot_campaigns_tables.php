<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piloto interno de campaña multivariante (A/B) — item roadmap #47.
 *
 * Alcance aprobado por Irving (log del item, respuestas q1-q4): piloto
 * ACOTADO a lista de prueba (empleados/cuentas dummy), 2 variantes A/B,
 * canal SOLO email, Irving define el copy — el circuito solo construye la
 * mecánica de variantes + tracking. Sin tocar clientes reales ni el
 * pipeline de publicación FB/IG existente (PostPublisherService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_pilot_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('name', 150);
            $table->enum('status', ['draft', 'dry_run', 'ready_to_send', 'sending', 'sent', 'canceled'])
                ->default('draft');

            $table->string('variant_a_subject', 255);
            $table->text('variant_a_body');
            $table->string('variant_a_cta_label', 80)->nullable();
            $table->string('variant_a_cta_url', 500)->nullable();

            $table->string('variant_b_subject', 255);
            $table->text('variant_b_body');
            $table->string('variant_b_cta_label', 80)->nullable();
            $table->string('variant_b_cta_url', 500)->nullable();

            $table->unsignedSmallInteger('batch_size')->default(10);
            $table->unsignedSmallInteger('batch_pause_seconds')->default(5);

            $table->json('dry_run_report')->nullable();
            $table->timestamp('dry_run_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketing_pilot_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilot_campaign_id')->constrained('marketing_pilot_campaigns')->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('name', 150)->nullable();
            $table->enum('variant', ['a', 'b']);
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'sent', 'failed', 'opened', 'clicked', 'converted'])
                ->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['pilot_campaign_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_pilot_campaign_sends');
        Schema::dropIfExists('marketing_pilot_campaigns');
    }
};
