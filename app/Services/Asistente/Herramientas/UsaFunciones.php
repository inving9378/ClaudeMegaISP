<?php

namespace App\Services\Asistente\Herramientas;

/** Datos de una función del sistema: llegan como texto JSON para que sirva con cualquier IA. */
trait UsaFunciones
{
    /** @return array|string arreglo de datos, o el mensaje de error */
    protected function datosDe(mixed $crudo): array|string
    {
        if (is_array($crudo)) {
            return $crudo;
        }
        $crudo = trim((string) $crudo);
        if ($crudo === '') {
            return [];
        }
        $datos = json_decode($crudo, true);

        return is_array($datos) ? $datos : 'Los datos deben ser un objeto JSON, por ejemplo {"name":"Juan","status":1}.';
    }

    protected function rutaLimpia(string $ruta): string
    {
        $ruta = trim($ruta);
        $partes = parse_url($ruta);

        return '/' . ltrim(($partes['path'] ?? ''), '/') . (isset($partes['query']) ? '?' . $partes['query'] : '');
    }

    /** Resultado de una función, recortado para la IA. */
    protected function resultadoParaIa(array $r, int $max = 15000): array
    {
        $cuerpo = $r['cuerpo'];
        if (is_array($cuerpo)) {
            $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $cuerpo = mb_strlen($json) > $max ? mb_substr($json, 0, $max) . '…(recortado)' : $cuerpo;
        }

        return array_filter([
            'ok'      => $r['ok'],
            'estado'  => $r['estado'],
            'tipo'    => $r['tipo'],
            'avisos'  => $r['avisos'] ?? null,
            'enlace'  => $r['enlace'] ?? null,
            'resultado' => $cuerpo,
        ], fn ($v) => $v !== null);
    }
}
