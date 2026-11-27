<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Jobs\BlastCampanaJob;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CobranzaCampanaService
{
    public function __construct(private AudienciaCampana $audiencia) {}

    /**
     * Activa la campaña con la lista que se revisó (27-nov, pedido de Irving): se llama a los
     * clientes marcados que siguen entrando con los filtros (estado, zona, caja y, si aplica, los
     * que deben). El que se marcó pero ya no entra (pagó, cambió de estado o de caja) no se llama,
     * y el que entró después de revisar la lista tampoco: nadie recibe una llamada sin haberse visto.
     *
     * Todo o nada: si algo falla a media carga, la campaña queda como estaba.
     *
     * La campaña se bloquea mientras se carga: dos activaciones a la vez no duplican las llamadas.
     *
     * @param  int[]  $clienteIds  los que se dejaron marcados
     * @param  int[]  $quitados    los que se quitaron a mano (solo para el registro)
     * @return array{insertados: int, sin_telefono: int, ya_no_entran: int}
     */
    public function activar(CobranzaCampana $campana, array $filtros, bool $soloDeudores, int $diasVencimiento, array $clienteIds, array $quitados = []): array
    {
        $r = DB::transaction(function () use ($campana, $filtros, $soloDeudores, $diasVencimiento, $clienteIds, $quitados) {
            $estado = CobranzaCampana::whereKey($campana->id)->lockForUpdate()->value('estado');
            if (! in_array($estado, ['borrador', 'pausada'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'La campaña ya se activó (quizá desde otra pestaña). Recarga la lista de campañas.',
                ]);
            }

            $vista = AudienciaCampana::resumir(
                $this->audiencia->candidatos($campana, $soloDeudores, $diasVencimiento), $filtros, true
            );

            $marcados = array_flip(array_map('intval', $clienteIds));
            $entran = array_filter($vista['clientes'], fn ($c) => isset($marcados[$c['id']]));
            if (! $entran) {
                throw ValidationException::withMessages([
                    'clientes' => 'Ninguno de los clientes marcados entra ya con estos filtros (pudieron pagar o cambiar de estado). Revisa la lista otra vez.',
                ]);
            }

            foreach ($entran as $c) {
                CobranzaLlamada::create([
                    'campana_id'         => $campana->id,
                    'client_id'          => $c['id'],
                    'telefono'           => $c['telefono'],
                    'monto_vencido'      => $soloDeudores ? $c['monto'] : 0,
                    'estado'             => 'pendiente',
                    'proximo_intento_at' => null,
                ]);
            }

            // Solo los que se quitaron a mano y siguen en la lista (no los que entraron después).
            $quitados = array_values(array_intersect(array_map('intval', $quitados), array_column($vista['clientes'], 'id')));

            $campana->update([
                'estado'           => 'activa',
                'solo_deudores'    => $soloDeudores,
                'dias_vencimiento' => $diasVencimiento,
                'audiencia'        => $vista['filtros'] + ['quitados' => $quitados],
            ]);

            return [
                'insertados'   => count($entran),
                'sin_telefono' => $vista['sin_telefono'],
                'ya_no_entran' => count($marcados) - count($entran),
            ];
        });

        BlastCampanaJob::dispatch($campana->id);

        Log::info("Campaña #{$campana->id} ({$campana->tipo}) activada con {$r['insertados']} llamadas cargadas"
            . ($r['ya_no_entran'] ? "; {$r['ya_no_entran']} marcados ya no entraban" : '') . '.');

        return $r;
    }

    /**
     * Saldo vencido del cliente hoy, con el mismo criterio que la carga. Antes de cada
     * llamada de cobranza se vuelve a ver: el que ya pagó no recibe la llamada.
     */
    public static function adeudoVencido(int $clientId, int $diasVencimiento): float
    {
        return (float) self::facturasVencidas($diasVencimiento)->where('client_id', $clientId)->sum('pending_balance');
    }

    /**
     * Facturas con saldo vencidas hace «días» o más: el criterio único de «los que deben» (la lista,
     * la carga y el «ya pagó» antes de cada llamada). Sin las borradas (27-nov: con SoftDeletes
     * fuera, en dev 1,118 Activos «debían» solo por facturas borradas).
     */
    public static function facturasVencidas(int $diasVencimiento): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('due_date', '<=', self::fechaDeCorte($diasVencimiento))
            ->where('pending_balance', '>', 0)
            ->whereNotIn('status', ['paid', 'cancelled']);
    }

    /** Facturas que vencieron en esta fecha o antes (0 días = hasta hoy, como antes). */
    public static function fechaDeCorte(int $diasVencimiento): string
    {
        return now()->subDays(max(0, $diasVencimiento))->toDateString();
    }

    /** Sigue marcando lo que ya tenía cargado (no vuelve a cargar clientes). */
    public function reanudarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'activa']);
        BlastCampanaJob::dispatch($campana->id);
    }

    public function pausarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'pausada']);
    }

    public function completarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'completada']);
    }

    public function estadisticas(CobranzaCampana $campana): array
    {
        $conteos = $campana->llamadas()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $total = array_sum($conteos);

        return [
            'total'           => $total,
            'pendiente'       => $conteos['pendiente']   ?? 0,
            'marcando'        => $conteos['marcando']    ?? 0,
            'contestada'      => $conteos['contestada']  ?? 0,
            'no_contesto'     => $conteos['no_contesto'] ?? 0,
            'ocupado'         => $conteos['ocupado']     ?? 0,
            'fallida'         => $conteos['fallida']     ?? 0,
            'pagada'          => $conteos['pagada']      ?? 0,
            'excluida'        => $conteos['excluida']    ?? 0,
            'porcentaje_completado' => $campana->porcentaje_completado,
        ];
    }
}
