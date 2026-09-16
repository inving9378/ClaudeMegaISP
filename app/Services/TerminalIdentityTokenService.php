<?php

namespace App\Services;

/**
 * Fase 1 ttyd-por-usuario (item #9991177, ver deploy/README-terminal-por-usuario.md).
 *
 * Emite y valida el token de identidad de terminal: HMAC-SHA256 sobre
 * "{login_user}|{expiry}" firmado con APP_KEY, TTL corto (60s — solo necesita
 * sobrevivir el viaje cookie→ttyd→env→bashrc, no una sesión completa).
 *
 * Punto único de verdad: DevToolsController::terminalToken()/index() lo emiten,
 * el comando `terminal:validar-token` (invocado desde ~/.bashrc una vez activado
 * el paso systemd del runbook) lo valida. Ninguno de los dos reimplementa el HMAC.
 */
class TerminalIdentityTokenService
{
    public const TTL_SECONDS = 60;

    public function issue(string $loginUser): string
    {
        $expiry = now()->addSeconds(self::TTL_SECONDS)->timestamp;

        return $this->sign($loginUser, $expiry);
    }

    /**
     * Devuelve el login_user si el token es válido y no expiró, o null.
     */
    public function validate(string $token): ?string
    {
        $parts = explode('|', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$loginUser, $expiry, $signature] = $parts;
        if ($loginUser === '' || ! ctype_digit($expiry)) {
            return null;
        }

        if ((int) $expiry < now()->timestamp) {
            return null;
        }

        $expected = hash_hmac('sha256', $loginUser . '|' . $expiry, (string) config('app.key'));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return $loginUser;
    }

    private function sign(string $loginUser, int $expiry): string
    {
        $payload = $loginUser . '|' . $expiry;
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        return $payload . '|' . $signature;
    }
}
