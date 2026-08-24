<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * Una fila del tablero de compuertas. Es un objeto de transporte: lo arma
 * `CompuertasService` midiendo el sistema y lo consume la UI tal cual.
 *
 * `semaforo`: 'verde' pasa · 'rojo' bloquea · 'ambar' pasa pero con advertencia.
 * `origen`:   de dónde salió el valor medido — 'config' | 'bd' | 'env' | 'so'.
 *             No es decorativo: es lo que evita el error del 24-ago, cuando supervisor
 *             decía una cosa y el proceso vivo decía otra.
 */
class Compuerta
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $semaforo,
        public string $valor,
        public string $origen,
        public ?string $porQue = null,
        public array $acciones = [],
        public ?string $comando = null,
        public ?string $quienPuede = null,
    ) {
    }

    public function bloquea(): bool
    {
        return $this->semaforo === 'rojo';
    }

    /**
     * Regla 1 — sin callejones sin salida: un rojo sin acción ejecutable debe al menos
     * decir quién puede actuar y con qué comando. Si no trae ninguna de las dos cosas,
     * la fila está incompleta y el tablero lo marca en vez de mostrar un rojo mudo.
     */
    public function tieneSalida(): bool
    {
        return $this->acciones !== [] || ($this->comando !== null && $this->quienPuede !== null);
    }

    public function toArray(): array
    {
        return [
            'clave'       => $this->clave,
            'nombre'      => $this->nombre,
            'semaforo'    => $this->semaforo,
            'valor'       => $this->valor,
            'origen'      => $this->origen,
            'por_que'     => $this->porQue,
            'acciones'    => $this->acciones,
            'comando'     => $this->comando,
            'quien_puede' => $this->quienPuede,
            'bloquea'     => $this->bloquea(),
            'sin_salida'  => $this->bloquea() && ! $this->tieneSalida(),
        ];
    }
}
