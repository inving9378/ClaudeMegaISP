<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de llamadas — MegaVoz Fase 3 (#9990718 continuación).
 *
 * ─── QUIÉN ESCRIBE AQUÍ ─────────────────────────────────────────────────────
 *
 * Esta tabla NO se llena desde PHP. La llena Asterisk directo, al colgar cada
 * llamada, vía el backend `cdr_adaptive_odbc` (mapeo en
 * `resources/asterisk/plantillas/cdr_adaptive_odbc.conf.tpl`, misma conexión
 * realtime `asterisk-connector` que ya usan ps_endpoints/queues/etc). Por eso
 * los nombres de columna son los que Asterisk conoce de forma nativa
 * (`uniqueid`, `linkedid`, `disposition`, `billsec`…) — cdr_adaptive_odbc
 * empareja por nombre exacto, sin alias, salvo para `grabacion` (variable de
 * CDR propia, ver DialplanGeneratorService::buildColaExten).
 *
 * Idempotente por diseño de Asterisk: el motor de CDR emite UN registro por
 * llamada al colgar — no hay reintentos ni reescrituras que puedan duplicar
 * una fila. `linkedid` agrupa las patas de una misma llamada (troncal→cola→
 * agente) cuando existan varias.
 *
 * ADITIVA.
 *
 * Conexión `asterisk_rt` (NO la del app) a propósito: es la misma base física
 * "asterisk" que ya usan ps_endpoints/queues/queue_members (ver
 * config/database.php), porque cdr_adaptive_odbc solo puede escribir en la
 * base a la que apunta el DSN `{{DB_NAME}}` de res_odbc.conf — si esta tabla
 * viviera en la base del app (`megaisp`), Asterisk simplemente no la vería.
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    public function up(): void
    {
        if (Schema::connection('asterisk_rt')->hasTable('voip_llamadas')) {
            return;
        }

        Schema::connection('asterisk_rt')->create('voip_llamadas', function (Blueprint $table) {
            $table->id();

            // ── Identidad de la llamada, tal como la conoce Asterisk ──
            $table->string('uniqueid', 150)->unique();
            $table->string('linkedid', 150)->nullable()->index();
            $table->unsignedInteger('sequence')->nullable();

            // ── Quién llamó a quién ──
            $table->string('accountcode', 80)->nullable();
            $table->string('src', 80)->nullable()->comment('CallerID numérico de origen');
            $table->string('dst', 80)->nullable()->comment('Extensión/número marcado');
            $table->string('dcontext', 80)->nullable()->index()
                ->comment('Contexto de destino: grupo-N, inbound-trunk-N, from-internal…');
            $table->string('clid', 160)->nullable()->comment('CallerID completo "Nombre" <numero>');
            $table->string('channel', 160)->nullable();
            $table->string('dstchannel', 160)->nullable();
            $table->string('lastapp', 80)->nullable();
            $table->string('lastdata', 200)->nullable();

            // ── Tiempos y resultado ──
            $table->dateTime('start')->nullable()->index();
            $table->dateTime('answer')->nullable();
            $table->dateTime('end')->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('Segundos totales, desde el marcado');
            $table->unsignedInteger('billsec')->nullable()->comment('Segundos con la llamada contestada');
            $table->string('disposition', 20)->nullable()->index()
                ->comment('ANSWERED|NO ANSWER|BUSY|FAILED|CONGESTION');
            $table->string('amaflags', 20)->nullable();
            $table->string('userfield', 80)->nullable()->comment('Etiqueta puesta en el dialplan: inbound|interno|internal_restricted');
            $table->string('peeraccount', 80)->nullable();

            // ── Variable de CDR propia (Set(CDR(grabacion)=...)) ──
            $table->string('grabacion', 255)->nullable()
                ->comment('Nombre de archivo dentro de config(voip.grabaciones.dir); NULL = no se grabó esta llamada');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['dst', 'start']);
        });
    }

    public function down(): void
    {
        Schema::connection('asterisk_rt')->dropIfExists('voip_llamadas');
    }
};
