<?php

namespace App\Services\Metricas;

use Illuminate\Support\Facades\DB;

/**
 * ¿El router que contestó es el que debe ser? (item #988, decisión de Irving). Si su identidad es
 * exactamente el título del router en MegaISP se registra sola; si no, una persona la confirma con
 * «Probar lectura». Registrada, si después contesta otra, el colector deja de leer ese router.
 */
final class IdentidadRouter
{
    public const COINCIDE = 'coincide';

    /** Sin registrar y contesta exactamente el título: se registra sola. */
    public const POR_TITULO = 'por_titulo';

    /** Sin registrar y contesta otra cosa: falta que alguien la confirme. */
    public const SIN_REGISTRAR = 'sin_registrar';

    /** Registrada, y contestó otra. */
    public const DISTINTA = 'distinta';

    public static function evaluar(string $contesto, ?string $esperada, ?string $titulo): string
    {
        $esperada = trim((string) $esperada);
        if ($esperada !== '') {
            return $contesto === $esperada ? self::COINCIDE : self::DISTINTA;
        }

        return $contesto !== '' && $contesto === trim((string) $titulo) ? self::POR_TITULO : self::SIN_REGISTRAR;
    }

    /** Directo a la tabla: guardar el modelo Mikrotik dispara trabajos que escriben en el router. */
    public static function guardar(int $routerId, string $identidad): void
    {
        DB::table('mikrotiks')->where('router_id', $routerId)->update(['identidad_esperada' => $identidad]);
    }
}
