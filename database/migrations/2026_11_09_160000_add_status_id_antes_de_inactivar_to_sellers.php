<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Al quitar el check «Añadir rol de vendedor» el vendedor ya no se borra: pasa a Inactivo y aquí
 * se guarda el estado que tenía (Activo, Bloqueado, Suspendido…), para devolvérselo al reactivarlo.
 * Así un bloqueo no se pierde por inactivar y reactivar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sellers') || Schema::hasColumn('sellers', 'status_id_antes_de_inactivar')) {
            return;
        }

        Schema::table('sellers', function (Blueprint $table) {
            $table->unsignedBigInteger('status_id_antes_de_inactivar')->nullable()->after('status_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sellers', 'status_id_antes_de_inactivar')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('status_id_antes_de_inactivar');
            });
        }
    }
};
