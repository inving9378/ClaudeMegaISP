<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #556 (CRM-E 02): datos de facturación empresarial sobre client_fiscal_data
 * (fuente única; no se crea una segunda tabla fiscal). Solo columnas aditivas, todas nullable.
 * No toca el UNIQUE de client_id ni el comportamiento del upsert actual.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'num_exterior' => ['string', 30],
        'num_interior' => ['string', 30],
        'colonia' => ['string', 150],
        'municipio' => ['string', 150],
        'estado' => ['string', 100],
        'pais' => ['string', 100],
        'forma_pago_cfdi' => ['string', 3],
        'metodo_pago_cfdi' => ['string', 3],
        'requiere_orden_compra' => ['boolean', null],
        'dia_recepcion_facturas' => ['string', 100],
        'dias_credito' => ['unsignedSmallInteger', null],
        'condiciones_credito' => ['string', 500],
        'contacto_facturacion_nombre' => ['string', 200],
        'contacto_facturacion_cargo' => ['string', 150],
        'contacto_facturacion_telefono' => ['string', 40],
        'contacto_facturacion_extension' => ['string', 20],
        'contacto_facturacion_celular' => ['string', 40],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('client_fiscal_data')) {
            return;
        }

        Schema::table('client_fiscal_data', function (Blueprint $t) {
            foreach (self::COLUMNAS as $col => [$tipo, $len]) {
                if (Schema::hasColumn('client_fiscal_data', $col)) {
                    continue;
                }
                $len ? $t->$tipo($col, $len)->nullable() : $t->$tipo($col)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Aditiva a propósito: no se borran datos fiscales capturados.
    }
};
