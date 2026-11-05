<?php

namespace App\Modules\Addons\Tickets\Services;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plazos de atención (G3c). Reloj corrido 24/7. Sin plazo configurado devuelve null: no se inventan compromisos.
 */
class TicketPlazoService
{
    /** Fila más específica: (tipo, prioridad) > (tipo, *) > (*, prioridad) > (*, *). */
    public function plazoPara(?string $type, ?int $priority): ?object
    {
        $filas = DB::table('ticket_plazos')
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', $type))
            ->where(fn ($q) => $q->whereNull('priority')->orWhere('priority', $priority))
            ->get();

        return $filas->sortByDesc(fn ($f) => ($f->type !== null ? 2 : 0) + ($f->priority !== null ? 1 : 0))->first();
    }

    /** @return array|null null si no hay plazo de resolución configurado. */
    public function estado(Ticket $ticket, ?Carbon $ahora = null): ?array
    {
        $plazo = $this->plazoPara($ticket->type, $ticket->priority);
        if (!$plazo || !$plazo->horas_resolucion) {
            return null;
        }

        $inicio = Carbon::parse($ticket->getRawOriginal('created_at'));
        $fin = $ticket->closed_at
            ? Carbon::parse($ticket->getRawOriginal('closed_at'))
            : ($ahora ?? now());
        $transcurridas = round($inicio->diffInMinutes($fin) / 60, 1);
        $limite = (int) $plazo->horas_resolucion;
        $uso = $transcurridas / $limite;

        return [
            'horas_transcurridas' => $transcurridas,
            'horas_plazo' => $limite,
            'estado' => $uso >= 1 ? 'vencido' : ($uso >= 0.8 ? 'por_vencer' : 'en_tiempo'),
            'cerrado' => (bool) $ticket->closed_at,
        ];
    }
}
