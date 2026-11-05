<?php

namespace App\Services\Metricas;

use PEAR2\Net\RouterOS\DataFlowException;
use Throwable;

/** Las fallas de «Probar lectura» con su causa probable, en palabras sencillas (item #988). */
final class FallaLectura
{
    /** @return array{codigo: string, mensaje: string, causa: string} */
    public static function deConexion(Throwable $e): array
    {
        $texto = '';
        $credenciales = false;
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            $texto .= ' ' . mb_strtolower($x->getMessage());
            $credenciales = $credenciales || ($x instanceof DataFlowException && $x->getCode() === DataFlowException::CODE_INVALID_CREDENTIALS);
        }

        return match (true) {
            str_contains($texto, 'sin destino') => self::falla('sin_laboratorio', 'No se conectó a ningún router.',
                'Fuera de producción solo se conecta al router de laboratorio, y no hay uno configurado.'),
            $credenciales || str_contains($texto, 'invalid username') => self::falla('credenciales', 'El router rechazó el usuario o la contraseña.',
                'Revisa las credenciales de la API en el registro del router.'),
            str_contains($texto, 'refused') => self::falla('puerto_cerrado', 'El router rechazó la conexión.',
                'El servicio API está apagado o usa otro puerto.'),
            str_contains($texto, 'timed out'), str_contains($texto, 'timeout') => self::falla('api_bloqueada', 'El router no contestó a tiempo.',
                'Lo más probable es que la API esté bloqueada por IP (Allowed Address de /ip service o el firewall), o que el router esté apagado.'),
            default => self::falla('sin_conexion', 'No se pudo abrir la conexión con el router.',
                'Puede estar bloqueada la API por IP, cerrado el puerto o apagado el router. Detalle: ' . mb_substr(trim($texto), 0, 160)),
        };
    }

    public static function identidadSinConfirmar(string $contesto, ?string $titulo): array
    {
        return self::falla('identidad_sin_confirmar', "Contestó «{$contesto}», que no es el título del router («{$titulo}»).",
            'Si es el router correcto, confírmalo: hasta entonces el colector no lo lee.');
    }

    public static function identidadDistinta(string $contesto, string $esperada): array
    {
        return self::falla('identidad_distinta', "Contestó «{$contesto}» y se esperaba «{$esperada}».",
            'Le cambiaron el nombre o es otro router: el colector no lo lee hasta que alguien lo confirme.');
    }

    public static function sinInterfaz(): array
    {
        return self::falla('sin_interfaz', 'No hay VLAN ni interfaz capturada.', 'Sin eso no se puede medir el tráfico: captúrala y guarda.');
    }

    public static function vlanNoEncontrada(?int $vlan, ?string $interfaz): array
    {
        return self::falla('vlan_no_encontrada', 'No se encontró ' . ($interfaz ? "la interfaz «{$interfaz}»" : "la VLAN {$vlan}") . ' en el router.',
            'Revisa el VLAN ID o el nombre exacto, y que sea el router correcto.');
    }

    public static function vlanRepetida(?int $vlan): array
    {
        return self::falla('vlan_repetida', "La VLAN {$vlan} está en más de una interfaz.", 'Captura el nombre exacto de la interfaz para que no haya duda.');
    }

    public static function ipSinRespuesta(string $ip): array
    {
        return self::falla('ip_sin_respuesta', "{$ip} no responde al ping desde el router.", 'El equipo puede estar apagado, bloquear el ping o no ser esa su IP.');
    }

    public static function sinIps(): array
    {
        return self::falla('sin_ips', 'No hay IPs marcadas para el ping.', 'Marca los equipos del cliente y guarda.');
    }

    private static function falla(string $codigo, string $mensaje, string $causa): array
    {
        return ['codigo' => $codigo, 'mensaje' => $mensaje, 'causa' => $causa];
    }
}
