<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Libera las extensiones de quien deja de estar activo (28-nov, pedido de Irving): si el usuario se
 * bloquea, se desactiva o se elimina, su extensión queda libre para asignársela a otra persona.
 *
 * Liberar es:
 *  - Quitarla de Asterisk: ni quien se fue ni nadie la usa mientras no tenga dueño. Al asignarla a otra
 *    persona (editar en VoIP → Extensiones) se vuelve a dar de alta sola.
 *  - Quitarle el dueño y regresarla al contexto normal (la de un bloqueado quedaba restringida).
 *  - El teléfono web, con contraseña nueva: la anterior pudo quedarse en el navegador de quien se fue.
 *    La del teléfono físico no se cambia: vive en el aparato, que se queda en su lugar.
 *
 * Si Asterisk no deja quitarla, se queda asignada (no queda una extensión «libre» que aún funciona con
 * la contraseña de quien se fue); `voip:reconciliar-estados` lo vuelve a intentar cada hora.
 */
class LiberadorExtensiones
{
    public const CONTEXTO_NORMAL = 'from-internal';
    /** El que le pone el bloqueo (AsteriskProvisioningService::restringirExtension): sin salientes. */
    public const CONTEXTO_RESTRINGIDO = 'from-internal-restringido';

    public function __construct(private AsteriskProvisioningService $provisioner) {}

    /** @return array<int, int> */
    public function idsDe(User|int $usuario): array
    {
        return Extension::where('user_id', $usuario instanceof User ? $usuario->id : $usuario)->pluck('id')->all();
    }

    /**
     * @return array<int, string> los números que quedaron libres
     */
    public function liberarDe(User|int $usuario, string $motivo): array
    {
        return $this->liberarIds($this->idsDe($usuario), $motivo);
    }

    /**
     * Por id: al borrar un usuario, la llave foránea ya le quitó el dueño a la extensión.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string> los números que quedaron libres
     */
    public function liberarIds(array $ids, string $motivo): array
    {
        $libres = [];

        foreach (Extension::whereIn('id', $ids)->orderBy('numero')->get() as $ext) {
            try {
                $this->liberar($ext, $motivo);
                $libres[] = $ext->numero;
            } catch (\Throwable $e) {
                Log::error("VoIP: no se pudo liberar la ext {$ext->numero} ({$motivo}): {$e->getMessage()}. "
                    . 'Sigue asignada; voip:reconciliar-estados lo reintenta.');
            }
        }

        return $libres;
    }

    public function liberar(Extension $ext, string $motivo): void
    {
        $antes = $ext->user_id;

        try {
            $this->provisioner->desprovisionarExtension($ext);
        } catch (\Throwable $e) {
            // Si estaba dada de alta y no se pudo quitar, no se libera: seguiría sonando con la contraseña de antes.
            if ($ext->estaProvisionada()) {
                throw $e;
            }
        }

        $cambios = ['user_id' => null];
        // Solo el restringido del bloqueo: otro contexto lo puso alguien a propósito y se respeta.
        if ($ext->contexto === self::CONTEXTO_RESTRINGIDO) {
            $cambios['contexto'] = self::CONTEXTO_NORMAL;
        }
        if ($ext->es_webrtc) {
            $cambios['secret'] = Str::random(32);
        }
        $ext->update($cambios);

        Log::info("VoIP: ext {$ext->numero} liberada (era del usuario {$antes}; {$motivo}).");
    }
}
