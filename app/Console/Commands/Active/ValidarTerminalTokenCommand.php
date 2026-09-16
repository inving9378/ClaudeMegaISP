<?php

namespace App\Console\Commands\Active;

use App\Services\TerminalIdentityTokenService;
use Illuminate\Console\Command;

/**
 * Fase 1 ttyd-por-usuario (item #9991177): valida el token HMAC de identidad
 * de terminal que ~/.bashrc leerá desde la env var `TTYD_USER` (confirmada
 * empíricamente el 2026-09-16 — ttyd 1.7.7 siempre expone ese nombre fijo al
 * proceso hijo cuando corre con `-H`, sin importar el nombre de header pasado
 * a la bandera) una vez que Irving active el paso systemd del runbook
 * (deploy/README-terminal-por-usuario.md).
 *
 * Sin guard web: se invoca desde bash al abrir la shell, sin sesión HTTP.
 */
class ValidarTerminalTokenCommand extends Command
{
    protected $signature = 'terminal:validar-token {token : Token emitido por DevToolsController (cookie megaisp_term_token)}';

    protected $description = 'Valida el token HMAC de identidad de terminal (Fase 1 ttyd por usuario) e imprime el login_user si es válido';

    public function handle(TerminalIdentityTokenService $service): int
    {
        $loginUser = $service->validate((string) $this->argument('token'));

        if ($loginUser === null) {
            $this->error('Token inválido o expirado.');

            return self::FAILURE;
        }

        $this->line($loginUser);

        return self::SUCCESS;
    }
}
