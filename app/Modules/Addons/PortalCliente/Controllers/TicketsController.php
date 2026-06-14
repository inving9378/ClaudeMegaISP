<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketsController extends Controller
{
    /**
     * Listado de tickets del cliente autenticado.
     * Filtro de aislamiento: customer_lead = client_id (condición #9 satisfecha).
     * La tabla tickets reutiliza el mismo modelo que el admin (no se duplica).
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $tickets = DB::table('tickets')
            ->where('customer_lead', $clientId)
            ->orderByDesc('id')
            ->get(['id', 'topic', 'estado', 'group', 'type', 'priority', 'created_at']);

        return view('addon-portal-cliente::tickets.index', compact('cmi', 'tickets'));
    }

    /**
     * Detalle de un ticket.
     * Verifica aislamiento: customer_lead = client_id.
     */
    public function show(int $id)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        $ticket = DB::table('tickets')
            ->where('id', $id)
            ->where('customer_lead', $clientId)
            ->first();

        if (! $ticket) {
            abort(404);
        }

        $hilos = DB::table('ticket_threads')
            ->where('ticket_id', $id)
            ->where('hidden', false)
            ->orderBy('created_at')
            ->get(['id', 'message', 'edited_by', 'created_at']);

        // Resolver nombres de editores
        $hilos = $hilos->map(function ($h) {
            $nombre = 'Soporte Meganet';
            if ($h->edited_by) {
                $u = DB::table('users')->where('id', $h->edited_by)->value('name');
                if ($u) $nombre = $u;
            }
            $h->autor = $nombre;
            return $h;
        });

        return view('addon-portal-cliente::tickets.show', compact('ticket', 'hilos'));
    }

    /**
     * Crear nuevo ticket desde el portal.
     * Reutiliza la tabla tickets — el mismo registro que ve el admin.
     */
    public function store(Request $request)
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $data = $request->validate([
            'topic'       => 'required|string|max:255',
            'tipo'        => 'required|in:Pregunta,Incidente,Problema,Solicitud de función',
            'descripcion' => 'nullable|string|max:5000',
        ]);

        $ticketId = DB::table('tickets')->insertGetId([
            'topic'         => '[Portal] ' . $data['topic'],
            'estado'        => 'Nuevo',
            'group'         => 'Cualquier',
            'type'          => $data['tipo'],
            'reporter'      => $cmi->nombre_completo,
            'reporter_id'   => $cmi->id,
            'reporter_type' => 'portal_client',
            'customer_lead' => $clientId,
            'phone'         => $cmi->phone,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        if (! empty($data['descripcion'])) {
            DB::table('ticket_threads')->insert([
                'ticket_id'  => $ticketId,
                'edited_by'  => null,
                'client_id'  => $clientId,
                'message'    => $data['descripcion'],
                'hidden'     => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('portal.tickets.show', $ticketId)
            ->with('success', 'Ticket creado correctamente. Nuestro equipo te atenderá pronto.');
    }

    /**
     * Responder a un ticket (hilo).
     * Verifica aislamiento antes de escribir.
     */
    public function responder(Request $request, int $id)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        $ticket = DB::table('tickets')
            ->where('id', $id)
            ->where('customer_lead', $clientId)
            ->first();

        if (! $ticket) {
            abort(404);
        }

        if (in_array($ticket->estado, ['Cerrado', 'Resuelto'])) {
            return back()->withErrors(['respuesta' => 'No puedes responder a un ticket cerrado o resuelto.']);
        }

        $data = $request->validate([
            'respuesta' => 'required|string|max:3000',
        ]);

        $cmi = Auth::guard('cliente')->user();

        DB::table('ticket_threads')->insert([
            'ticket_id'  => $id,
            'edited_by'  => null,
            'client_id'  => $clientId,
            'message'    => $data['respuesta'],
            'hidden'     => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cambiar estado a "Esperando al agente" si estaba en "Esperando al cliente"
        if ($ticket->estado === 'Esperando al cliente') {
            DB::table('tickets')->where('id', $id)->update([
                'estado'     => 'Esperando al agente',
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('portal.tickets.show', $id)
            ->with('success', 'Respuesta enviada.');
    }
}
