<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #1115 — «Modelos de IA»: modo activo (fila única) + historial de cambios. Aditiva y reversible. */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('circuito_ia_config')) {
            Schema::create('circuito_ia_config', function (Blueprint $t) {
                $t->id();
                $t->string('modo', 20)->default('ahorro');          // ahorro | normal | potente
                $t->string('modo_previo', 20)->nullable();           // al que vuelve «Potente 2 h»
                $t->timestamp('potente_hasta')->nullable();
                $t->json('piezas')->nullable();                      // {modo:{pieza:modelo}} (ediciones)
                $t->json('verificados')->nullable();                 // {modelo:{ok,at,error}}
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('circuito_ia_cambios')) {
            Schema::create('circuito_ia_cambios', function (Blueprint $t) {
                $t->id();
                $t->string('tipo', 30);                              // modo|pieza|potente|expira|fallback|verificacion
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('pieza', 40)->nullable();
                $t->string('antes', 120)->nullable();
                $t->string('despues', 120)->nullable();
                $t->string('detalle', 500)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['tipo', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_ia_cambios');
        Schema::dropIfExists('circuito_ia_config');
    }
};
