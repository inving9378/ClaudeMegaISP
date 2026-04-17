<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateIpRedirect implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$value) return true;
        return $this->validateIp($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existen IP con el formato incorrecto.';
    }

    public function validateIp($ip)
    {
        // Verificar si la cadena contiene un número de puerto
        $hasPort = strpos($ip, ':') !== false;

        // Si no hay número de puerto y la dirección IP tiene el formato válido, retornar true
        if (!$hasPort && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        // Analizar la URL en busca de componentes como el host y el puerto
        $urlComponents = parse_url($ip);

        // Verificar si el componente "host" está presente
        if (!isset($urlComponents['host'])) {
            return false;
        }

        // Asignar el valor del componente "host" a una variable
        $ipHost = $urlComponents['host'];

        // Verificar si el valor del componente "host" es una dirección IP válida
        if (filter_var($ipHost, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // Verificar si el componente "port" está presente
        if (isset($urlComponents['port'])) {
            // Obtener el número de puerto
            $port = $urlComponents['port'];

            // Verificar si el número de puerto es un valor numérico y está en el rango válido
            if (!is_numeric($port) || $port < 1 || $port > 65535) {
                return false;
            }
        }

        // Si la dirección IP y el número de puerto (si está presente) pasan todas las validaciones, retornar true
        return true;
    }
}
