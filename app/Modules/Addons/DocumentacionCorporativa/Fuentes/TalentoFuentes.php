<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Fuentes;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\Talento\Models\TalentoColaborador;

/**
 * Fase 1.3 (item #730) — fuentes vivas de talento humano para el Apartado VII.
 *
 * Mismo mecanismo que Fase 1.1/1.2 (`FinanzasFuentes`): solo lectura sobre
 * `TalentoColaborador`, nunca escribe. `$empresaId` no se usa (mismo motivo
 * documentado en `FinanzasFuentes`: una sola empresa corporativa hoy).
 *
 * Nunca se expone `base_salary`/`curp`/`nss` (datos sensibles del expediente
 * RH, gateados aparte por `talento.expediente.view`) — estas fuentes solo
 * listan identidad + puesto/relación, igual que `finanzas.proveedores`
 * evita mostrar montos que no le corresponden a ese concepto.
 */
class TalentoFuentes
{
    /** Roles Spatie que cuentan como "técnico especializado" (mismo catálogo que ClientPaymentController::tecnicos). */
    private const ROLES_TECNICOS = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];

    public static function registrar(FuenteRegistry $registry): void
    {
        $registry->registrar('talento.plantilla', static fn (array $config, int $empresaId): array => self::plantilla($config));
        $registry->registrar('talento.externos', static fn (array $config, int $empresaId): array => self::externos($config));
    }

    /**
     * Plantilla laboral vigente (`type=interno`, `status=active`). Con
     * `config.clasificacion=tecnicos` filtra además por rol Spatie del user
     * asociado (TECNICO/TECNICO_INSTALADOR/TECNICO_PLANTA) — mismo criterio
     * que ya usa `ClientPaymentController::tecnicos()`, en vez de parsear
     * `job_title` (texto libre, frágil).
     */
    private static function plantilla(array $config): array
    {
        $query = TalentoColaborador::query()
            ->with('user')
            ->where('type', 'interno')
            ->where('status', 'active');

        $esTecnicos = ($config['clasificacion'] ?? null) === 'tecnicos';
        if ($esTecnicos) {
            $query->whereHas('user.roles', function ($q) {
                $q->whereIn('name', self::ROLES_TECNICOS);
            });
        }

        $colaboradores = $query->orderBy('department')->get();

        $datos = $colaboradores->map(fn (TalentoColaborador $c) => [
            'nombre'        => $c->user?->name ?? 'Sin nombre',
            'puesto'        => $c->job_title,
            'departamento'  => $c->department,
            'fecha_ingreso' => optional($c->hire_date)->format('d/m/Y'),
            'estado'        => $c->status,
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total' => $colaboradores->count(),
            ],
            'mensaje' => ($datos === [] && $esTecnicos)
                ? 'Sin colaboradores clasificados como técnicos especializados en este entorno.'
                : null,
        ];
    }

    /**
     * Relación de personal externo (`type=externo`, `status=active`). Con
     * `config.clasificacion` filtra por la columna nueva
     * `categoria_externo` (migración `2026_08_28_940000`, item #730) — un
     * colaborador sin esa columna poblada simplemente no aparece en ninguna
     * clasificación, no es un error.
     */
    private static function externos(array $config): array
    {
        $query = TalentoColaborador::query()
            ->with('user')
            ->where('type', 'externo')
            ->where('status', 'active');

        $clasificacion = $config['clasificacion'] ?? null;
        if ($clasificacion) {
            $query->where('categoria_externo', $clasificacion);
        }

        $colaboradores = $query->orderBy('department')->get();

        $datos = $colaboradores->map(fn (TalentoColaborador $c) => [
            'nombre'        => $c->user?->name ?? 'Sin nombre',
            'puesto'        => $c->job_title,
            'relacion'      => $c->relation_type,
            'fecha_ingreso' => optional($c->hire_date)->format('d/m/Y'),
            'estado'        => $c->status,
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total'         => $colaboradores->count(),
                'clasificacion' => $clasificacion,
            ],
            'mensaje' => $datos === []
                ? ($clasificacion
                    ? "Sin colaboradores clasificados como '{$clasificacion}' en este entorno."
                    : 'Sin personal externo registrado en este entorno.')
                : null,
        ];
    }
}
