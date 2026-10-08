<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;

/**
 * Cuando JARVIS no tiene cómo hacer algo que le pidieron, lo anota en la bitácora (activity_log,
 * log_name 'jarvis', descripción "Pendiente: ...") para que el equipo sepa qué capacidad agregarle.
 * Consulta: activity_log where log_name='jarvis' and description like 'Pendiente:%'.
 */
class AnotarPendiente extends Herramienta
{
    public function nombre(): string
    {
        return 'anotar_pendiente';
    }

    public function descripcion(): string
    {
        return 'Úsala SIEMPRE que te pidan algo del sistema que tus herramientas no permiten hacer: anota el pedido '
            . 'para que el equipo te agregue esa capacidad. Después dile al usuario, sin pretextos, que todavía no '
            . 'puedes hacerlo tú y dónde lo puede hacer.';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'pedido' => ['type' => 'string', 'description' => 'Qué pidió el usuario, en una frase.'],
            ],
            'required' => ['pedido'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $pedido = trim(mb_substr((string) ($args['pedido'] ?? ''), 0, 300));
        if ($pedido === '') {
            return ['error' => 'Falta el pedido.'];
        }

        activity('jarvis')->causedBy($user)->withProperties(['pedido' => $pedido])->log('Pendiente: ' . $pedido);

        return ['ok' => true, 'nota' => 'Anotado para el equipo.'];
    }
}
