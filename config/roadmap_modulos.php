<?php

/*
|--------------------------------------------------------------------------
| Mapa de módulos de la Hoja de ruta (#1050, paso A de #1030)
|--------------------------------------------------------------------------
| `roadmap_items.modulo` es una etiqueta libre; `modulo_slug` la traduce al módulo de
| `module_registry` para que la Torre pueda agrupar el trabajo por módulo. La etiqueta NO se
| reescribe (el despachador la usa para el tope por módulo), salvo las correcciones de escritura.
|
| La búsqueda ignora mayúsculas y acentos: «flotas» y «Flotas» caen en el mismo alias.
| Una etiqueta que no está aquí deja `modulo_slug` en NULL y un aviso en el log.
*/

return [

    // Etiqueta → slug de module_registry, o un área transversal (`area-*`, ver abajo).
    'alias' => [
        'Roadmap / Circuito CC'       => 'addon-roadmap',
        'Roadmap'                     => 'addon-roadmap',
        'Torre de Control'            => 'addon-roadmap',
        'Talento'                     => 'addon-talento',
        // Facturación es un módulo propio dentro de Finanzas: conserva su etiqueta (y su tope
        // por módulo en el despachador) y en la vista por módulos cuelga de Finanzas.
        'Facturación'                 => 'addon-finanzas',
        'Finanzas'                    => 'addon-finanzas',
        'Flotas'                      => 'addon-flotas',
        'Clientes'                    => 'core-clientes',
        'Core / Clientes'             => 'core-clientes',
        'CRM'                         => 'core-crm',
        'MegaFamilia'                 => 'addon-megafamilia',
        // IPv6 no es un módulo (solo modelos en Addons/Ipv6); su trabajo es de Gestión de Red.
        'Red / IPv6'                  => 'addon-gestion-red',
        'GestionRed'                  => 'addon-gestion-red',
        'Vendedores'                  => 'addon-vendedores',
        'Scheduling'                  => 'addon-scheduling',
        'Usuarios / Permisos'         => 'core-usuarios',
        'Permisos / Roles'            => 'core-usuarios',
        'Usuarios'                    => 'core-usuarios',
        'ModuleManager'               => 'core-module-manager',
        'Core / Layout'               => 'core-layout',
        'Marketing'                   => 'addon-marketing',
        'Deploy / Releases'           => 'core-release',
        'Release'                     => 'core-release',
        'Configuracion'               => 'core-configuracion',
        'Payments'                    => 'addon-payments',
        'Pagos / Verificación diaria' => 'addon-payments',
        'Inventario'                  => 'addon-inventario',
        'Tickets'                     => 'addon-tickets',
        'CobranzaBlaster'             => 'addon-cobranza-blaster',
        'Documentos'                  => 'core-documentos',
        'WarRoom'                     => 'addon-warroom',
        'DevTools'                    => 'addon-devtools',
        'Dashboard'                   => 'core-dashboard',
        'Mapas'                       => 'addon-mapas',
        'Manual'                      => 'addon-manual',
        'WhatsAppAgent'               => 'addon-whatsapp-agent',
        'Auth'                        => 'core-auth',
        'Embajadores'                 => 'addon-embajadores',
        'IA'                          => 'addon-ia',
        'SmartImportExport'           => 'addon-smart-import-export',
        'MonitoreoOficial'            => 'addon-monitoreo-oficial',
        // Core/Voice es la librería compartida de telefonía, no un módulo: su trabajo es de VoIP.
        'VoIP'                        => 'addon-voip',
        'Voice'                       => 'addon-voip',
        'Notificaciones'              => 'core-notifications',
        'Portal Cliente'              => 'addon-portal-cliente',
        'Integraciones'               => 'addon-hub',
        'Security'                    => 'area-seguridad',
        'Seguridad'                   => 'area-seguridad',
        'Core / arquitectura'         => 'area-arquitectura',
        'Infraestructura'             => 'area-infraestructura',
    ],

    // Dentro de una etiqueta, un prefijo de título que manda a otro módulo (los items «CRM-E …»
    // son de CRM Empresarial y los «Firma electrónica: …» de Firma Electrónica aunque su etiqueta
    // diga CRM).
    'por_prefijo_titulo' => [
        'CRM' => [
            'CRM-E'             => 'addon-crm-empresarial',
            'CRM empresarial'   => 'addon-crm-empresarial',
            'Firma electrónica' => 'addon-firma-electronica',
        ],
    ],

    // Trabajo transversal que no es de un solo módulo.
    'areas' => [
        'area-seguridad'       => 'Seguridad',
        'area-arquitectura'    => 'Arquitectura',
        'area-infraestructura' => 'Infraestructura',
    ],

    // Correcciones de escritura de la etiqueta (misma etiqueta, otra grafía). Se guardan en el log
    // del item para poder revertirlas con `roadmap:mapear-modulos --revertir`.
    'correcciones' => [
        'flotas'        => 'Flotas',
        'GestiónRed'    => 'GestionRed',
        'Configuración' => 'Configuracion',
    ],
];
