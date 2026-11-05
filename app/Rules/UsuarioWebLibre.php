<?php

namespace App\Rules;

use App\Models\User;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use Illuminate\Contracts\Validation\Rule;

/**
 * El Usuario WEB de una ficha no puede ser el usuario de entrada de una cuenta ajena: de
 * personal, del sistema u otro cliente (2026-10-23). La app MegaFamilia y el portal encuentran la
 * cuenta del cliente por ese usuario. En el alta, sin número de cliente todavía, cualquier cuenta
 * con ese usuario es ajena. Con $usuarioActual solo se revisa si cambia, para no frenar el guardado
 * de una ficha que ya esté así.
 */
class UsuarioWebLibre implements Rule
{
    protected $clientId;
    protected $usuarioActual;

    public function __construct(?int $clientId = null, ?string $usuarioActual = null)
    {
        $this->clientId = $clientId;
        $this->usuarioActual = $usuarioActual;
    }

    public function passes($attribute, $value)
    {
        if (blank($value)) {
            return true;
        }
        $usuarioWeb = (string) ClientMainInformation::normalizeUserNumber($value);

        return $usuarioWeb === $this->usuarioActual || ! static::esDeOtraCuenta($usuarioWeb, $this->clientId);
    }

    public function message()
    {
        return 'Ese Usuario WEB ya lo usa otra cuenta; elige otro.';
    }

    public static function esDeOtraCuenta(string $usuarioWeb, ?int $clientId): bool
    {
        return User::where('login_user', $usuarioWeb)->get()
            ->contains(fn (User $cuenta) => $clientId === null || ! $cuenta->esCuentaDelCliente($clientId));
    }
}
