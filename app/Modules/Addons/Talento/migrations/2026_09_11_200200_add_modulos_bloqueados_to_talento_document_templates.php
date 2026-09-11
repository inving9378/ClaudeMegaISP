<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #9990804 — mapa declarativo documento->módulos bloqueados, insumo del middleware
 * global de bloqueo proporcional. Decisión ya resuelta por Irving (padre #9990792, q1):
 * bloqueo PROPORCIONAL por módulo, no total. Backfill de los templates tipo='firma'
 * existentes según la tabla del item padre: Convenio de Comisiones -> solo
 * prospectos/ventas/comisiones; el resto (Contrato/confidencialidad/resguardos/etc.)
 * -> TODO lo operativo (sentinel '*').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            $table->json('modulos_bloqueados')->nullable()->after('tipo');
        });

        DB::table('talento_document_templates')
            ->where('tipo', 'firma')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($tpl): void {
                $nombre = mb_strtolower((string) $tpl->name);
                $modulos = str_contains($nombre, 'comisi')
                    ? ['prospectos', 'ventas', 'comisiones']
                    : ['*'];

                DB::table('talento_document_templates')
                    ->where('id', $tpl->id)
                    ->update(['modulos_bloqueados' => json_encode($modulos)]);
            });
    }

    public function down(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            $table->dropColumn('modulos_bloqueados');
        });
    }
};
