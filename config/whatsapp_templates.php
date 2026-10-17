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

    // FAC-09 (#516) — PROPUESTAS, no sometidas a Meta. Categoría utilidad (transaccional, sin
    // promoción), encabezado DOCUMENTO (el PDF se adjunta al enviar con su media_id).
    // {0} nombre, {1} periodo, {2} monto.
    'prefactura_documento' => [
        'name'     => 'prefactura_documento',
        'category' => 'utility',
        'lang'     => 'es_MX',
        'header'   => 'document',
        'body'     => 'Hola {0}, te compartimos tu prefactura del periodo {1} por {2}. '
                    . 'En el documento adjunto encuentras la fecha límite y los datos para pagar.',
    ],

    'factura_pagada_documento' => [
        'name'     => 'factura_pagada_documento',
        'category' => 'utility',
        'lang'     => 'es_MX',
        'header'   => 'document',
        'body'     => 'Hola {0}, registramos tu pago. Te compartimos tu factura del periodo {1} por {2}, '
                    . 'sin valor fiscal. ¡Gracias!',
    ],

    'geofence_alert' => [
        'name'     => 'geofence_alert',
        'category' => 'utility',
        'lang'     => 'es_MX',
        // {0} nombre del vehiculo/unidad, {1} nombre de la geocerca, {2} tipo (entrada/salida)
        'body'     => 'Alerta de flota: la unidad {0} registró {2} de la geocerca "{1}".',
    ],

];
