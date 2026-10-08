<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;

class ConsultarGuia extends Herramienta
{
    public function nombre(): string
    {
        return 'consultar_guia';
    }

    public function descripcion(): string
    {
        return 'Lee una sección de la guía del sistema para explicar cómo se hace algo o dónde está una pantalla. '
            . 'Temas disponibles: ' . implode(', ', $this->conocimiento->temas()) . '.';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['tema' => ['type' => 'string', 'enum' => $this->conocimiento->temas()]],
            'required'   => ['tema'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $texto = $this->conocimiento->guia((string) ($args['tema'] ?? ''));

        return $texto === null
            ? ['error' => 'Ese tema no existe en la guía. Temas: ' . implode(', ', $this->conocimiento->temas())]
            : ['guia' => $texto];
    }
}
