<?php

namespace App\Modules\Addons\WhatsAppAgent\Services\AgenteVentas;

use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Motor de disponibilidad de instalaciones para el Agente de Ventas.
 *
 * No existía ningún concepto de "capacidad por día" en el sistema (el
 * calendario de Scheduling es un CRUD de tareas sin límite); este servicio lo
 * introduce de la forma más simple posible: cuenta cuántos tickets de
 * instalación (`group='Instalaciones'`, sin importar el canal que los creó —
 * la capacidad real de los técnicos es la misma venga de donde venga) ya
 * existen para un día, contra un tope configurable
 * (`whatsapp.agente_ventas_max_instalaciones_dia`, hoy un ESTIMADO — decisión
 * de Irving, ajustable sin tocar código).
 *
 * Supuesto NO validado con el negocio (a confirmar si hace falta): se
 * consideran TODOS los días de la semana, incluidos domingos, como
 * candidatos — nadie ha dicho que haya días no laborables que excluir.
 */
class AgenteVentasDisponibilidadService
{
    private function tope(): int
    {
        return (int) config('whatsapp.agente_ventas_max_instalaciones_dia', 5);
    }

    /** Cuántas instalaciones ya hay agendadas para esa fecha (cualquier canal). */
    public function ocupadas(string $fechaIso): int
    {
        return Ticket::where('group', 'Instalaciones')
            ->whereDate('date_time', $fechaIso)
            ->count();
    }

    /** ¿Ese día todavía tiene cupo para una instalación más? */
    public function tieneCupo(string $fechaIso): bool
    {
        return $this->ocupadas($fechaIso) < $this->tope();
    }

    /**
     * Próximos $dias días a partir de hoy, con su ocupación y si tienen cupo.
     *
     * @return array<int, array{fecha:string, label:string, ocupadas:int, tope:int, cupo:bool}>
     */
    public function proximosDias(int $dias = 14): array
    {
        $tope = $this->tope();
        $out  = [];

        for ($i = 0; $i < $dias; $i++) {
            $fecha = Carbon::today()->addDays($i);
            $iso   = $fecha->toDateString();
            $ocupadas = $this->ocupadas($iso);

            $out[] = [
                'fecha'    => $iso,
                'label'    => $fecha->locale('es')->translatedFormat('l j \d\e F'),
                'ocupadas' => $ocupadas,
                'tope'     => $tope,
                'cupo'     => $ocupadas < $tope,
            ];
        }

        return $out;
    }

    /**
     * Texto legible para inyectar en el prompt del cerebro — la única fuente
     * de verdad de disponibilidad que la IA puede usar (nunca debe inventar).
     */
    public function formatoParaPrompt(int $dias = 14): string
    {
        $lineas = collect($this->proximosDias($dias))->map(function ($d) {
            $estado = $d['cupo']
                ? "CON CUPO ({$d['ocupadas']}/{$d['tope']})"
                : "LLENO ({$d['ocupadas']}/{$d['tope']}) — NO ofrecer este día";
            return "- {$d['fecha']} ({$d['label']}): {$estado}";
        });

        return $lineas->implode("\n");
    }
}
