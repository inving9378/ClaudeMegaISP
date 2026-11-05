<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1116 — IA económica PASO 3: reglas por día/hora, presupuesto diario y su historial.
 * Aditiva: sin filas el supervisor se comporta como hasta hoy (interruptor `activo` apagado).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('circuito_ia_reglas')) {
            Schema::create('circuito_ia_reglas', function (Blueprint $t) {
                $t->id();
                $t->unsignedTinyInteger('dia_semana');   // 1 = lunes … 7 = domingo (ISO)
                $t->time('hora_inicio');
                $t->time('hora_fin');
                $t->string('modo', 16);                  // economico | normal
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('circuito_ia_ajustes')) {
            Schema::create('circuito_ia_ajustes', function (Blueprint $t) {
                $t->string('clave', 64)->primary();
                $t->string('valor', 255)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('circuito_ia_historial')) {
            Schema::create('circuito_ia_historial', function (Blueprint $t) {
                $t->id();
                $t->string('clave', 64);
                $t->string('anterior', 255)->nullable();
                $t->string('nuevo', 255)->nullable();
                $t->string('usuario', 120);
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_ia_historial');
        Schema::dropIfExists('circuito_ia_ajustes');
        Schema::dropIfExists('circuito_ia_reglas');
    }
};
