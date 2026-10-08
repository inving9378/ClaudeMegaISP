<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\Asistente\Recuerdo;
use App\Models\User;

/** Guarda algo que el usuario pidió explícitamente recordar (su propia memoria, nadie más la ve). */
class Recordar extends Herramienta
{
    public function nombre(): string
    {
        return 'recordar';
    }

    public function descripcion(): string
    {
        return 'Guarda en tu memoria algo que el usuario te pidió recordar (una preferencia, cómo le gusta que '
            . 'le contestes, en qué trabaja). No guardes datos que cambian, como saldos o estados de clientes.';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['texto' => ['type' => 'string', 'description' => 'Lo que hay que recordar, en una frase']],
            'required'   => ['texto'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $texto = trim(mb_substr((string) ($args['texto'] ?? ''), 0, 500));
        if ($texto === '') {
            return ['error' => 'No hay nada que recordar.'];
        }

        Recuerdo::firstOrCreate(
            ['user_id' => $user->id, 'contenido' => $texto, 'activo' => true],
            ['origen' => 'usuario']
        );

        return ['ok' => true, 'mensaje' => 'Guardado en tu memoria.'];
    }
}
