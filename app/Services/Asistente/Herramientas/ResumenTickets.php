<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Panorama general de tickets abiertos. */
class ResumenTickets extends Herramienta
{
    public function nombre(): string
    {
        return 'resumen_tickets';
    }

    public function descripcion(): string
    {
        return 'Resumen de los tickets abiertos de todos los clientes: cuántos hay, por prioridad, cuántos se abrieron hoy '
            . 'y los más antiguos sin cerrar.';
    }

    public function permiso(): string|array|null
    {
        return 'ticket_view_open';
    }

    public function ejecutar(User $user, array $args): array
    {
        $abiertos = DB::table('tickets as t')->whereNotIn('t.estado', TicketsCliente::CERRADOS);

        $porPrioridad = (clone $abiertos)->selectRaw('t.priority, COUNT(*) n')->groupBy('t.priority')->pluck('n', 'priority');
        $antiguos = (clone $abiertos)->leftJoin('client_main_information as c', 'c.client_id', '=', 't.customer_lead')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_to')
            ->orderBy('t.created_at')->limit(5)
            ->get(['t.id', 't.topic', 't.estado', 't.priority', 't.type', 't.created_at', 'u.name as asignado', 'c.name', 'c.father_last_name', 'c.mother_last_name', 't.customer_lead']);

        return [
            'abiertos'      => (clone $abiertos)->count(),
            'abiertos_hoy'  => (clone $abiertos)->whereDate('t.created_at', today())->count(),
            'por_prioridad' => collect(TicketsCliente::PRIORIDADES)->mapWithKeys(fn ($label, $p) => [$label => (int) ($porPrioridad[$p] ?? 0)])
                ->put('Sin prioridad', (int) ($porPrioridad[''] ?? $porPrioridad[null] ?? 0))->all(),
            'mas_antiguos'  => $antiguos->map(fn ($t) => TicketsCliente::fila($t, $this->enlace('ticket', $t->id)) + [
                'cliente' => $t->customer_lead ? DatosCliente::nombre($t) . " (núm. {$t->customer_lead})" : null,
            ])->all(),
            'enlace' => $this->enlace('tickets_abiertos'),
        ];
    }
}
