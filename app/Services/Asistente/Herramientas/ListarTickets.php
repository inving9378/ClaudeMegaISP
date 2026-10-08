<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lista o cuenta tickets de soporte de todos los clientes, con filtros (abiertos/cerrados, prioridad,
 * a quién están asignados, fechas). Para los de un solo cliente está tickets_cliente.
 */
class ListarTickets extends Herramienta
{
    use FiltrosListado;

    private const PRIORIDAD = ['urgente' => 1, 'alta' => 2, 'normal' => 3, 'baja' => 4];

    public function nombre(): string
    {
        return 'listar_tickets';
    }

    public function descripcion(): string
    {
        return 'Lista o cuenta tickets de soporte de todos los clientes: los últimos, abiertos o cerrados, por prioridad, '
            . 'por persona asignada o por fechas. Para los tickets de un cliente concreto usa tickets_cliente.';
    }

    public function permiso(): string|array|null
    {
        return ['ticket_view_open', 'ticket_view_close'];
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->propiedadesListado() + [
                'estado'    => ['type' => 'string', 'enum' => ['abiertos', 'cerrados', 'todos'], 'description' => 'Por defecto abiertos.'],
                'prioridad' => ['type' => 'string', 'enum' => array_keys(self::PRIORIDAD)],
                'asignado'  => ['type' => 'string', 'description' => 'Nombre (o parte) de la persona asignada.'],
                'orden'     => ['type' => 'string', 'enum' => ['recientes', 'antiguos'], 'description' => 'Por defecto recientes.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $rango = $this->rango($args);
        if (isset($rango['error'])) {
            return $rango;
        }

        $q = DB::table('tickets as t')
            ->leftJoin('client_main_information as c', 'c.client_id', '=', 't.customer_lead')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_to');

        $estado = $args['estado'] ?? 'abiertos';
        $filtros = [];
        if ($estado === 'abiertos') {
            $q->whereNotIn('t.estado', TicketsCliente::CERRADOS);
            $filtros[] = 'abiertos';
        } elseif ($estado === 'cerrados') {
            $q->whereIn('t.estado', TicketsCliente::CERRADOS);
            $filtros[] = 'cerrados';
        }
        if (isset(self::PRIORIDAD[$args['prioridad'] ?? ''])) {
            $q->where('t.priority', self::PRIORIDAD[$args['prioridad']]);
            $filtros[] = 'prioridad ' . $args['prioridad'];
        }
        if (($asignado = trim((string) ($args['asignado'] ?? ''))) !== '') {
            $q->whereRaw("CONCAT_WS(' ', u.name, u.father_last_name, u.mother_last_name) LIKE ?", ['%' . $asignado . '%']);
            $filtros[] = "asignados a «{$asignado}»";
        }
        if ($rango[0]) {
            $q->where('t.created_at', '>=', $rango[0]);
        }
        if ($rango[1]) {
            $q->where('t.created_at', '<=', $rango[1]);
        }
        if ($p = $this->periodoTexto($rango)) {
            $filtros[] = "creados {$p}";
        }

        $total = (clone $q)->count();
        $respuesta = [
            'total_que_coinciden' => $total,
            'filtros'             => $filtros ?: ['todos los tickets'],
            'enlace_lista'        => $this->enlace('tickets_abiertos'),
        ];
        if (!empty($args['solo_contar'])) {
            return $respuesta;
        }

        ($args['orden'] ?? 'recientes') === 'antiguos'
            ? $q->orderBy('t.created_at')->orderBy('t.id')
            : $q->orderByDesc('t.created_at')->orderByDesc('t.id');

        $filas = $q->limit($this->cuantos($args))->get([
            't.id', 't.topic', 't.estado', 't.priority', 't.type', 't.created_at', 't.customer_lead',
            'u.name as asignado', 'c.name', 'c.father_last_name', 'c.mother_last_name',
        ]);

        return $respuesta + [
            'mostrando' => $filas->count(),
            'tickets'   => $filas->map(fn ($t) => TicketsCliente::fila($t, $this->enlace('ticket', $t->id)) + [
                'cliente' => $t->customer_lead ? self::nombreDe($t) . " (núm. {$t->customer_lead})" : null,
            ])->all(),
        ];
    }
}
