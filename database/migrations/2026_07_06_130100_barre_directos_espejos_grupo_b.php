<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * FASE 2.5 — PASO 2 (Grupo B): barre TODOS los permisos directos de las cuentas
 * ESPEJO de cliente (395 en dev). Una cuenta espejo pura no debe tener permisos
 * directos: su acceso viene del rol 'client' limpio (Fase 2). Incluye por
 * idempotencia a las de Grupo A (ya vaciadas por el Paso 1 = no-op de directos
 * válidos) y limpia sus filas HUÉRFANAS.
 *
 * SELF-CONTAINED y PORTABLE a prod: pre-filtra los objetivos por SQL (no por
 * ids/logins hardcodeados) para no iterar todo el universo de clientes.
 *
 * Selector espejo-contaminado:
 *   rol 'client' + login_user ∈ client_main_information.user + is_seller=0
 *   + login_user ~ ^Meganet[0-9a-f]{8}$ (RAIL) + (tiene directos O rol de staff).
 * RAIL por cuenta antes de tocarla; si no cumple las tres → SKIP + log.
 * Pre-flight: si los objetivos superan 450 → ABORTA (protege contra que el
 * selector se ensanche y toque cuentas reales).
 *
 * Idempotente y re-ejecutable. down() no-op: el saneo queda aplicado.
 */
return new class extends Migration
{
    private const ESPEJO_PATTERN = '/^Meganet[0-9a-f]{8}$/';
    private const ESPEJO_SQL_REGEXP = '^Meganet[0-9a-f]{8}$';
    private const ABORT_THRESHOLD = 450;

    public function up(): void
    {
        $clientRole = Role::where('name', 'client')->where('guard_name', 'web')->first();
        if (! $clientRole) {
            return;
        }

        // Pre-filtro por SQL: candidatos espejo (rol client + login∈CMI + is_seller=0 + patrón).
        $candidateIds = DB::table('model_has_roles as mrc')
            ->join('users as u', 'u.id', '=', 'mrc.model_id')
            ->join('client_main_information as cmi', 'cmi.user', '=', 'u.login_user')
            ->where('mrc.role_id', $clientRole->id)
            ->where('u.is_seller', 0)
            ->where('u.login_user', 'REGEXP', self::ESPEJO_SQL_REGEXP)
            ->distinct()
            ->pluck('u.id');

        // Sólo los que tienen algo que limpiar (directos o rol de staff).
        $targetIds = [];
        foreach ($candidateIds as $uid) {
            $hasDirect = DB::table('model_has_permissions')->where('model_id', $uid)->exists();
            $hasStaffRole = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->where('mr.model_id', $uid)->where('r.name', '!=', 'client')->exists();
            if ($hasDirect || $hasStaffRole) {
                $targetIds[] = $uid;
            }
        }

        // Pre-flight: si el selector se ensanchó, ABORTAR sin tocar nada.
        if (count($targetIds) > self::ABORT_THRESHOLD) {
            throw new \RuntimeException(
                'Fase 2.5 Grupo B ABORTADO: ' . count($targetIds) . ' objetivos (>'
                . self::ABORT_THRESHOLD . '). El selector se ensanchó — revisar antes de ejecutar.'
            );
        }

        $cuentas = 0;
        $directosBarridos = 0;
        $confirmedTargetIds = [];

        foreach (array_chunk($targetIds, 50) as $chunk) {
            foreach ($chunk as $uid) {
                $u = User::find($uid);
                if (! $u) {
                    continue;
                }
                // RAIL de seguridad por cuenta.
                if (! preg_match(self::ESPEJO_PATTERN, $u->login_user)
                    || (int) $u->is_seller !== 0
                    || ! $u->hasRole('client')) {
                    Log::warning('Fase 2.5 Grupo B: SKIP (no cumple rail espejo)', [
                        'login_user' => $u->login_user,
                    ]);
                    continue;
                }

                $directNames = $u->getDirectPermissions()->pluck('name')->all();
                if (! empty($directNames)) {
                    $u->revokePermissionTo($directNames);
                    $directosBarridos += count($directNames);
                }
                $confirmedTargetIds[] = $uid;
                $cuentas++;
            }
        }

        // Limpieza de filas HUÉRFANAS (permission_id de permisos ya borrados) SOLO de los
        // espejos confirmados — getDirectPermissions()/revokePermissionTo no las alcanzan.
        $huerfanos = 0;
        if (! empty($confirmedTargetIds)) {
            $huerfanos = DB::table('model_has_permissions')
                ->whereIn('model_id', $confirmedTargetIds)
                ->whereNotIn('permission_id', function ($q) {
                    $q->select('id')->from('permissions');
                })
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('Fase 2.5 Grupo B: barrido de directos completo', [
            'cuentas'             => $cuentas,
            'directos_barridos'   => $directosBarridos,
            'huerfanos_eliminados' => $huerfanos,
        ]);
    }

    public function down(): void
    {
        // No-op a propósito: el saneo de seguridad se queda aplicado.
    }
};
