<?php

namespace App\Modules\Addons\GestionRed\Services;

use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Modules\Core\Clientes\Services\ClienteSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de discrepancias de SN captura-manual vs. OLT (item #9990837, Fase 4).
 *
 * NUNCA compara contra serie_equipo_norm: ese campo (#9990835) se puebla con
 * PRIORIDAD "OLT gana si existe" (NormalizarSeriesClientesCommand líneas
 * 87-96), así que ya trae el valor de la OLT cuando ambos existen — comparado
 * contra olt_onus.sn daría 0 discrepancias siempre. Aquí se compara SIEMPRE
 * client_additional_information.modem_sn (crudo, captura manual) contra
 * olt_onus.sn (crudo), ambos normalizados en caliente con
 * ClienteSearchService::normalizarSn().
 */
class DiscrepanciaSnService
{
    public const CATEGORIAS = ['no-normaliza', 'difiere-olt', 'sin-cliente-activo'];

    public function paginar(string $categoria, int $page, int $perPage): LengthAwarePaginator
    {
        $filas = $this->todas($categoria);

        $perPage = max(1, min($perPage, 500));
        $page = max(1, $page);

        return new LengthAwarePaginator(
            array_slice($filas, ($page - 1) * $perPage, $perPage),
            count($filas),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public function todas(string $categoria): array
    {
        return match ($categoria) {
            'no-normaliza' => $this->noNormaliza(),
            'difiere-olt' => $this->difiereDeOlt(),
            'sin-cliente-activo' => $this->enOltSinClienteActivo(),
            default => throw new \InvalidArgumentException("Categoría de discrepancia SN desconocida: {$categoria}"),
        };
    }

    /**
     * 1) Clientes con modem_sn capturado que NO normaliza al formato canónico
     * GPON de 16 hex (ni 16-hex directo ni 12-char 4ASCII+8hex de la OLT). Un
     * cliente WiFi sin fila en olt_onus NO es excepción automática — solo
     * entra si su modem_sn en sí no normaliza.
     */
    public function noNormaliza(): array
    {
        $rows = DB::table('client_additional_information as cai')
            ->leftJoin('client_main_information as cmi', function ($join) {
                $join->on('cmi.client_id', '=', 'cai.client_id')->whereNull('cmi.deleted_at');
            })
            ->whereNull('cai.deleted_at')
            ->whereNotNull('cai.modem_sn')
            ->where('cai.modem_sn', '!=', '')
            ->select('cai.client_id', 'cai.modem_sn', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', 'cmi.estado')
            ->orderBy('cai.client_id')
            ->get();

        $resultado = [];
        foreach ($rows as $row) {
            $normalizado = ClienteSearchService::normalizarSn($row->modem_sn);
            if ($this->esHexCanonico($normalizado)) {
                continue; // normaliza correctamente, no es discrepancia de esta categoría
            }

            $resultado[] = [
                'client_id' => $row->client_id,
                'nombre' => $this->nombreCompleto($row),
                'estado' => $row->estado,
                'modem_sn' => $row->modem_sn,
                'modem_sn_normalizado' => $normalizado,
            ];
        }

        return $resultado;
    }

    /**
     * 2) Clientes con modem_sn que SÍ normaliza y que difiere del sn de su
     * ONU real en olt_onus. Un cliente con varias ONUs usa la de menor id
     * (mismo criterio MIN(id) que NormalizarSeriesClientesCommand, evita
     * duplicar por el JOIN).
     */
    public function difiereDeOlt(): array
    {
        $oltPick = DB::table('olt_onus')
            ->select('client_id', DB::raw('MIN(id) as min_id'))
            ->whereNotNull('client_id')
            ->whereNotNull('sn')
            ->where('sn', '!=', '')
            ->groupBy('client_id');

        $rows = DB::table('client_additional_information as cai')
            ->joinSub($oltPick, 'onu_pick', function ($join) {
                $join->on('onu_pick.client_id', '=', 'cai.client_id');
            })
            ->join('olt_onus as onu', 'onu.id', '=', 'onu_pick.min_id')
            ->leftJoin('client_main_information as cmi', function ($join) {
                $join->on('cmi.client_id', '=', 'cai.client_id')->whereNull('cmi.deleted_at');
            })
            ->whereNull('cai.deleted_at')
            ->whereNotNull('cai.modem_sn')
            ->where('cai.modem_sn', '!=', '')
            ->select('cai.client_id', 'cai.modem_sn', 'onu.sn as olt_sn', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', 'cmi.estado')
            ->orderBy('cai.client_id')
            ->get();

        $resultado = [];
        foreach ($rows as $row) {
            $modemNorm = ClienteSearchService::normalizarSn($row->modem_sn);
            if (!$this->esHexCanonico($modemNorm)) {
                continue; // SN manual no confiable, esa discrepancia ya la cubre noNormaliza()
            }

            $oltNorm = ClienteSearchService::normalizarSn($row->olt_sn);
            if ($modemNorm === $oltNorm) {
                continue; // coinciden, no hay discrepancia
            }

            $resultado[] = [
                'client_id' => $row->client_id,
                'nombre' => $this->nombreCompleto($row),
                'estado' => $row->estado,
                'modem_sn' => $row->modem_sn,
                'modem_sn_normalizado' => $modemNorm,
                'olt_sn' => $row->olt_sn,
                'olt_sn_normalizado' => $oltNorm,
            ];
        }

        return $resultado;
    }

    /**
     * 3) ONUs en olt_onus con client_id asignado cuyo cliente NO está Activo
     * (Bloqueado/Inactivo/Cancelado o client_id huérfano sin fila). Es la
     * categoría de mayor valor económico (equipo en la calle sin facturar) —
     * 100% SQL, usa el índice de olt_onus.client_id.
     */
    public function enOltSinClienteActivo(): array
    {
        $rows = DB::table('olt_onus as onu')
            ->leftJoin('client_main_information as cmi', function ($join) {
                $join->on('cmi.client_id', '=', 'onu.client_id')->whereNull('cmi.deleted_at');
            })
            ->whereNotNull('onu.client_id')
            ->where(function ($q) {
                $q->whereNull('cmi.id')
                    ->orWhere('cmi.estado', '!=', ClientMainInformation::STATE_ACTIVE);
            })
            ->select('onu.id as onu_id', 'onu.client_id', 'onu.sn as olt_sn', 'onu.status as onu_status', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', 'cmi.estado')
            ->orderBy('onu.client_id')
            ->get();

        return $rows->map(fn ($row) => [
            'onu_id' => $row->onu_id,
            'client_id' => $row->client_id,
            'nombre' => $this->nombreCompleto($row),
            'estado' => $row->estado, // null = client_id huérfano (sin fila en client_main_information)
            'olt_sn' => $row->olt_sn,
            'onu_status' => $row->onu_status,
        ])->all();
    }

    private function esHexCanonico(?string $valor): bool
    {
        return $valor !== null && strlen($valor) === 16 && ctype_xdigit($valor);
    }

    private function nombreCompleto(object $row): ?string
    {
        if (empty($row->name)) {
            return null;
        }

        return trim(implode(' ', array_filter([$row->name, $row->father_last_name ?? null, $row->mother_last_name ?? null])));
    }
}
