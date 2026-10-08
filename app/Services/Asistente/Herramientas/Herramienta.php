<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\ConocimientoAsistente;

/**
 * Una capacidad concreta de JARVIS ("buscar cliente", "crear ticket"...).
 *
 * - permiso(): el MISMO permiso que pide la pantalla equivalente. Sin él, la IA ni siquiera ve la
 *   herramienta, y se vuelve a revisar al ejecutar.
 * - esCambio(): las que crean o modifican algo. Pasan primero por preparar() (valida y describe el
 *   cambio en lenguaje de usuario) y solo se ejecutan cuando el usuario confirma (ver limites.json).
 * - Lo que devuelven son datos para la IA: textos ya en lenguaje de usuario, montos y fechas
 *   formateados, y enlaces internos listos para usar.
 */
abstract class Herramienta
{
    public function __construct(protected ConocimientoAsistente $conocimiento)
    {
    }

    abstract public function nombre(): string;

    abstract public function descripcion(): string;

    /** JSON Schema (objeto) de los argumentos. Vacío = sin argumentos. */
    public function parametros(): array
    {
        return [];
    }

    public function permiso(): string|array|null
    {
        return null;
    }

    public function esCambio(): bool
    {
        return false;
    }

    /** Lectura: devuelve los datos. Cambio: lo aplica (ya confirmado por el usuario). */
    abstract public function ejecutar(User $user, array $args): array;

    /**
     * Solo para cambios: valida y describe lo que se va a hacer.
     *
     * @return array{resumen:string, argumentos:array}|array{error:string}
     */
    public function preparar(User $user, array $args): array
    {
        return ['resumen' => '', 'argumentos' => $args];
    }

    public function esquema(): array
    {
        return ['nombre' => $this->nombre(), 'descripcion' => $this->descripcion(), 'parametros' => $this->parametros()];
    }

    protected function enlace(string $clave, int|string|null $id = null): ?string
    {
        return $this->conocimiento->enlace($clave, $id);
    }

    protected function entero(array $args, string $clave): ?int
    {
        $v = preg_replace('/\D/', '', (string) ($args[$clave] ?? ''));

        return $v === '' ? null : (int) ltrim($v, '0');
    }
}
