<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #336: registra qué modelo (sonnet/opus/alias) usó cada vuelta del ejecutor on-box,
 * para auditar el gasto por modelo en el panel Ejecuciones. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('circuito_ejecuciones', 'modelo')) {
            Schema::table('circuito_ejecuciones', function (Blueprint $table) {
                $table->string('modelo', 40)->nullable()->after('modo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('circuito_ejecuciones', 'modelo')) {
            Schema::table('circuito_ejecuciones', function (Blueprint $table) {
                $table->dropColumn('modelo');
            });
        }
    }
};
