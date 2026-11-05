<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #988 — El monitoreo es parte del servicio dedicado: la configuración se crea sola (automática, sin
 * revisar, hasta que alguien guarde «Datos de red») y se pausa o reanuda según el estado del cliente y
 * del servicio. Los periodos de pausa se guardan con su motivo para no contarlos como caída.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicio_monitoreo', function (Blueprint $table) {
            if (! Schema::hasColumn('servicio_monitoreo', 'automatica')) {
                $table->boolean('automatica')->default(false)->after('activo')->comment('Creada sola y sin revisar');
            }
            if (! Schema::hasColumn('servicio_monitoreo', 'motivo_pausa')) {
                $table->string('motivo_pausa', 40)->nullable()->after('automatica')->comment('Por qué no se mide (ver PoliticaMonitoreo)');
            }
            if (! Schema::hasColumn('servicio_monitoreo', 'revisado_por')) {
                $table->unsignedBigInteger('revisado_por')->nullable()->after('motivo_pausa');
            }
            if (! Schema::hasColumn('servicio_monitoreo', 'revisado_at')) {
                $table->dateTime('revisado_at')->nullable()->after('revisado_por');
            }
        });

        if (! Schema::hasTable('servicio_monitoreo_pausas')) {
            Schema::create('servicio_monitoreo_pausas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->string('motivo', 40);
                $table->dateTime('desde');
                $table->dateTime('hasta')->nullable()->comment('null = sigue en pausa');
                $table->index(['servicio_id', 'desde']);
                $table->index('hasta');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_monitoreo_pausas');
        $columnas = array_filter(['automatica', 'motivo_pausa', 'revisado_por', 'revisado_at'], fn ($c) => Schema::hasColumn('servicio_monitoreo', $c));
        if ($columnas !== []) {
            Schema::table('servicio_monitoreo', fn (Blueprint $table) => $table->dropColumn($columnas));
        }
    }
};
