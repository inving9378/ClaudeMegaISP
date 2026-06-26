<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios Contratables — FASE 1 (catálogo).
 *
 * `contratable_services` = catálogo de servicios vendibles al cliente (Flotas,
 * MegaFamilia…). El PRECIO no vive aquí: vive en `contratable_packages` (paquetes por
 * rango de unidades). Aquí van metadatos + config de prueba + IVA (para nacer
 * compatible con getTax()/serviceHasIva() del motor de facturación en Fase 2).
 *
 * Aditiva y reversible. No engancha aún a calculateAmounts (eso es Fase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratable_services', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique();          // 'flotas', 'megafamilia'
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('metrica');                        // 'vehiculos' | 'dispositivos'
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('meses_prueba')->default(3); // nº de facturas gratis por defecto
            $table->boolean('aplica_iva')->default(true);     // equivale a custom.tax_include
            $table->decimal('iva_porcentaje', 5, 2)->default(16.00); // equivale a custom.tax
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratable_services');
    }
};
