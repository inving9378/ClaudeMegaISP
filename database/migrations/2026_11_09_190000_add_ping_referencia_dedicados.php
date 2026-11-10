<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ping de referencia externo por servicio dedicado (item #1002, épica #988): una IP que el propio
 * cliente monitorea (su servidor, VPN o un DNS), medida desde el MikroTik del servicio. Va en su
 * propia tabla para no mezclarse con el ping a las IPs del bloque (disponibilidad/estado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('servicio_monitoreo', 'ip_referencia')) {
            Schema::table('servicio_monitoreo', function (Blueprint $table) {
                $table->string('ip_referencia', 45)->nullable()->comment('IP externa de referencia que el cliente monitorea (#1002)');
            });
        }

        if (! Schema::hasTable('cliente_metricas_referencia')) {
            Schema::create('cliente_metricas_referencia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->string('ip', 45);
                $table->dateTime('ts');
                $table->unsignedTinyInteger('enviados');
                $table->unsignedTinyInteger('recibidos');
                $table->decimal('lat_min_ms', 9, 3)->nullable();
                $table->decimal('lat_avg_ms', 9, 3)->nullable();
                $table->decimal('lat_max_ms', 9, 3)->nullable();
                $table->string('estado', 24)->comment('responde | sin_respuesta | omitido | error');
                $table->string('origen', 16)->default('mikrotik');
                $table->unique(['servicio_id', 'ts']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_metricas_referencia');
        if (Schema::hasColumn('servicio_monitoreo', 'ip_referencia')) {
            Schema::table('servicio_monitoreo', fn (Blueprint $t) => $t->dropColumn('ip_referencia'));
        }
    }
};
