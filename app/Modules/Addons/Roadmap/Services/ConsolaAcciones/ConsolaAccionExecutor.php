<?php

namespace App\Modules\Addons\Roadmap\Services\ConsolaAcciones;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Item #876 — el ejecutor central. Ningún botón de la consola habla directo con su servicio:
 * todos pasan por aquí. Valida el permiso (MISMA semántica que `Controller::authorize()` —
 * `Gate::forUser()->authorize()` es lo que esa llamada usa por dentro, así que un botón migrado
 * rechaza exactamente igual que antes), aplica la confirmación, ejecuta, registra en
 * `consola_acciones_ejecuciones` (regla 2: sin excepciones) y devuelve lo que el handler produjo.
 *
 * El handler recibe solo la lógica de "hacer la acción" — la resolución de datos (ej. 404 si el
 * item no existe) queda ANTES, en el controller, igual que en el resto del archivo. Así una acción
 * migrada no cambia su contrato de respuesta ni sus casos de error existentes.
 */
class ConsolaAccionExecutor
{
    /**
     * @param  \Closure(): array  $handler  Debe devolver el array que el controller responderá tal cual.
     * @param  array<string,mixed>  $payload  Se guarda en el registro de auditoría (no viaja al handler).
     */
    public function ejecutar(
        ConsolaAccion $accion,
        $user,
        \Closure $handler,
        array $payload = [],
        ?string $confirmacionTexto = null
    ): array {
        Gate::forUser($user)->authorize($accion->permiso);

        if ($accion->confirma === ConsolaAccion::CONFIRMA_SI_CON_TEXTO) {
            $esperado = trim($accion->confirmacionEsperada());
            if (trim((string) $confirmacionTexto) !== $esperado) {
                throw ValidationException::withMessages([
                    'confirmacion' => ["Escribe exactamente \"{$esperado}\" para confirmar."],
                ]);
            }
        }

        try {
            $resultado = $handler();
            $this->registrar($accion, $user, true, null, $payload);

            return $resultado;
        } catch (\Throwable $e) {
            $this->registrar($accion, $user, false, $e->getMessage(), $payload);

            throw $e;
        }
    }

    private function registrar(ConsolaAccion $accion, $user, bool $ok, ?string $mensaje, array $payload): void
    {
        try {
            DB::table('consola_acciones_ejecuciones')->insert([
                'clave'      => $accion->clave,
                'actor'      => $this->actorLabel($user),
                'payload'    => empty($payload) ? null : json_encode($payload),
                'ok'         => $ok,
                'mensaje'    => $mensaje,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // El registro NUNCA tumba la acción que ya se ejecutó (regla del resto del módulo,
            // ver `vigilarProcesosProgramados()`). Se pierde el rastro de ESTA fila, no la acción.
            \Illuminate\Support\Facades\Log::error('ConsolaAccionExecutor: no se pudo registrar la ejecución.', [
                'clave' => $accion->clave, 'excepcion' => $e->getMessage(),
            ]);
        }
    }

    private function actorLabel($user): string
    {
        if (! $user) {
            return 'desconocido';
        }

        return 'irving:' . ($user->login_user ?? $user->email ?? $user->id ?? '?');
    }
}
