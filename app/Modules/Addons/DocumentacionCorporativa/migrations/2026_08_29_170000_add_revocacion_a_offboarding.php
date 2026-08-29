<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 5d, checklist de offboarding (apartado XII,
 * item roadmap #761).
 *
 * Aditiva: dos columnas nullable en las dos tablas que el checklist recorre
 * (`dc_inventario_accesos` y `dc_activos_digitales`) para poder MARCAR un
 * acceso/activo como revocado sin borrar el registro — la fila sigue siendo
 * la fuente de verdad de "qué existía y quién lo custodiaba"; revocar solo
 * anota que ya se le quitó el acceso a esa persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['dc_inventario_accesos', 'dc_activos_digitales'] as $tabla) {
            if (Schema::hasColumn($tabla, 'revocado_at')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('revocado_at')->nullable()->after('notas');
                $table->foreignId('revocado_por_user_id')->nullable()->after('revocado_at')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['dc_inventario_accesos', 'dc_activos_digitales'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'revocado_at')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('revocado_por_user_id');
                $table->dropColumn('revocado_at');
            });
        }
    }
};
