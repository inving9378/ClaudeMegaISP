<?php

namespace App\Services;

use App\Models\Referrals\ReferralCommission;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Models\User;

/**
 * UNI-T I5 — ¿este incentivo es aplicable dado otro ya otorgado a la misma persona?
 * Lee config('unificacion.compatibilidad_incentivos'). Interruptor apagado = siempre true (como hoy).
 *
 * Precedencia fija para evitar que dos motores se cancelen entre sí en 'excluyente':
 * vendedor > talento > embajador. El de menor precedencia cede ante el que ya existe;
 * por eso vendedor nunca consulta y solo Talento y Embajadores llaman a aplicable().
 * Solo afecta cálculos nuevos: no reescribe importes ya generados.
 */
class IncentiveCompatibilityService
{
    public const TIPOS = ['vendedor', 'talento', 'embajador'];

    public function activo(): bool
    {
        return (bool) config('unificacion.compatibilidad_incentivos.activo', false);
    }

    public function modo(string $a, string $b): string
    {
        $m = config('unificacion.compatibilidad_incentivos.matriz', []);
        foreach ([$a . '_' . $b, $b . '_' . $a] as $k) {
            if (isset($m[$k])) {
                return (string) $m[$k];
            }
        }
        return 'compatible';
    }

    /** @param string[] $otorgados tipos de incentivo ya otorgados a la misma persona */
    public function aplicable(string $incentivo, array $otorgados, ?string $campana = null): bool
    {
        if (!$this->activo()) {
            return true;
        }
        foreach (array_unique($otorgados) as $otro) {
            if ($otro === $incentivo) {
                continue;
            }
            $modo = $this->modo($incentivo, $otro);
            if ($modo === 'excluyente') {
                return false;
            }
            if ($modo === 'por_campana'
                && !($campana !== null && in_array($campana, (array) config('unificacion.compatibilidad_incentivos.campanas', []), true))) {
                return false;
            }
        }
        return true;
    }

    /** Incentivos de precedencia superior que la persona ya tiene en la ventana [from,to] (Y-m-d). */
    public function otorgadosPrevios(string $incentivo, ?int $userId, string $from, string $to): array
    {
        if (!$this->activo() || !$userId) {
            return [];
        }
        $out = [];
        if ($incentivo !== 'vendedor'
            && ClientMainInformation::where('seller_id', $userId)
                ->whereBetween('activation_date', [$from, $to . ' 23:59:59'])->exists()) {
            $out[] = 'vendedor';
        }
        if ($incentivo === 'embajador') {
            $col = TalentoColaborador::where('user_id', $userId)->value('id');
            if ($col && TalentoLedgerEntry::where('colaborador_id', $col)->where('type', 'credit')
                ->where('concept', 'overproduction')->whereBetween('period_start', [$from, $to])->exists()) {
                $out[] = 'talento';
            }
        }
        return $out;
    }

    public function userIdDeCliente(int $clientId): ?int
    {
        $login = ClientMainInformation::where('client_id', $clientId)->value('user');
        return $login ? User::where('login_user', $login)->value('id') : null;
    }
}
