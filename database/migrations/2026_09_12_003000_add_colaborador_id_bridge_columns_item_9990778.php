<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 de #9990778 (Identidad unificada) — puente aditivo hacia `talento_colaboradores`.
 *
 * El diagnóstico de Fase 1 (#9990801, docs/identidad-vendedores-inventario-seller-id-item-9990801.md)
 * determinó, con datos y triangulando 3 consumidores de código, que en estas dos tablas
 * `seller_id` es en realidad un `users.id` (NO un `sellers.id`, pese al nombre de columna y pese
 * a que el modelo también declara una relación muerta hacia `Seller`). Por eso `colaborador_id`
 * se resuelve vía `talento_colaboradores.user_id = seller_id` — sigue siendo el mismo bridge por
 * `user_id` que ya usa el resto del sistema (Actor::seller(), item #123), no un ID inventado.
 *
 * `seller_id` NO se toca (se mantiene como fuente de verdad hasta la Fase 4 del item). Esta
 * migración solo agrega columna nullable + índice — reversible con un `down()` limpio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            if (!Schema::hasColumn('client_main_information', 'colaborador_id')) {
                $table->unsignedBigInteger('colaborador_id')->nullable()->after('seller_id');
                $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
                $table->index('colaborador_id');
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'colaborador_id')) {
                $table->unsignedBigInteger('colaborador_id')->nullable()->after('seller_id');
                $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
                $table->index('colaborador_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            if (Schema::hasColumn('client_main_information', 'colaborador_id')) {
                $table->dropForeign(['colaborador_id']);
                $table->dropColumn('colaborador_id');
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'colaborador_id')) {
                $table->dropForeign(['colaborador_id']);
                $table->dropColumn('colaborador_id');
            }
        });
    }
};
