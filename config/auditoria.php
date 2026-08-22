<?php

return [
    /*
     * Minero de bitácora — item #1016 (sub-item de #1004 §4).
     * Detecta la señal "intención abandonada": un registro se crea en el
     * sistema (evento `created` en `activity_log`, la bitácora interna que ya
     * alimenta Auditoría — decisión q3 de Irving) y nadie vuelve a tocarlo
     * dentro de la ventana, MIENTRAS el mismo usuario sigue generando otra
     * actividad en el sistema (prueba de que no se fue, sino que se distrajo
     * y no volvió). Solo detección, sin acciones (clase "producto").
     */
    'minero_bitacora' => [
        'enabled' => (bool) env('AUDITORIA_MINERO_BITACORA_ENABLED', true),

        // Minutos sin evento de cierre/éxito sobre el mismo registro para
        // considerarlo candidato a "intención abandonada" (decisión q2).
        'ventana_abandono_minutos' => (int) env('AUDITORIA_MINERO_VENTANA_MIN', 30),

        // Tope de filas de activity_log procesadas por corrida (protege al
        // scheduler de una corrida excepcionalmente larga si el cursor quedó
        // muy atrás).
        'lote_maximo' => (int) env('AUDITORIA_MINERO_LOTE_MAXIMO', 2000),
    ],
];
