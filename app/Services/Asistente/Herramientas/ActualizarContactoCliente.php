<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Modules\Core\Clientes\Models\ClientMainInformation;

/**
 * Cambia teléfonos o correo de un cliente. Pasa por el modelo (mismo camino que la ficha): se
 * disparan sus observers y la bitácora de cambios queda a nombre del usuario.
 */
class ActualizarContactoCliente extends Herramienta
{
    private const CAMPOS = ['telefono' => 'phone', 'telefono2' => 'phone2', 'telefono3' => 'phone3', 'correo' => 'email'];

    private const ETIQUETAS = ['phone' => 'Teléfono', 'phone2' => 'Teléfono 2', 'phone3' => 'Teléfono 3', 'email' => 'Correo'];

    public function nombre(): string
    {
        return 'actualizar_contacto_cliente';
    }

    public function descripcion(): string
    {
        return 'Cambia el teléfono (principal, 2 o 3) o el correo de un cliente. Prepara el cambio y el usuario lo confirma.';
    }

    public function permiso(): string|array|null
    {
        return 'client_edit_client';
    }

    public function esCambio(): bool
    {
        return true;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'numero_cliente' => ['type' => 'integer'],
                'telefono'       => ['type' => 'string', 'description' => 'Teléfono principal nuevo (10 dígitos)'],
                'telefono2'      => ['type' => 'string'],
                'telefono3'      => ['type' => 'string'],
                'correo'         => ['type' => 'string'],
            ],
            'required' => ['numero_cliente'],
        ];
    }

    public function preparar(User $user, array $args): array
    {
        $numero = $this->entero($args, 'numero_cliente');
        $cmi = $numero ? ClientMainInformation::where('client_id', $numero)->first() : null;
        if (!$cmi) {
            return ['error' => "No encontré al cliente número {$numero}."];
        }

        $nuevos = [];
        $lineas = [];
        foreach (self::CAMPOS as $arg => $columna) {
            if (!array_key_exists($arg, $args) || $args[$arg] === null || $args[$arg] === '') {
                continue;
            }
            $valor = trim((string) $args[$arg]);
            if ($columna === 'email') {
                if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    return ['error' => "El correo «{$valor}» no parece válido."];
                }
            } else {
                $valor = preg_replace('/\D/', '', $valor);
                if (strlen($valor) < 10 || strlen($valor) > 13) {
                    return ['error' => 'El teléfono debe tener 10 dígitos.'];
                }
            }
            if ((string) $cmi->{$columna} === $valor) {
                continue;
            }
            $nuevos[$columna] = $valor;
            $antes = $cmi->{$columna} ?: 'vacío';
            $lineas[] = self::ETIQUETAS[$columna] . ": {$antes} → {$valor}";
        }

        if (!$nuevos) {
            return ['error' => 'No hay nada que cambiar: los datos ya son esos o no me dijiste qué cambiar.'];
        }

        $nombre = trim("{$cmi->name} {$cmi->father_last_name} {$cmi->mother_last_name}");

        return [
            'resumen'    => "Actualizar contacto de {$nombre} (núm. {$numero}):\n- " . implode("\n- ", $lineas),
            'argumentos' => ['numero_cliente' => $numero, 'cambios' => $nuevos],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $numero = (int) ($args['numero_cliente'] ?? 0);
        $cambios = array_intersect_key((array) ($args['cambios'] ?? []), array_flip(self::CAMPOS));
        $cmi = ClientMainInformation::where('client_id', $numero)->first();
        if (!$cmi || !$cambios) {
            return ['error' => 'No se pudo aplicar: el cliente ya no existe o no hay cambios.'];
        }

        $cmi->fill($cambios)->save();

        return [
            'ok'      => true,
            'mensaje' => 'Listo, actualicé los datos de contacto del cliente número ' . $numero . '.',
            'enlace'  => $this->enlace('ficha_cliente', $numero),
            'sujeto'  => $cmi,
        ];
    }
}
