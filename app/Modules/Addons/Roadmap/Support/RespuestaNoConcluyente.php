<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * El modelo contestó, pero su respuesta no puede leerse como un veredicto (#1076).
 *
 * Hoy el único caso es `stop_reason: "refusal"`: los modelos 5.x tienen clasificadores de seguridad
 * que declinan con un HTTP 200 normal. Un rechazo NO es un «escala» ni un «autoriza», es la
 * ausencia de juicio — por eso se lanza en vez de devolver texto vacío: cada pieza del supervisor
 * ya trata la excepción con su falla-segura (el keyword manda, el item va a Irving, etc.).
 */
class RespuestaNoConcluyente extends \RuntimeException
{
}
