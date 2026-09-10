<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rol de instalación exigido por un módulo (Sprint 1 · Voz Mayorista).
 *
 * ADITIVA y nullable: `null` = el módulo no exige ningún rol y corre en
 * cualquier instalación. Los 48 módulos ya registrados quedan en `null`, así
 * que ninguno cambia de comportamiento al aplicar esta migración.
 *
 * El valor lo escribe `ModuleLifecycleService` desde `instance_role` del
 * manifiesto al instalar. Persistirlo —en vez de leerlo solo del module.json—
 * permite responder desde la base "qué módulos de esta instalación son de
 * operador" sin recorrer archivos, que es lo que hará falta para auditar
 * cuando existan varios (facturación mayorista, aprovisionamiento de VMs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('module_registry', 'instance_role')) {
            return;
        }

        Schema::table('module_registry', function (Blueprint $table) {
            $table->string('instance_role', 20)
                ->nullable()
                ->after('type')
                ->comment('Rol de instalación exigido: operador|cliente. NULL = cualquiera.');
            $table->index('instance_role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('module_registry', 'instance_role')) {
            return;
        }

        Schema::table('module_registry', function (Blueprint $table) {
            $table->dropIndex(['instance_role']);
            $table->dropColumn('instance_role');
        });
    }
};
