<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * FASE 2.5 — PASO 1 (Grupo A): sanea las cuentas ESPEJO de cliente que además
 * portan algún rol de STAFF (10 en dev; una triple-admin y tres con cientos de
 * permisos directos). Les quita TODOS los roles de staff (deja solo 'client') y
 * barre TODOS sus permisos directos. Su acceso legítimo viene del rol 'client'
 * limpio (Fase 2).
 *
 * SELF-CONTAINED y PORTABLE a prod: descubre los objetivos por SELECTOR (no por
 * ids/logins hardcodeados, que difieren entre entornos). Orden: mega-admins
 * primero (más permisos directos primero).
 *
 * Selector espejo-contaminado con-rol:
 *   rol 'client' + login_user ∈ client_main_information.user + is_seller=0 + tiene rol de staff.
 * RAIL de seguridad por cuenta (obligatorio): login_user matchea ^Meganet[0-9a-f]{8}$
 *   Y is_seller=0 Y hasRole('client'). Si no cumple las tres → SKIP + log.
 * Pre-flight: si el Grupo A supera 50 cuentas, el selector se ensanchó → ABORTA.
 *
 * Idempotente (hasRole/getDirectPermissions reflejan el estado actual) y
 * re-ejecutable. down() no-op: el saneo queda aplicado.
 */
return new class extends Migration
{
    private const ESPEJO_PATTERN = '/^Meganet[0-9a-f]{8}$/';

    public function up(): void
    {
        $clientRole = Role::where('name', 'client')->where('guard_name', 'web')->first();
        if (! $clientRole) {
            return;
        }

        $cmiUsers = DB::table('client_main_information')->whereNotNull('user')
            ->pluck('user')->unique()->flip();

        $clientUserIds = DB::table('model_has_roles')->where('role_id', $clientRole->id)
            ->pluck('model_id')->unique();

        // Descubrir Grupo A por selector.
        $targets = [];
        foreach ($clientUserIds as $uid) {
            $u = User::find($uid);
            if (! $u) {
                continue;
            }
            if (! isset($cmiUsers[$u->login_user])) {
                continue;
            }
            if ((int) $u->is_seller !== 0) {
                continue;
            }
            $staffRoles = $u->roles->pluck('name')->reject(fn ($n) => $n === 'client')->values();
            if ($staffRoles->isEmpty()) {
                continue;
            }
            $targets[] = [
                'user'        => $u,
                'staff'       => $staffRoles->all(),
                'directCount' => $u->getDirectPermissions()->count(),
            ];
        }

        // Pre-flight: Grupo A esperado ~10; si es enorme el selector se ensanchó.
        if (count($targets) > 50) {
            throw new \RuntimeException(
                'Fase 2.5 Grupo A ABORTADO: ' . count($targets) . ' objetivos (>50). '
                . 'El selector se ensanchó — revisar antes de ejecutar.'
            );
        }

        // Mega-admins primero (más directos primero).
        usort($targets, fn ($a, $b) => $b['directCount'] <=> $a['directCount']);

        foreach ($targets as $t) {
            /** @var User $u */
            $u = $t['user'];

            // RAIL de seguridad: las tres condiciones o SKIP.
            if (! preg_match(self::ESPEJO_PATTERN, $u->login_user)
                || (int) $u->is_seller !== 0
                || ! $u->hasRole('client')) {
                Log::warning('Fase 2.5 Grupo A: SKIP (no cumple rail espejo)', [
                    'login_user' => $u->login_user,
                ]);
                continue;
            }

            $rolesQuitados = [];
            foreach ($t['staff'] as $roleName) {
                if ($u->hasRole($roleName)) {
                    $u->removeRole($roleName);
                    $rolesQuitados[] = $roleName;
                }
            }

            $directNames = $u->getDirectPermissions()->pluck('name')->all();
            if (! empty($directNames)) {
                $u->revokePermissionTo($directNames);
            }

            Log::info('Fase 2.5 Grupo A: espejo saneado', [
                'login_user'        => $u->login_user,
                'roles_quitados'    => $rolesQuitados,
                'directos_barridos' => count($directNames),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito: el saneo de seguridad se queda aplicado.
    }
};
