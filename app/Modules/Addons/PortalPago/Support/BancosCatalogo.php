<?php

namespace App\Modules\Addons\PortalPago\Support;

/**
 * Catálogo de bancos comunes en México para el select del reporte de pago.
 *
 * ⚠️ Hoy el valor es el NOMBRE del banco. El CEP de Banxico requiere la CLAVE
 * del participante (3-5 dígitos). El mapeo nombre→clave es deuda registrada en
 * Hoja de Ruta (#161); mientras tanto, si la clave no coincide, Banxico no
 * encuentra el pago y el reporte cae a discrepancia/revisión manual.
 */
class BancosCatalogo
{
    public static function comunes(): array
    {
        return [
            'BBVA',
            'Citibanamex',
            'Santander',
            'Banorte',
            'HSBC',
            'Scotiabank',
            'Inbursa',
            'Banco Azteca',
            'BanCoppel',
            'Banregio',
            'Afirme',
            'Banjercito',
            'Mifel',
            'Bancrea',
            'STP',
            'Nu',
            'Hey Banco',
            'Klar',
            'Mercado Pago',
            'Otro',
        ];
    }
}
