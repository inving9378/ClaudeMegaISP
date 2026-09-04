<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.2a (item #729/#762) — clasificación de proveedores para el Apartado
 * X (10 conceptos, todos con fuente `finanzas.proveedores` + `config.clasificacion`).
 *
 * `suppliers` (tabla compartida con Finanzas/Inventario) NO se toca: la
 * clasificación vive en esta tabla pivote, aditiva y reversible. Un proveedor
 * puede tener varias clasificaciones (ej. tecnologia + programadores), por
 * eso son filas independientes y no una columna en `suppliers`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_proveedor_clasificaciones')) {
            Schema::create('dc_proveedor_clasificaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->enum('clasificacion', [
                    'telecomunicaciones', 'tecnologia', 'contratistas', 'programadores',
                    'desarrolladores', 'capacitadores', 'asesores', 'contadores',
                    'despachos', 'estrategicos',
                ]);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['supplier_id', 'clasificacion']);
                $table->index('clasificacion');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_proveedor_clasificaciones');
    }
};
