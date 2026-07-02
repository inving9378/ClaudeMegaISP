<?php

namespace App\Modules\Addons\Payments\Services\Extraction;

/**
 * FASE PAGOS 3 (pieza 2) — Perfil de extracción de un tipo de comprobante.
 *
 * Cada tipo de documento (SPEI, Oxxo, CEP oficial, …) implementa esta interfaz.
 * Agregar un tipo nuevo = crear un perfil y registrarlo en
 * PaymentReceiptExtractor; el motor no cambia.
 */
interface ReceiptProfileInterface
{
    /** Identificador del tipo de documento (ej. 'spei_transfer'). */
    public function type(): string;

    /** Llaves de los campos a extraer, en orden de despliegue. */
    public function fields(): array;

    /** Prompt ESTRICTO (anti-invención) que se manda a la IA con la imagen. */
    public function prompt(): string;
}
