<?php

namespace App\Services\Asistente\Herramientas;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crea un ticket de soporte para un cliente, con los mismos datos que guarda la pantalla de
 * Tickets (TicketController::store): ticket + primer mensaje del hilo + aviso al asignado.
 */
class CrearTicket extends Herramienta
{
    private const PRIORIDAD = ['urgente' => 1, 'alta' => 2, 'normal' => 3, 'baja' => 4];

    private const TIPOS = ['Pregunta', 'Incidente', 'Problema', 'Solicitud de funcion', 'Cliente potencial'];

    private const GRUPOS = ['Cualquier', 'IT', 'Finanzas', 'Ventas'];

    public function nombre(): string
    {
        return 'crear_ticket';
    }

    public function descripcion(): string
    {
        return 'Crea un ticket de soporte para un cliente (por ejemplo una falla de internet). Prepara el ticket y el usuario lo confirma.';
    }

    public function permiso(): string|array|null
    {
        return 'ticket_add_open';
    }

    public function esCambio(): bool
    {
        return true;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'numero_cliente' => ['type' => 'integer'],
                'tema'           => ['type' => 'string', 'description' => 'Título corto del problema'],
                'mensaje'        => ['type' => 'string', 'description' => 'Descripción de lo que pasa'],
                'prioridad'      => ['type' => 'string', 'enum' => array_keys(self::PRIORIDAD)],
                'tipo'           => ['type' => 'string', 'enum' => self::TIPOS],
                'grupo'          => ['type' => 'string', 'enum' => self::GRUPOS],
            ],
            'required' => ['numero_cliente', 'tema'],
        ];
    }

    public function preparar(User $user, array $args): array
    {
        $numero = $this->entero($args, 'numero_cliente');
        $c = $numero ? DatosCliente::cliente($numero) : null;
        if (!$c) {
            return ['error' => "No encontré al cliente número {$numero}."];
        }
        $tema = trim(mb_substr((string) ($args['tema'] ?? ''), 0, 200));
        if ($tema === '') {
            return ['error' => 'Necesito el tema del ticket.'];
        }

        $prioridad = strtolower((string) ($args['prioridad'] ?? 'normal'));
        $prioridad = isset(self::PRIORIDAD[$prioridad]) ? $prioridad : 'normal';
        $tipo = in_array($args['tipo'] ?? null, self::TIPOS, true) ? $args['tipo'] : 'Incidente';
        $grupo = in_array($args['grupo'] ?? null, self::GRUPOS, true) ? $args['grupo'] : 'Cualquier';
        $mensaje = trim(mb_substr((string) ($args['mensaje'] ?? ''), 0, 4000));
        $nombre = DatosCliente::nombre($c);

        return [
            'resumen' => "Crear ticket para {$nombre} (núm. {$numero}):\n- Tema: {$tema}\n- Prioridad: " . ucfirst($prioridad)
                . "\n- Tipo: {$tipo}\n- Grupo: {$grupo}" . ($mensaje !== '' ? "\n- Mensaje: {$mensaje}" : ''),
            'argumentos' => compact('numero', 'tema', 'prioridad', 'tipo', 'grupo', 'mensaje'),
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $numero = (int) ($args['numero'] ?? 0);
        $c = DatosCliente::cliente($numero);
        if (!$c) {
            return ['error' => 'El cliente ya no existe.'];
        }

        $ticket = DB::transaction(function () use ($user, $args, $c, $numero) {
            $ticket = Ticket::create([
                'customer_lead' => $numero,
                'topic'         => $args['tema'],
                'priority'      => self::PRIORIDAD[$args['prioridad']] ?? 3,
                'type'          => $args['tipo'],
                'group'         => $args['grupo'],
                'hidden'        => false,
                'date_time'     => Carbon::now()->format('Y-m-d\TH:i'),
                'phone'         => $c->phone,
                'phone2'        => $c->phone2,
                'colony_id'     => $c->colony_id ?: null,
                'reporter_id'   => $user->id,
                'reporter'      => $user->name,
                'reporter_type' => implode(', ', $user->getRoleNames()->toArray()),
            ]);
            $ticket->ticketCreateTicketThread([
                'edited_by' => $user->id,
                'client_id' => $numero,
                'message'   => $args['mensaje'] !== '' ? $args['mensaje'] : $args['tema'],
                'hidden'    => false,
            ]);

            return $ticket;
        });

        SendTicketNotificationJob::dispatchAfterResponse($ticket, $ticket->ticket_thread()->first());

        return [
            'ok'      => true,
            'mensaje' => "Listo, creé el ticket número {$ticket->id} para el cliente número {$numero}.",
            'enlace'  => $this->enlace('ticket', $ticket->id),
            'sujeto'  => $ticket,
        ];
    }
}
