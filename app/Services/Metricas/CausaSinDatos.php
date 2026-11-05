<?php

namespace App\Services\Metricas;

/**
 * Por qué un servicio no tiene datos, en palabras sencillas (item #988): de lo más general (el freno
 * de todo el sistema) a lo más particular (una IP). Solo decide; los datos los junta EstadoServicioDedicado.
 */
final class CausaSinDatos
{
    /**
     * @param  array{
     *     monitoreo: array{activo: bool, override: ?bool, cambiado_por: ?string, cambiado_en: ?string},
     *     configurado: bool, con_router: bool, con_que_medir: bool,
     *     latido_segundos: ?int, umbral_segundos: int, router: ?array, estado_fila: ?string, sin_ips: bool, ips_sin_respuesta: string[]
     * }  $h
     * @return array{codigo: string, titulo: string, detalle: ?string}
     */
    public static function de(array $h): array
    {
        if (! $h['monitoreo']['activo']) {
            return self::causa('monitoreo_apagado', 'El monitoreo está apagado en Configuración', self::quienApago($h['monitoreo']));
        }
        if (! $h['configurado'] || ! $h['con_router'] || ! $h['con_que_medir']) {
            return self::causa('servicio_no_configurado', 'Falta configurar este servicio', match (true) {
                ! $h['configurado'] => 'Todavía no tiene datos de red.',
                ! $h['con_router']  => 'No tiene router: captúralo en «Datos de red».',
                default             => 'No tiene VLAN, interfaz ni IPs que medir: captúralas en «Datos de red».',
            });
        }
        if ($h['latido_segundos'] === null) {
            return self::causa('colector_sin_correr', 'El colector no ha corrido', 'No hay ninguna corrida registrada.');
        }
        if ($h['latido_segundos'] > $h['umbral_segundos']) {
            return self::causa('colector_sin_correr', 'El colector no ha corrido', 'Su última corrida fue hace ' . self::tiempo($h['latido_segundos']) . '.');
        }

        $router = $h['router'];
        switch ($router['estado'] ?? null) {
            case 'sin_conexion':
                return self::causa('router_sin_respuesta', 'El router no contesta', $router['error'] ?? null);
            case 'identidad_distinta':
                return self::causa('identidad_distinta', 'El router contestó con otra identidad', ($router['error'] ?? '') . ' Revisa en «Datos de red» → «Probar lectura».');
            case 'sin_identidad_esperada':
                return self::causa('identidad_sin_confirmar', 'Falta confirmar el router', $router['error'] ?? null);
            case 'omitido':
                return self::causa('sin_tiempo', 'No alcanzó el tiempo del ciclo', 'El router se dejó para la siguiente corrida.');
        }

        if ($h['estado_fila'] === 'vlan_repetida') {
            return self::causa('vlan_repetida', 'La VLAN está repetida en el router', 'Hay más de una interfaz con ese VLAN ID: captura el nombre exacto en «Datos de red».');
        }
        if ($h['estado_fila'] === 'interfaz_no_encontrada') {
            return self::causa('vlan_no_encontrada', 'No se encontró la VLAN o interfaz en el router', 'Revisa el VLAN ID o el nombre en «Datos de red».');
        }
        if ($h['ips_sin_respuesta'] !== []) {
            return self::causa('ip_sin_respuesta', 'Las IPs no responden', 'Sin respuesta: ' . implode(', ', $h['ips_sin_respuesta']) . '.');
        }
        if ($h['sin_ips']) {
            return self::causa('sin_ips', 'No hay IPs marcadas para el ping', 'Márcalas en «Datos de red».');
        }

        return self::causa('sin_lecturas', 'Todavía no hay lecturas', 'La primera llega en la siguiente corrida del colector.');
    }

    private static function causa(string $codigo, string $titulo, ?string $detalle): array
    {
        return ['codigo' => $codigo, 'titulo' => $titulo, 'detalle' => $detalle];
    }

    private static function quienApago(array $m): ?string
    {
        if ($m['override'] === false) {
            return 'METRICAS_ACTIVO en el .env lo tiene apagado.';
        }

        return $m['cambiado_por'] ? "Lo apagó {$m['cambiado_por']}" . ($m['cambiado_en'] ? " el {$m['cambiado_en']}." : '.') : null;
    }

    private static function tiempo(int $segundos): string
    {
        return match (true) {
            $segundos < 7200   => round($segundos / 60) . ' min',
            $segundos < 172800 => round($segundos / 3600) . ' h',
            default            => round($segundos / 86400) . ' días',
        };
    }
}
