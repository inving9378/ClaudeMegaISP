<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa ps_contacts al schema canónico de Asterisk 20.19.0.
 *
 * La tabla fue creada con el schema mínimo de Asterisk 18; las columnas
 * via_addr/via_port/call_id/endpoint/prune_on_boot/reg_server/
 * authenticate_qualify son necesarias para que res_pjsip_registrar
 * persista los contactos de los softphones vía ODBC realtime.
 *
 * Sin estas columnas, cada REGISTER del softphone produce:
 *   WARNING res_odbc.c: Unknown column 'via_addr' in 'field list'
 *   ERROR res_pjsip_registrar.c: Unable to bind contact ... to AOR
 *
 * Idempotente: solo agrega columnas que no existan.
 * Diseñado para replicarse en cada VM de tenant (modelo SaaS).
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    private function asteriskAvailable(): bool
    {
        try {
            DB::connection('asterisk_rt')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (! $this->asteriskAvailable()) {
            return;
        }

        Schema::connection('asterisk_rt')->table('ps_contacts', function (Blueprint $table) {
            // Asterisk 20 escribe via_addr:via_port cuando el REGISTER llega con Via header
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'via_addr')) {
                $table->string('via_addr', 40)->nullable()->default(null);
            }

            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'via_port')) {
                $table->integer('via_port')->unsigned()->nullable()->default(null);
            }

            // call_id del REGISTER para detectar re-registros vs nuevos registros
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'call_id')) {
                $table->string('call_id', 255)->nullable()->default(null);
            }

            // endpoint al que pertenece el contacto (link de vuelta para lookups)
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'endpoint')) {
                $table->string('endpoint', 255)->nullable()->default(null);
            }

            // si se debe eliminar el contacto al reiniciar Asterisk
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'prune_on_boot')) {
                $table->tinyInteger('prune_on_boot')->default(0);
            }

            // servidor de registro que "posee" este contacto (multi-server SaaS)
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'reg_server')) {
                $table->string('reg_server', 255)->nullable()->default(null);
            }

            // si debe hacer QUALIFY autenticado antes de considerarlo activo
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'authenticate_qualify')) {
                $table->tinyInteger('authenticate_qualify')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! $this->asteriskAvailable()) {
            return;
        }

        Schema::connection('asterisk_rt')->table('ps_contacts', function (Blueprint $table) {
            foreach ([
                'via_addr',
                'via_port',
                'call_id',
                'endpoint',
                'prune_on_boot',
                'reg_server',
                'authenticate_qualify',
            ] as $col) {
                if (Schema::connection('asterisk_rt')->hasColumn('ps_contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
