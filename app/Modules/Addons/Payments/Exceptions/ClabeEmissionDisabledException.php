<?php

namespace App\Modules\Addons\Payments\Exceptions;

use RuntimeException;

/**
 * Excepción de dominio: la emisión de CLABE virtual está deshabilitada.
 *
 * La lanza OpenPayService::createClabe() ANTES de llamar a OpenPay cuando:
 *   - el flag global config('openpay.clabe_emission_enabled') está en false, O
 *   - el provider está en sandbox / con credenciales stub (CLABE no enrutable).
 *
 * Los controladores la capturan para devolver un 503 con mensaje amable, en
 * lugar de dejar reventar un QueryException (Duplicate entry) como 500 con el
 * mensaje crudo de la BD expuesto al usuario.
 */
class ClabeEmissionDisabledException extends RuntimeException
{
    /** Mensaje seguro para mostrar al usuario final (sin detalles internos). */
    public const USER_MESSAGE = 'La emisión de CLABE está deshabilitada por el momento (pendiente de activación de cobros SPEI). Inténtalo más tarde o contacta a tu proveedor.';

    public function __construct(string $message = 'CLABE emission disabled')
    {
        parent::__construct($message);
    }
}
