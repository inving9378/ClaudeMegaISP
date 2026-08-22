<?php

namespace App\Services\Ipv6\Exceptions;

use DomainException;

/**
 * Excepción de dominio de Ipv6RenumberingStateMachine (item #1067): transición
 * inválida (salto de estado), punto crítico faltante antes de 'retirado', o
 * rollback() bloqueado porque el plan ya está en 'retirado' (punto de no
 * retorno). Nunca tiene bypass ni flag admin — el mensaje siempre explica
 * qué falta.
 */
class Ipv6RenumberingStateException extends DomainException
{
}
