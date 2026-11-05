<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1077: constancia por vuelta de cómo se cobra (suscripcion|api|desconocido). Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('circuito_ejecuciones', 'cobro')) {
            Schema::table('circuito_ejecuciones', function (Blueprint $table) {
                $table->string('cobro', 20)->nullable()->after('modelo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('circuito_ejecuciones', 'cobro')) {
            Schema::table('circuito_ejecuciones', function (Blueprint $table) {
                $table->dropColumn('cobro');
            });
        }
    }
};
