<?php

/**
 * Catálogo de puntos de uso de IA que se pueden asignar a un proveedor.
 * 'global' es el respaldo cuando una clave no tiene asignación propia.
 * Agregar un consumidor nuevo = sumar su clave aquí.
 *
 * requiere: capacidades que el proveedor debe soportar (imagenes).
 * El Circuito CC NO aparece aquí a propósito: usa su propio CLI.
 */
return [
    'global' => ['nombre' => 'Respaldo global (módulos sin asignación)', 'requiere' => []],
];
