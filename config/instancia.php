<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rol de esta instalación de MegaISP
    |--------------------------------------------------------------------------
    |
    | El código de MegaISP viaja COMPLETO a todas las instalaciones, pero no
    | todos los módulos deben poder encenderse en todas. Los módulos de
    | operador —Voz Mayorista, y más adelante facturación mayorista o el panel
    | de aprovisionamiento de VMs— administran el negocio de Meganet: tarifas
    | de carrier, márgenes, inventario de DID, capacidad contratada. Un cliente
    | arrendado no debe poder activarlos desde su propio panel.
    |
    | Valores: 'operador' (la instalación de Meganet) | 'cliente' (arrendada).
    |
    | ⚠️ EL DEFAULT ES 'cliente' A PROPÓSITO. Es el valor restrictivo, no el
    | permisivo. Una instalación a la que se le olvidó definir INSTANCE_ROLE
    | debe quedarse sin los módulos de operador, no con ellos: el modo de fallo
    | seguro es "de menos", nunca "de más". Si el default fuera 'operador', un
    | .env incompleto en la VM de un cliente le abriría el panel comercial de
    | Meganet, y ese error no avisa — simplemente funciona hasta que alguien
    | mira lo que no debía.
    |
    */

    'rol' => env('INSTANCE_ROLE', 'cliente'),

    /*
    | Roles válidos. Un valor fuera de esta lista se trata como 'cliente'
    | (mismo criterio: ante un valor que no entendemos, restringir).
    */
    'roles_validos' => ['operador', 'cliente'],

];
