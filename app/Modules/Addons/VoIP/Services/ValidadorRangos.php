<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\RangoNumeracion;

/**
 * Valida rangos de numeración y protege el rango de sistema.
 *
 * Las comparaciones son por CADENA, nunca casteando a entero: `desde` y `hasta`
 * son texto para respetar ceros a la izquierda, y `'0100' < '99'` es verdadero
 * como cadena y falso como número. Castear aquí abriría un traslape silencioso.
 */
class ValidadorRangos
{
    /** @return string[] Lista de errores; vacía = válido. */
    public function validar(string $desde, string $hasta, ?int $ignorarId = null): array
    {
        $errores = [];
        $desde   = trim($desde);
        $hasta   = trim($hasta);

        if ($desde === '' || $hasta === '') {
            return ['El rango necesita un número inicial y uno final.'];
        }

        if (! ctype_digit($desde) || ! ctype_digit($hasta)) {
            $errores[] = 'Los extremos del rango deben ser solo dígitos.';
        }

        if (strlen($desde) !== strlen($hasta)) {
            $errores[] = sprintf(
                'Los extremos deben tener la misma cantidad de dígitos: "%s" tiene %d y "%s" tiene %d.',
                $desde, strlen($desde), $hasta, strlen($hasta)
            );
        }

        // Solo tiene sentido comparar si ya sabemos que son comparables.
        if (empty($errores) && strcmp($desde, $hasta) > 0) {
            $errores[] = sprintf('El inicio del rango ("%s") no puede ser mayor que el final ("%s").', $desde, $hasta);
        }

        if (! empty($errores)) {
            return $errores;
        }

        foreach ($this->traslapes($desde, $hasta, $ignorarId) as $otro) {
            $errores[] = sprintf(
                'El rango %s–%s se traslapa con "%s" (%s–%s), que ya existe.',
                $desde, $hasta, $otro->nombre, $otro->desde, $otro->hasta
            );
        }

        return $errores;
    }

    /** @return RangoNumeracion[] */
    public function traslapes(string $desde, string $hasta, ?int $ignorarId = null): array
    {
        return RangoNumeracion::query()
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->get()
            ->filter(fn (RangoNumeracion $r) => $r->seTraslapaCon($desde, $hasta))
            ->values()
            ->all();
    }

    /**
     * ¿Se puede dar de alta una extensión de usuario con este número?
     *
     * El rango protegido (1900–1999 en el plan estándar) es donde viven los
     * enlaces del sistema y las troncales internas. Una extensión de persona ahí
     * rompe la telefonía de la propia instalación — y el rechazo tiene que venir
     * del modelo, no solo del formulario, porque el seeder y la API también crean
     * extensiones.
     *
     * @return string|null Motivo del rechazo, o null si se permite.
     */
    public function motivoRechazoAlta(string $numero): ?string
    {
        $numero = trim($numero);

        foreach (RangoNumeracion::activos()->get() as $rango) {
            if (! $rango->contiene($numero)) {
                continue;
            }

            if ($rango->protegido) {
                return sprintf(
                    'El número %s pertenece al rango "%s" (%s–%s), reservado para el sistema. '
                    . 'Ahí viven los enlaces a centrales externas y las troncales internas: dar de alta '
                    . 'una extensión de usuario en ese rango rompe la telefonía de esta instalación. '
                    . 'Elige un número de otro rango.',
                    $numero, $rango->nombre, $rango->desde, $rango->hasta
                );
            }

            return null;   // cae en un rango normal
        }

        // Fuera de todo rango: no se rechaza, pero tampoco hereda perfil. Que lo
        // decida quien llame; aquí solo se protege el rango de sistema.
        return null;
    }

    public function rangoDe(string $numero): ?RangoNumeracion
    {
        return RangoNumeracion::activos()->get()
            ->first(fn (RangoNumeracion $r) => $r->contiene(trim($numero)));
    }
}
