<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Fuentes;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcAccionista;

/**
 * Fase 2d (item roadmap #737) — fuentes cuya tabla dueña es del PROPIO módulo
 * (namespace `dc.*`), a diferencia de `FinanzasFuentes`/`TalentoFuentes`/etc.
 * que leen de otros dominios. El concepto "Estructura accionaria actualizada"
 * (apartado I) es `tipo_resolvedor` = `grafica`, no `plantilla`: se resuelve
 * en VIVO contra `dc_accionistas` (Fase 2c, item #736) vía `GraficaResolver`,
 * igual patrón que cualquier otra fuente registrada aquí.
 */
class PropiaFuentes
{
    public static function registrar(FuenteRegistry $registry): void
    {
        $registry->registrar(
            'dc.estructura_accionaria',
            static fn (array $config, int $empresaId): array => self::estructuraAccionaria($empresaId)
        );
    }

    /**
     * Sólo accionistas VIGENTES (sin `fecha_baja` o con baja futura) — un
     * accionista que ya salió no forma parte de la estructura "actualizada".
     */
    private static function estructuraAccionaria(int $empresaId): array
    {
        $hoy = now()->toDateString();

        $accionistas = DcAccionista::deEmpresa($empresaId)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>', $hoy);
            })
            ->orderByDesc('porcentaje')
            ->get();

        $datos = $accionistas->map(fn (DcAccionista $a) => [
            'accionista'  => $a->nombre_razon_social,
            'serie'       => $a->tipo_serie,
            'acciones'    => $a->num_acciones,
            'porcentaje'  => (float) $a->porcentaje,
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_accionistas' => $accionistas->count(),
                'porcentaje_total'  => round((float) $accionistas->sum('porcentaje'), 2),
            ],
            'mensaje' => $accionistas->isEmpty()
                ? 'Sin accionistas vigentes capturados en el libro de registro de acciones.'
                : null,
        ];
    }
}
