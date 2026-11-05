<?php

// L3a Talento: regla de garantías decidida por Irving en #905 (K2, pregunta q5 = Opción 2, doc 12 §5):
// cero puntos al corregir la PROPIA instalación dentro de la ventana, sin descuento retroactivo (sin clawback).
// Esta fase solo EXPONE los interruptores; ningún cálculo los lee aún (lo hará L3b). Mismos valores que
// config/talento_incentivos.php → 'garantias'. Apagado = comportamiento actual: «Soporte/reparación (garantía)»
// suma 3 puntos, es cobrable y no exige validación.
return [
    'garantia_cero_puntos_propia'  => (bool) env('UNIFICACION_GARANTIA_CERO_PUNTOS_PROPIA', false),
    'garantia_ventana_meses'       => (int) env('UNIFICACION_GARANTIA_VENTANA_MESES', 6),
    'garantia_clawback'            => (bool) env('UNIFICACION_GARANTIA_CLAWBACK', false),
];
