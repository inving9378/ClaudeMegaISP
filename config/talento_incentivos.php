<?php

/*
 * UNI-T K2 (#905) — Decisiones económicas de Irving, registradas como configuración.
 *
 * Esta configuración NO cambia ningún cálculo por sí sola: cada clave `activo`/`modo` por defecto
 * reproduce el comportamiento ACTUAL. Los cálculos que la leerán son D8b (#906), I5 (#907), L3 y M2,
 * cada uno detrás de su interruptor de config/unificacion.php. Ver docs/unificacion/12-decisiones-economicas.md.
 */
return [
    // §1 Periodo semanal — Opción 3: Talento unifica en PayWeek (sáb 18:00); Vendedores dom–sáb queda informativo.
    'periodo' => [
        'talento_usa_payweek'        => true,  // ya es así hoy
        'vendedores_calendario'      => 'dom_sab_informativo',
        'vendedores_usa_payweek'     => false, // pasar a true solo por decisión posterior
    ],

    // §2 Compatibilidad sueldo vendedor / bono Talento / comisión embajador — Opción 2: matriz configurable.
    // Valores: 'compatible' | 'excluyente' | 'tope'. Por defecto todo compatible (= hoy).
    'compatibilidad' => [
        'vendedor_talento'    => 'compatible',
        'vendedor_embajador'  => 'compatible',
        'talento_embajador'   => 'compatible',
        'tope'                => null,
    ],

    // §3 Importes del sueldo de vendedor — Opción 2: columnas de la regla; estos son los valores actuales (default).
    'sueldo_vendedor' => [
        'por_venta'      => 300,
        'por_prospecto'  => 15,
        'atajo'          => 120,
        'desde_regla_bd' => false, // true cuando existan las columnas y se active I5
    ],

    // §4 Orden con varios participantes — Opción 3: porcentajes por rol; default = hoy (unidad completa a cada uno).
    'participantes' => [
        'modo'                 => 'unidad_completa',
        'porcentaje_responsable' => 100,
        'porcentaje_participante' => 100,
    ],

    // §5 Garantías y correcciones — Opción 2: cero puntos al corregir la propia instalación dentro de la ventana,
    // sin descuento retroactivo (sin clawback). Apagado por defecto (= hoy: 3 puntos siempre).
    'garantias' => [
        'cero_puntos_propia_instalacion' => false,
        'ventana_meses'                  => 6,
        'clawback'                       => false,
    ],

    // §6 Metas — Opción 1: una sola meta por concepto (cuota de la regla de compensación) que la regla semanal lee.
    // Permanencia/descomisión quedan para una política posterior de Irving (no se incluyen aquí).
    'metas' => [
        'fuente_unica_cuota_regla' => false, // true = la regla semanal lee la cuota de la regla de compensación
    ],

    // §7 Planta externa — Opción 2: evaluación 1–10 como multiplicador del bono existente, solo planta externa.
    'evaluacion_planta_externa' => [
        'activo'              => false,
        'bono_normal_desde'   => 9,
        'excelencia_en'       => 10,
    ],
];
