<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Retira el catálogo de puestos (item #923) y el texto libre job_title, primer tiempo: el puesto
 * de cada persona es su rol en Administración y el código ya no lee el catálogo. Nadie tenía
 * puesto (0 de 35 en dev, copia de prod del 15-oct) y el único uso era un paquete de documentos
 * para "Vendedor". Decisión del 2026-10-21.
 *
 * Aquí: los paquetes de documentos pasan a guardar el nombre del rol en la columna `puesto` y se
 * quita el permiso talento.puestos.manage. La tabla talento_puestos y las columnas puesto_id y
 * job_title se borran en la migración de contracción (rama contraccion/talento-niveles-puestos),
 * cuando esta versión lleve los días mínimos en producción (guardrail #1018).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_puesto_document_templates')) {
            $this->paquetesARoles();
        }

        DB::table('permissions')->where('name', 'talento.puestos.manage')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Cambia el nombre del puesto del catálogo por el rol que le corresponde ("Técnico" → TECNICO). */
    private function paquetesARoles(): void
    {
        $normal = fn (string $s) => Str::lower(Str::ascii($s));
        $roles = DB::table('roles')->pluck('name')->mapWithKeys(fn ($r) => [$normal($r) => $r]);

        foreach (DB::table('talento_puesto_document_templates')->get() as $fila) {
            $rol = $roles[$normal((string) $fila->puesto)] ?? null;
            if (!$rol || $rol === $fila->puesto) {
                continue;
            }
            $yaExiste = DB::table('talento_puesto_document_templates')
                ->where('puesto', $rol)->where('template_id', $fila->template_id)->exists();
            $yaExiste
                ? DB::table('talento_puesto_document_templates')->where('id', $fila->id)->delete()
                : DB::table('talento_puesto_document_templates')->where('id', $fila->id)->update(['puesto' => $rol]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->insertOrIgnore(['name' => 'talento.puestos.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
