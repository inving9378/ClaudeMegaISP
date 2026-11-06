<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use App\Modules\Core\Auth\Controllers\LoginController;
use Illuminate\Console\Command;

/**
 * Reconteo de contraseñas legacy recuperables (base64) — item Roadmap #179.
 *
 * "Privilegiada" aquí = tiene al menos uno de los roles de
 * `LoginController::STAFF_ROLES`, porque esa es la ÚNICA puerta real hacia el
 * panel admin (`LoginController::attemptLogin` bloquea a cualquier cuenta sin
 * esos roles después de validar el password, sin importar su formato). Las
 * cuentas espejo con rol 'client' o sin rol NO pueden entrar al admin aunque
 * su password siga en base64.
 *
 * `LoginController::attemptLogin` ya re-hashea a bcrypt en cada login exitoso
 * (upgrade-on-login), así que una cuenta privilegiada activa se auto-limpia
 * sola con el uso normal. Este comando es solo lectura: existe para vigilar
 * que no queden privilegiadas dormidas sin loguearse, no para tocar nada.
 */
class AuditLegacyPasswordsCommand extends Command
{
    protected $signature = 'auth:audit-legacy-passwords';

    protected $description = 'Reconteo de cuentas con password legacy base64 (privilegiadas vs no); exit 1 si queda alguna privilegiada';

    public function handle(): int
    {
        $legacy = User::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->where('password', 'not like', '$2y$%');

        $privileged = (clone $legacy)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', LoginController::STAFF_ROLES))
            ->get(['id', 'login_user', 'estado']);

        $clientOnly = (clone $legacy)
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', LoginController::STAFF_ROLES))
            ->count();

        $noRole = (clone $legacy)->whereDoesntHave('roles')->count();

        $this->table(
            ['Privilegiadas (entran al admin)', "Rol 'client' (bloqueadas en admin)", 'Sin rol (bloqueadas en admin)'],
            [[$privileged->count(), $clientOnly, $noRole]]
        );

        if ($privileged->isEmpty()) {
            $this->info("✔ 0 cuentas privilegiadas con password legacy. Ninguna cuenta que pueda entrar al panel admin guarda contraseña recuperable en base64.");

            return self::SUCCESS;
        }

        $this->error('✘ ' . $privileged->count() . ' cuenta(s) PRIVILEGIADA(S) siguen con password legacy recuperable:');
        foreach ($privileged as $u) {
            $this->line("  #{$u->id}\t{$u->login_user}\t{$u->estado}");
        }
        $this->warn('Se auto-migran a bcrypt en su próximo login exitoso. Si alguna lleva tiempo inactiva, coordinar el reset con Irving (ver item Roadmap #179).');

        return self::FAILURE;
    }
}
