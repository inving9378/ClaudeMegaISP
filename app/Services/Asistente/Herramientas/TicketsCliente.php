<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Tickets de soporte de un cliente. */
class TicketsCliente extends Herramienta
{
    public const PRIORIDADES = [1 => 'Urgente', 2 => 'Alta', 3 => 'Normal', 4 => 'Baja'];

    public const CERRADOS = ['Cerrado', 'Reciclado', 'Resuelto'];

    public function nombre(): string
    {
        return 'tickets_cliente';
    }

    public function descripcion(): string
    {
        return 'Tickets de soporte de un cliente (tema, estado, prioridad, fecha y a quién está asignado). '
            . 'Por defecto solo los abiertos.';
    }

    public function permiso(): string|array|null
    {
        return ['ticket_view_open', 'ticket_view_close'];
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'numero_cliente'    => ['type' => 'integer'],
                'incluir_cerrados'  => ['type' => 'boolean'],
            ],
            'required' => ['numero_cliente'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $numero = $this->entero($args, 'numero_cliente');
        $c = $numero ? DatosCliente::cliente($numero) : null;
        if (!$c) {
            return DatosCliente::noEncontrado((int) $numero);
        }

        $q = DB::table('tickets as t')->leftJoin('users as u', 'u.id', '=', 't.assigned_to')->where('t.customer_lead', $numero);
        if (empty($args['incluir_cerrados'])) {
            $q->whereNotIn('t.estado', self::CERRADOS);
        }

        return [
            'cliente' => DatosCliente::nombre($c) . " (núm. {$numero})",
            'tickets' => $q->orderByDesc('t.id')->limit(10)->get(['t.id', 't.topic', 't.estado', 't.priority', 't.type', 't.created_at', 'u.name as asignado'])
                ->map(fn ($t) => self::fila($t, $this->enlace('ticket', $t->id)))->all(),
            'enlace_ficha' => $this->enlace('ficha_cliente', $numero),
        ];
    }

    public static function fila(object $t, ?string $enlace): array
    {
        return [
            'ticket'     => $t->id,
            'tema'       => $t->topic,
            'estado'     => $t->estado === 'Nuevo' ? 'Abierto (nuevo)' : $t->estado,
            'prioridad'  => self::PRIORIDADES[(int) $t->priority] ?? 'Sin prioridad',
            'tipo'       => $t->type ?? null,
            'creado'     => DatosCliente::fecha($t->created_at, true),
            'asignado_a' => $t->asignado ?? 'Sin asignar',
            'enlace'     => $enlace,
        ];
    }
}
