<?php

/**
 * POLÍTICA ÚNICA DE DISCO — la fuente que leen TODOS los que hoy miden lo mismo.
 *
 * POR QUÉ EXISTE (2026-08-25): había dos políticas conviviendo. `config/torre_salud.php`
 * pintaba el panel de Salud con amarillo 85 / rojo 93, y los escalones dictados para el
 * vigilante son 80/85/90/95. Con las dos vivas, el panel decía "verde" al 82 % mientras el
 * vigilante ya tenía que estar avisando. Dos verdades sobre el mismo disco es la misma
 * divergencia que ya costó `eta_minutos` contra `eta_segundos`.
 *
 * Los escalones son la POLÍTICA; este archivo es sólo dónde vive. Quien mida disco —panel de
 * Salud, compuertas, Thomas— deriva de aquí y no define umbrales propios.
 *
 * Cada escalón es un PORCENTAJE DE USO del sistema de archivos, y son acumulativos: al llegar
 * a uno, todo lo del escalón anterior ya se hizo y volvió a medirse. Nunca un barrido de golpe.
 */
return [

    'escalones' => [
        // 80 % — AVISA Y NO TOCA NADA. Investiga qué creció, cuánto y desde cuándo, y lo reporta.
        'avisa' => (int) env('CIRCUITO_DISCO_AVISA', 80),

        // 85 % — COMPRIME logs ya rotados, limpia cachés regenerables, borra artefactos de build.
        'comprime' => (int) env('CIRCUITO_DISCO_COMPRIME', 85),

        // 90 % — TRUNCA logs activos, SIEMPRE después de preservar su ventana forense. Avisa fuerte.
        'trunca' => (int) env('CIRCUITO_DISCO_TRUNCA', 90),

        // 95 % — PAUSA el circuito (deja de generar más), libera y alarma. Seguir escribiendo es
        // lo que lo está matando.
        'pausa' => (int) env('CIRCUITO_DISCO_PAUSA', 95),
    ],

    /**
     * Mapa escalón → nivel de escalamiento del encargo. Aquí no se ejecuta nada: es el contrato
     * que la entrega de RECURSOS tendrá que respetar cuando se le den manos a Thomas.
     *
     * Ojo con 'trunca': truncar un log activo es PÉRDIDA IRREVERSIBLE, así que no vive en el
     * peldaño silencioso — actúa y avisa, y sólo si la ventana forense se escribió con éxito
     * VERIFICADO (exit code, no buena intención).
     */
    'nivel_por_escalon' => [
        'avisa'    => 'resuelve_y_no_interrumpe',
        'comprime' => 'resuelve_y_no_interrumpe',
        'trunca'   => 'actua_y_avisa',
        'pausa'    => 'alarma',
    ],
];
