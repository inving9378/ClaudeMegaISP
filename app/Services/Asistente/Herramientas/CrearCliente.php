<?php

namespace App\Services\Asistente\Herramientas;

use App\Http\Controllers\HelperController;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Models\User;
use App\Modules\Core\Clientes\Controllers\ClientController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta un cliente por el MISMO camino que la pantalla Crear cliente (ClientCreateRequest +
 * ClientController::store): mismas reglas, mismos valores por defecto del formulario, y los mismos
 * pasos de después (saldo, recordatorios, facturación, fechas de corte y pago, cuenta del portal).
 * No le agrega servicios: eso sigue en la ficha.
 */
class CrearCliente extends Herramienta
{
    use DatosAlta;

    /** duration_contracts: meses → id. */
    private const PLAZOS = [6 => 1, 12 => 2, 18 => 3, 24 => 4, 36 => 5];

    public function nombre(): string
    {
        return 'crear_cliente';
    }

    public function descripcion(): string
    {
        return 'Da de alta un cliente nuevo (sin servicios; esos se agregan después en su ficha). La pantalla pide: '
            . 'nombre con apellido paterno y materno, CURP (o identificación), correo, colonia (usa buscar_colonia) y plazo '
            . 'del contrato (6, 12, 18, 24 o 36 meses); teléfono y domicilio son opcionales. Llámala con lo que ya tengas '
            . 'aunque falten datos: te responde la lista exacta de lo que falta. '
            . 'Si solo hay datos de contacto, ofrece registrarlo como prospecto. Prepara el alta y el usuario la confirma.';
    }

    public function permiso(): string|array|null
    {
        return 'client_add_client';
    }

    public function esCambio(): bool
    {
        return true;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->propiedadesAlta() + [
                'curp'             => ['type' => 'string', 'description' => 'CURP o número de identificación.'],
                'plazo_meses'      => ['type' => 'integer', 'enum' => array_keys(self::PLAZOS)],
                'costo_activacion' => ['type' => 'number', 'description' => 'Solo si el usuario lo dice. Por defecto $299.'],
            ],
            'required' => ['nombre'],
        ];
    }

    public function preparar(User $user, array $args): array
    {
        ['datos' => $datos, 'faltan' => $faltan, 'colonia' => $colonia] = $this->datosComunes($args);
        $curp = mb_strtoupper(preg_replace('/\s+/', '', self::texto($args, 'curp', 30)));
        $plazo = (int) ($args['plazo_meses'] ?? 0);
        if ($curp === '') {
            $faltan[] = 'CURP o identificación';
        }
        if ($datos['email'] === '') {
            $faltan[] = 'correo';
        }
        if (!isset(self::PLAZOS[$plazo])) {
            $faltan[] = 'plazo del contrato (6, 12, 18, 24 o 36 meses)';
        }
        if ($faltan) {
            return ['error' => 'Para dar de alta al cliente me falta: ' . implode(', ', $faltan) . '.'];
        }

        $costo = isset($args['costo_activacion']) && is_numeric($args['costo_activacion']) ? max(0, (float) $args['costo_activacion']) : 299;
        $datos += [
            'nif_pasaport'         => $curp,
            'duration_contract_id' => self::PLAZOS[$plazo],
            'activation_cost'      => $costo,
        ];

        $prueba = $datos + $this->fijos($user);
        if ($error = Formulario::errores(Formulario::peticion(ClientCreateRequest::class, '/cliente/add', $prueba, $user))) {
            return self::rechazo('clientes', $error, $prueba);
        }

        return [
            'resumen' => "Dar de alta cliente nuevo:\n" . implode("\n", $this->lineasResumen($datos, $colonia))
                . "\n- CURP / identificación: {$curp}\n- Plazo del contrato: {$plazo} meses"
                . "\n- Costo de activación: " . DatosCliente::dinero($costo) . ' (pendiente de pago)'
                . "\n- Estado: Nuevo · Vendedor: " . trim("{$user->name} {$user->father_last_name}")
                . "\n- El usuario y la contraseña del portal se generan solos, como en la pantalla."
                . "\n- Los servicios (internet, etc.) se agregan después en su ficha.",
            'argumentos' => ['datos' => $datos],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $datos = $this->fijos($user) + (array) ($args['datos'] ?? []);
        $req = Formulario::peticion(ClientCreateRequest::class, '/cliente/add', $datos, $user);
        if ($error = Formulario::errores($req)) {
            return ['error' => "La pantalla de clientes ya no lo acepta: {$error}"];
        }

        $cliente = DB::transaction(fn () => Formulario::comoPeticionActual($req, fn () => app(ClientController::class)->store($req)));
        if (!is_object($cliente) || !isset($cliente->id)) {
            return ['error' => 'No se pudo dar de alta al cliente.'];
        }

        return [
            'ok'      => true,
            'mensaje' => "Listo, di de alta al cliente número {$cliente->id}. Para que tenga internet falta agregarle su servicio en la ficha.",
            'enlace'  => $this->enlace('ficha_cliente_servicios', $cliente->id),
            'sujeto'  => $cliente,
        ];
    }

    /**
     * Los valores que el formulario ya trae puestos (field_modules.default_value) y los que genera
     * solo (usuario y contraseña del portal). Se calculan al guardar, no al preparar.
     */
    private function fijos(User $user): array
    {
        return [
            'user'                       => (new HelperController())->getGenerateUserExist(),
            'password'                   => Str::random(8),
            'estado'                     => 'Nuevo',
            'type_of_billing_id'         => 1,
            'ift'                        => 1,
            'discharge_date'             => now()->format('Y-m-d H:i:s'),
            'activation_date'            => now()->format('Y-m-d H:i'),
            'seller_id'                  => $user->id,
            'is_payment_activation_cost' => 0,
            'connection_type'            => 'olt',
            'is_dedicated'               => false,
        ];
    }
}
