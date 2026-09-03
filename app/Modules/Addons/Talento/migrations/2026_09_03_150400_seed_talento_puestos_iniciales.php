<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #923 — Fase 4. Catálogo inicial derivado de EVIDENCIA real (no inventado):
 * roles Spatie que hoy tienen colaboradores reales en `talento_colaboradores` (verificado en
 * dev 2026-09-03: Vendedor=23, TECNICO=3, Mostrador=2, Almacen=1). Se excluyen 'Administrador'
 * y 'client' (no son puestos operativos, son roles de cuenta) y TECNICO_INSTALADOR/
 * TECNICO_PLANTA (0 colaboradores hoy, sin evidencia). Decisión registrada en el reporte del
 * item. Idempotente (firstOrCreate por nombre); forward-only (down() no borra el catálogo:
 * pudo haberse editado/ampliado desde la pantalla /talento/puestos).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['Vendedor', 'Técnico', 'Mostrador', 'Almacén'] as $nombre) {
            $exists = DB::table('talento_puestos')->where('nombre', $nombre)->exists();
            if (!$exists) {
                DB::table('talento_puestos')->insert([
                    'nombre'     => $nombre,
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: el catálogo pudo editarse desde la UI, no se borra.
    }
};
