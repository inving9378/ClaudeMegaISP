<?php

/**
 * Catálogo de plantillas de mensaje PROACTIVO (las que el sistema manda por
 * iniciativa propia, sin que el cliente haya escrito primero). Un solo lugar
 * para el texto real de cada una:
 *
 * - En la API OFICIAL de Meta, este `body` es la referencia para REDACTAR y
 *   someter la plantilla a aprobación en Meta Business Manager -- el envío
 *   real usa el `name` + variables posicionales, y Meta resuelve el cuerpo
 *   aprobado por su cuenta (no se manda el texto, solo el nombre+variables).
 * - En Evolution (sin sistema de plantillas real), el MISMO `body` se resuelve
 *   aquí mismo sustituyendo {0}, {1}... y se manda como texto libre.
 *
 * `name` debe coincidir EXACTO con el nombre de la plantilla ya aprobada en
 * Meta (minúsculas, guión bajo) una vez que exista -- hasta entonces, sirve
 * de documento para saber qué someter a revisión.
 */
return [

    'payment_reminder' => [
        'name'     => 'payment_reminder',
        'category' => 'utility',
        'lang'     => 'es_MX',
        // {0} nombre del cliente, {1} monto, {2} fecha de vencimiento
        'body'     => 'Hola {0}, tu saldo pendiente con Meganet es de {1} con vencimiento el {2}. '
                    . 'Evita la suspensión de tu servicio realizando tu pago a tiempo.',
    ],

    'geofence_alert' => [
        'name'     => 'geofence_alert',
        'category' => 'utility',
        'lang'     => 'es_MX',
        // {0} nombre del vehiculo/unidad, {1} nombre de la geocerca, {2} tipo (entrada/salida)
        'body'     => 'Alerta de flota: la unidad {0} registró {2} de la geocerca "{1}".',
    ],

];
