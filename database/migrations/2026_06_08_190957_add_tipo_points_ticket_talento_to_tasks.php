<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Discriminador principal Tarea interna vs Tarea de campo (ex-OT)
            $table->enum('tipo', ['interna', 'campo'])
                  ->default('interna')
                  ->after('status');

            // Puntos para liquidación (solo aplica cuando tipo=campo)
            $table->unsignedSmallInteger('points')
                  ->default(0)
                  ->after('tipo');

            // Si la tarea genera puntos facturables al cliente
            $table->boolean('is_billable')
                  ->default(false)
                  ->after('points');

            // FK opcional al Ticket que originó la tarea
            $table->unsignedBigInteger('ticket_id')->nullable()->after('is_billable');
            $table->foreign('ticket_id')
                  ->references('id')->on('tickets')
                  ->onDelete('set null');

            // FK opcional al catálogo de tipos de OT (solo cuando tipo=campo)
            $table->unsignedBigInteger('talento_type_id')->nullable()->after('ticket_id');
            $table->foreign('talento_type_id')
                  ->references('id')->on('talento_work_order_types')
                  ->onDelete('set null');

            // Índice para queries del translation layer (Capa 3)
            $table->index(['tipo', 'status'], 'idx_tasks_tipo_status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropForeign(['talento_type_id']);
            $table->dropIndex('idx_tasks_tipo_status');
            $table->dropColumn(['tipo', 'points', 'is_billable', 'ticket_id', 'talento_type_id']);
        });
    }
};
