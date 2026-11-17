<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfonos web en su propio segmento (17-nov-2026): las gemelas `web1011` pasan a
 * números de 4 dígitos (`2011`). El historial de llamadas guarda el canal con el número
 * de ese momento (PJSIP/web1011-…); `numero_anterior` permite seguir diciendo de quién
 * era esa llamada. Aditiva y nullable: no cambia nada de lo que ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('voip_extensiones', 'numero_anterior')) {
            return;
        }
        Schema::table('voip_extensiones', function (Blueprint $table) {
            $table->string('numero_anterior', 20)->nullable()->after('numero')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('voip_extensiones', 'numero_anterior')) {
            return;
        }
        Schema::table('voip_extensiones', function (Blueprint $table) {
            $table->dropIndex(['numero_anterior']);
            $table->dropColumn('numero_anterior');
        });
    }
};
