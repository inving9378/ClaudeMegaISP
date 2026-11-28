<?php

namespace App\Observers;

use App\Models\User;
use App\Modules\Addons\VoIP\Services\LiberadorExtensiones;

/**
 * La extensión de VoIP sigue al usuario. Desde el 28-nov (pedido de Irving): si el usuario se bloquea,
 * se desactiva o se elimina, su extensión queda libre para asignársela a otra persona (antes se quedaba
 * suya: desactivado se quitaba de Asterisk y bloqueado se restringía, esperando a que volviera).
 * Reactivarlo no le devuelve la extensión: se le asigna la que toque en VoIP → Extensiones.
 */
class UserVoipObserver
{
    public function __construct(private LiberadorExtensiones $liberador) {}

    public function updated(User $user): void
    {
        if (! $user->wasChanged('estado')) {
            return;
        }

        $estado = $user->estado ?? 'activo';
        if ($estado !== 'activo') {
            $this->liberador->liberarDe($user, "usuario {$estado}");
        }
    }

    /** @var array<int, array<int, int>> extensiones del usuario que se está borrando, por id de usuario */
    private static array $alBorrar = [];

    /**
     * Al borrar: aquí solo se anotan sus extensiones y se liberan en deleted(), ya borrado. Si el borrado
     * falla (otra tabla lo usa), la extensión se queda con su dueño. La llave foránea le quita el dueño sola;
     * lo demás (quitarla de Asterisk, contraseña nueva del teléfono web) lo hace el liberador.
     */
    public function deleting(User $user): void
    {
        self::$alBorrar[$user->id] = $this->liberador->idsDe($user);
    }

    public function deleted(User $user): void
    {
        $ids = self::$alBorrar[$user->id] ?? [];
        unset(self::$alBorrar[$user->id]);
        $this->liberador->liberarIds($ids, 'usuario eliminado');
    }
}
