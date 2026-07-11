<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agente revisor #338. ADITIVA / reversible.
 *  - estado_aprobacion += 'aprobado_revisor' (autorización del REVISOR adversarial, distinta
 *    de 'aprobado_claude' [A auto] y 'aprobado_irving' [aprobación humana de Irving]).
 *  - tabla `circuito_revisiones`: auditoría de CADA veredicto del revisor (todo revertible).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving','cancelado','aprobado_revisor') NOT NULL DEFAULT 'pendiente_revision'");

        if (! Schema::hasTable('circuito_revisiones')) {
            Schema::create('circuito_revisiones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('roadmap_item_id')->index();
                $table->string('veredicto', 20);                       // autoriza | escala
                $table->boolean('en_alcance')->default(false);
                $table->string('categoria_escalada', 40)->nullable();  // dinero|seguridad|prod|negocio|amplio|duda|fuera_alcance
                $table->string('confianza', 10)->nullable();           // alta|media|baja
                $table->text('motivo')->nullable();
                $table->text('riesgos')->nullable();                   // json array
                $table->string('modelo', 60)->nullable();
                $table->text('decisor_ctx')->nullable();
                $table->unsignedInteger('tokens_in')->nullable();
                $table->unsignedInteger('tokens_out')->nullable();
                $table->boolean('aplicado')->default(false);           // ¿cambió el estado del item?
                $table->string('actor', 190)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_revisiones');
        // Revierte el enum (requiere que ningún item quede en 'aprobado_revisor').
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving','cancelado') NOT NULL DEFAULT 'pendiente_revision'");
    }
};
