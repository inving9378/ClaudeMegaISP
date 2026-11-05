<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1117: telemetría por vuelta leída del stream-json de `claude -p`: fuente de la llave
 * (apiKeySource, solo el NOMBRE de la fuente, nunca el valor), turnos y costo. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circuito_ejecuciones', function (Blueprint $table) {
            if (! Schema::hasColumn('circuito_ejecuciones', 'api_key_source')) {
                $table->string('api_key_source', 40)->nullable()->after('cobro');
            }
            if (! Schema::hasColumn('circuito_ejecuciones', 'num_turns')) {
                $table->unsignedInteger('num_turns')->nullable()->after('api_key_source');
            }
            if (! Schema::hasColumn('circuito_ejecuciones', 'costo_usd')) {
                $table->decimal('costo_usd', 10, 4)->nullable()->after('num_turns');
            }
        });
    }

    public function down(): void
    {
        foreach (['costo_usd', 'num_turns', 'api_key_source'] as $c) {
            if (Schema::hasColumn('circuito_ejecuciones', $c)) {
                Schema::table('circuito_ejecuciones', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
