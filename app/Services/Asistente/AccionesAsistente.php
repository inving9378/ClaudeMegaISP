<?php

namespace App\Services\Asistente;

use App\Models\Asistente\Accion;
use App\Models\Asistente\Conversacion;
use App\Models\User;
use App\Services\Asistente\Herramientas\Herramienta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Cambios de JARVIS. La IA nunca escribe directo: la herramienta prepara el cambio, queda una
 * acción pendiente y SOLO el usuario la confirma (botón o un "sí" que valida el servidor, no la IA).
 * Al ejecutarse se revisa de nuevo el permiso y queda en la bitácora a nombre del usuario,
 * marcado "vía JARVIS".
 */
class AccionesAsistente
{
    public function __construct(
        private ConocimientoAsistente $conocimiento,
        private RegistroHerramientas $registro,
    ) {
    }

    public function pendienteDe(User $user, ?Conversacion $conversacion = null): ?Accion
    {
        $accion = Accion::where('user_id', $user->id)
            ->where('estado', Accion::PENDIENTE)
            ->when($conversacion, fn ($q) => $q->where('conversacion_id', $conversacion->id))
            ->latest('id')->first();

        if ($accion && !$accion->vigente()) {
            $accion->update(['estado' => Accion::EXPIRADA, 'resuelta_at' => now()]);

            return null;
        }

        return $accion;
    }

    /** Valida y deja el cambio esperando confirmación. Lo que devuelve es lo que ve la IA. */
    public function preparar(User $user, Herramienta $herramienta, array $args, ?Conversacion $conversacion): array
    {
        $p = $herramienta->preparar($user, $args);
        if (isset($p['error'])) {
            return $p;
        }

        // Una sola acción pendiente a la vez: la anterior se cancela.
        Accion::where('user_id', $user->id)->where('estado', Accion::PENDIENTE)
            ->update(['estado' => Accion::CANCELADA, 'resuelta_at' => now()]);

        $accion = Accion::create([
            'user_id'         => $user->id,
            'conversacion_id' => $conversacion?->id,
            'herramienta'     => $herramienta->nombre(),
            'argumentos'      => $p['argumentos'],
            'resumen'         => $p['resumen'],
            'estado'          => Accion::PENDIENTE,
            'expira_at'       => now()->addMinutes($this->conocimiento->limite('minutos_para_confirmar', 15)),
        ]);

        return [
            'estado'      => 'pendiente_confirmacion',
            'accion_id'   => $accion->id,
            'resumen'     => $p['resumen'],
            'instruccion' => 'El cambio NO se ha hecho. Dile al usuario en una línea qué se va a hacer y que lo confirme con el botón o respondiendo "sí".',
        ];
    }

    /** Para cambios que limites.json marca como 'permitida': se aplican sin pedir confirmación. */
    public function ejecutarDirecto(User $user, Herramienta $herramienta, array $args, ?Conversacion $conversacion): array
    {
        $p = $herramienta->preparar($user, $args);
        if (isset($p['error'])) {
            return $p;
        }

        return $this->aplicar($user, $herramienta, $p['argumentos'], $p['resumen']);
    }

    public function confirmar(User $user, Accion $accion): array
    {
        if ((int) $accion->user_id !== (int) $user->id) {
            return ['error' => 'Esa acción no es tuya.'];
        }
        if (!$accion->vigente()) {
            if ($accion->estado === Accion::PENDIENTE) {
                $accion->update(['estado' => Accion::EXPIRADA, 'resuelta_at' => now()]);
            }

            return ['error' => 'Esa acción ya no está disponible (se confirmó, se canceló o pasó el tiempo). Pídemela otra vez.'];
        }

        $herramienta = $this->registro->obtener($accion->herramienta);
        if (!$herramienta
            || $this->conocimiento->modoDe($herramienta->nombre(), true) === 'bloqueada'
            || !PermisosAsistente::puede($user, $herramienta->permiso())) {
            $accion->update(['estado' => Accion::FALLIDA, 'resuelta_at' => now(), 'resultado' => ['error' => 'sin permiso']]);

            return ['error' => 'Tu usuario ya no tiene permiso para hacer este cambio.'];
        }

        // Se marca antes de ejecutar: un doble clic no puede aplicar el cambio dos veces.
        $tomada = Accion::where('id', $accion->id)->where('estado', Accion::PENDIENTE)
            ->update(['estado' => Accion::EJECUTADA, 'resuelta_at' => now()]);
        if (!$tomada) {
            return ['error' => 'Esa acción ya se estaba procesando.'];
        }

        $r = $this->aplicar($user, $herramienta, (array) $accion->argumentos, $accion->resumen);
        $accion->update([
            'estado'    => isset($r['error']) ? Accion::FALLIDA : Accion::EJECUTADA,
            'resultado' => $r,
        ]);

        return $r;
    }

    public function cancelar(User $user, Accion $accion): bool
    {
        if ((int) $accion->user_id !== (int) $user->id || $accion->estado !== Accion::PENDIENTE) {
            return false;
        }
        $accion->update(['estado' => Accion::CANCELADA, 'resuelta_at' => now()]);

        return true;
    }

    private function aplicar(User $user, Herramienta $herramienta, array $args, string $resumen): array
    {
        try {
            $r = $herramienta->ejecutar($user, $args);
        } catch (\Throwable $e) {
            Log::error('[JARVIS] falló un cambio', ['herramienta' => $herramienta->nombre(), 'user' => $user->id, 'error' => $e->getMessage()]);

            return ['error' => 'No pude aplicar el cambio. No se modificó nada; inténtalo desde la pantalla o más tarde.'];
        }

        $sujeto = $r['sujeto'] ?? null;
        unset($r['sujeto']);

        if (!isset($r['error'])) {
            try {
                $log = activity('jarvis')->causedBy($user)->withProperties([
                    'via'         => 'JARVIS',
                    'herramienta' => $herramienta->nombre(),
                    'resumen'     => $resumen,
                ]);
                if ($sujeto instanceof Model) {
                    $log->performedOn($sujeto);
                }
                $log->log('Vía JARVIS: ' . strtok($resumen, "\n"));
            } catch (\Throwable $e) {
                Log::warning('[JARVIS] no se pudo escribir la bitácora', ['error' => $e->getMessage()]);
            }
        }

        return $r;
    }
}
