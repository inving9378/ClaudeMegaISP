<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Jobs\BlastCampanaJob;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CobranzaCampanaService
{
    /**
     * Morosos de la campaña: facturas con saldo vencidas hace «Días vencimiento» días o
     * más (25-nov: antes ese campo no se usaba y entraba cualquier factura vencida).
     *
     * @return array{insertados: int, sin_telefono: int}
     */
    public function cargarMorosos(CobranzaCampana $campana): array
    {
        $morosos = $this->consultaMorosos($campana)->get();

        $r = $this->insertar($campana, $morosos, true);

        Log::info("CobranzaCampanaService: {$r['insertados']} morosos cargados en campaña #{$campana->id}"
            . ($r['sin_telefono'] ? " ({$r['sin_telefono']} sin un teléfono de 10 dígitos, no se les llama)" : ''));

        return $r;
    }

    /**
     * Morosos que entrarían a la campaña: facturas con saldo vencidas hace «Días
     * vencimiento» días o más, clientes Activo/Bloqueado con teléfono, sin una llamada
     * abierta en esta campaña. La usan la carga y la vista previa («a cuántos»).
     */
    private function consultaMorosos(CobranzaCampana $campana): \Illuminate\Database\Query\Builder
    {
        return DB::table('clients as c')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'c.id')
            ->join('invoices as i', 'i.client_id', '=', 'c.id')
            ->where('i.due_date', '<=', self::fechaDeCorte((int) $campana->dias_vencimiento))
            ->where('i.pending_balance', '>', 0)
            ->whereNotIn('i.status', ['paid', 'cancelled'])
            // El estado del cliente vive en client_main_information.estado (clients no
            // tiene columna status; bug de Fase 7).
            ->whereIn('cmi.estado', ['Activo', 'Bloqueado']) // #491: el moroso ya cortado (Bloqueado) también se cobra
            ->whereNotNull('cmi.phone')
            ->whereNotExists(function ($q) use ($campana) {
                $q->from('cobranza_llamadas')
                    ->whereColumn('cobranza_llamadas.client_id', 'c.id')
                    ->where('cobranza_llamadas.campana_id', $campana->id)
                    ->whereNotIn('cobranza_llamadas.estado', ['pagada', 'excluida']);
            })
            ->select(
                'c.id as client_id',
                'cmi.phone',
                'cmi.name',
                DB::raw('SUM(i.pending_balance) as monto_vencido')
            )
            ->groupBy('c.id', 'cmi.phone', 'cmi.name');
    }

    /**
     * A cuántos clientes llamaría la campaña si se activa ahora (25-nov, pedido de Irving:
     * que «activar» diga a cuántos antes de hacerlo). Mismo criterio que la carga.
     *
     * @return array{clientes: int, sin_telefono: int, monto: float}
     */
    public function alcance(CobranzaCampana $campana, ?int $districtId = null, ?int $zoneId = null, ?int $boxId = null): array
    {
        $filas = $campana->tipo === 'cobranza'
            ? $this->consultaMorosos($campana)->get()
            : $this->consultaPorZona($campana, (int) $districtId, $zoneId, $boxId)->get();

        $clientes = [];
        $sinTelefono = [];
        $monto = 0.0;
        foreach ($filas as $f) {
            if (isset($clientes[$f->client_id])) {
                continue;
            }
            if (CobranzaLlamada::normalizarTelefono($f->phone) === null) {
                $sinTelefono[$f->client_id] = true;
                continue;
            }
            $clientes[$f->client_id] = true;
            unset($sinTelefono[$f->client_id]);
            $monto += (float) ($f->monto_vencido ?? 0);
        }

        return ['clientes' => count($clientes), 'sin_telefono' => count($sinTelefono), 'monto' => round($monto, 2)];
    }

    /** @return array{insertados: int, sin_telefono: int} */
    public function activarCampana(CobranzaCampana $campana): array
    {
        // Todo o nada: si algo falla a media carga, la campaña queda como estaba.
        $r = DB::transaction(function () use ($campana) {
            $r = $this->cargarMorosos($campana);
            $campana->update(['estado' => 'activa']);

            return $r;
        });
        BlastCampanaJob::dispatch($campana->id);

        Log::info("Campaña #{$campana->id} activada con {$r['insertados']} llamadas cargadas.");

        return $r;
    }

    /**
     * Saldo vencido del cliente hoy, con el mismo criterio que la carga. Antes de cada
     * llamada de cobranza se vuelve a ver: el que ya pagó no recibe la llamada.
     */
    public static function adeudoVencido(int $clientId, int $diasVencimiento): float
    {
        return (float) DB::table('invoices')
            ->where('client_id', $clientId)
            ->where('due_date', '<=', self::fechaDeCorte($diasVencimiento))
            ->where('pending_balance', '>', 0)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->sum('pending_balance');
    }

    /** Facturas que vencieron en esta fecha o antes (0 días = hasta hoy, como antes). */
    public static function fechaDeCorte(int $diasVencimiento): string
    {
        return now()->subDays(max(0, $diasVencimiento))->toDateString();
    }

    /**
     * MegaVoz Fase 7 — "botón de corte por distrito/zona/caja". Resuelve
     * clientes vía `box_zones` (distrito → zona → caja, la nomenclatura
     * DxZyCz de `NomenclatureController`) en vez de por morosidad. Usado por
     * los tipos aviso/anuncio/corte — cobranza sigue siendo SIEMPRE por
     * morosos (activarCampana()).
     *
     * $districtId es obligatorio (no se dispara un blast a "todo el país" por
     * accidente); $zoneId/$boxId opcionales van angostando el alcance.
     *
     * @return array{insertados: int, sin_telefono: int}
     */
    public function cargarPorZona(CobranzaCampana $campana, int $districtId, ?int $zoneId = null, ?int $boxId = null): array
    {
        $clientes = $this->consultaPorZona($campana, $districtId, $zoneId, $boxId)->get();

        $r = $this->insertar($campana, $clientes, false);

        Log::info("CobranzaCampanaService: {$r['insertados']} clientes de zona (D{$districtId}" .
            ($zoneId ? "Z{$zoneId}" : '') . ($boxId ? "C{$boxId}" : '') .
            ") cargados en campaña #{$campana->id}"
            . ($r['sin_telefono'] ? " ({$r['sin_telefono']} sin un teléfono de 10 dígitos)" : ''));

        return $r;
    }

    private function consultaPorZona(CobranzaCampana $campana, int $districtId, ?int $zoneId, ?int $boxId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('box_zones as bz')
            ->join('zones as z', 'z.id', '=', 'bz.zone_id')
            ->join('clients as c', 'c.id', '=', 'bz.client')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'c.id')
            ->where('z.district_id', $districtId)
            ->whereIn('cmi.estado', ['Activo', 'Bloqueado']) // #491: el moroso ya cortado (Bloqueado) también se cobra
            ->whereNotNull('cmi.phone')
            ->whereNotExists(function ($q) use ($campana) {
                $q->from('cobranza_llamadas')
                    ->whereColumn('cobranza_llamadas.client_id', 'c.id')
                    ->where('cobranza_llamadas.campana_id', $campana->id);
            });

        if ($zoneId) {
            $query->where('bz.zone_id', $zoneId);
        }
        if ($boxId) {
            $query->where('bz.id', $boxId);
        }

        return $query->select('c.id as client_id', 'cmi.phone', 'cmi.name')->distinct();
    }

    /** Sigue marcando lo que ya tenía cargado (no vuelve a cargar clientes). */
    public function reanudarCampana(CobranzaCampana $campana): void
    {
        $campana->update(['estado' => 'activa']);
        BlastCampanaJob::dispatch($campana->id);
    }

    /** @return array{insertados: int, sin_telefono: int} */
    public function activarCampanaPorZona(CobranzaCampana $campana, int $districtId, ?int $zoneId = null, ?int $boxId = null): array
    {
        $r = DB::transaction(function () use ($campana, $districtId, $zoneId, $boxId) {
            $r = $this->cargarPorZona($campana, $districtId, $zoneId, $boxId);
            $campana->update(['estado' => 'activa']);

            return $r;
        });
        BlastCampanaJob::dispatch($campana->id);

        Log::info("Campaña #{$campana->id} ({$campana->tipo}) activada por zona con {$r['insertados']} llamadas cargadas.");

        return $r;
    }

    /**
     * Una fila por cliente con su número ya limpio (10 dígitos). El que no tiene un
     * número que se pueda marcar no entra: se cuenta para decírselo a quien activa.
     *
     * @return array{insertados: int, sin_telefono: int}
     */
    private function insertar(CobranzaCampana $campana, Collection $clientes, bool $conMonto): array
    {
        $insertados = 0;
        $sinTelefono = [];   // por cliente: uno con dos filas sin número cuenta una vez
        $vistos = [];

        foreach ($clientes as $c) {
            if (isset($vistos[$c->client_id])) {
                continue;   // el mismo cliente con dos filas (dos teléfonos distintos en el join)
            }
            $telefono = CobranzaLlamada::normalizarTelefono($c->phone);
            if ($telefono === null) {
                $sinTelefono[$c->client_id] = true;
                continue;
            }
            $vistos[$c->client_id] = true;
            unset($sinTelefono[$c->client_id]);

            CobranzaLlamada::create([
                'campana_id'         => $campana->id,
                'client_id'          => $c->client_id,
                'telefono'           => $telefono,
                'monto_vencido'      => $conMonto ? $c->monto_vencido : 0,
                'estado'             => 'pendiente',
                'proximo_intento_at' => null,
            ]);
            $insertados++;
        }

        return ['insertados' => $insertados, 'sin_telefono' => count($sinTelefono)];
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
