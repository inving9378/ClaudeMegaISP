<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registra el ítem de roadmap "Integración PAC CFDI 4.0" en la tabla de roadmap
 * si existe. Si no, es una no-op (no falla).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('roadmap_items')) {
            return;
        }

        $existe = DB::table('roadmap_items')
            ->where('title', 'like', '%PAC%CFDI%')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('roadmap_items')->insert([
            'title'       => 'Integración PAC para CFDI 4.0 (factura fiscal)',
            'description' => 'Implementar TimbradoServiceInterface con un proveedor PAC real ' .
                             '(candidatos: Facturama, Finkok, SW Sapien, Formas Digitales). ' .
                             'Incluye: timbrado, UUID, sellos, cadena original, QR, cancelación, ' .
                             'almacenamiento de XML + PDF, envío por correo al correo_fiscal del cliente. ' .
                             'La interfaz y el stub NullTimbradoService están listos; solo se necesita ' .
                             'decidir el proveedor e implementar el adaptador.',
            'status'      => 'pending',
            'priority'    => 'alta',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void {}
};
