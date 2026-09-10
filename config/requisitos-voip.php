<?php

/*
|--------------------------------------------------------------------------
| Requisitos del módulo VoIP — el manifiesto que VIAJA con la actualización
|--------------------------------------------------------------------------
|
| El principio del provisionador (item #9990718): **la actualización lleva las
| instrucciones y un sitio de descarga lleva los archivos**. Este archivo son
| las instrucciones. Viaja por git con cada versión de MegaISP y le dice al
| provisionador qué necesita el módulo y de dónde bajarlo.
|
| ─── EL CRITERIO DE VERIFICACIÓN, QUE ES LO IMPORTANTE ────────────────────
|
| El `sha256` de cada artefacto vive AQUÍ, y aquí es la ÚNICA fuente que el
| provisionador consulta. **Nunca** se verifica contra un archivo `.sha256`
| descargado del mismo sitio que el tarball.
|
| La razón: si ese host se compromete, sirve el tarball alterado *y* el .sha256
| alterado, y la verificación pasa igual — no protege de nada. El hash viaja por
| git y el archivo por HTTPS: son dos canales independientes, así que falsificar
| el artefacto exige comprometer los dos.
|
| Corolario, para que nadie lo "arregle" mal más adelante: el sitio de descarga
| SÍ publica archivos `.sha256` junto a los tarballs, y sirven para verificación
| manual. El provisionador NO los lee.
|
| Mismo principio en la firma GPG del tarball de Asterisk: se verificó contra la
| clave F2FC93DB7587BD1FB49E045A5D984BE337191CE7, cuya huella se contrastó con el
| changelog del paquete Debian de Asterisk — fuente independiente del sitio que
| sirvió el archivo.
|
| ─── SOBRE LA URL ────────────────────────────────────────────────────────
|
| Va en configuración con valor por omisión, nunca fija en el código: el día que
| cambie el dominio, se monte un espejo o un cliente grande quiera consumir de su
| propio servidor, no debe hacer falta emitir una versión nueva del sistema.
|
| El host por omisión está a propósito FUERA de la infraestructura propia
| (hosting externo): una caída del nodo de Proxmox no debe impedir que una
| instalación se provisione.
|
*/

return [

    'asterisk' => [

        'version' => '22.11.0',

        // Raíz del sitio de descarga. Sobrescribible por entorno.
        'origen' => env('MEGAISP_ARTEFACTOS_URL', 'https://megaisp.com.mx/artefactos/asterisk'),

        // El tarball de Asterisk. `sha256` es la ÚNICA fuente de verdad para
        // verificarlo (ver el criterio arriba).
        'archivo' => 'asterisk-22.11.0.tar.gz',
        'sha256'  => '3bd5ee040509a3d3cd9b1ba9520c18e6ec0a7e7981ca68c457dcd36ba3c54d94',

        // Firma GPG del tarball: informativa para verificación manual. La huella
        // se contrastó contra el changelog del paquete Debian de Asterisk.
        'firma'          => 'asterisk-22.11.0.tar.gz.asc',
        'clave_firmante' => 'F2FC93DB7587BD1FB49E045A5D984BE337191CE7',

        // Revisión de Alembic que corresponde a este Asterisk. El provisionador
        // ejecuta `alembic upgrade` hasta aquí y verifica que quede registrada en
        // la tabla ESTÁNDAR `alembic_version`.
        //
        // ⚠️ PENDIENTE: se completa al ejecutar la receta por primera vez, que es
        // cuando se conoce el head real del árbol de 22.11.0. Mientras esté en
        // null, el provisionador debe ABORTAR antes de tocar la base — nunca
        // asumir "la que salga".
        'esquema_realtime' => null,

        // Paquetes de sonidos. Las versiones salen del `sounds/Makefile` del
        // propio tarball de Asterisk; la ruta upstream real es `.../sounds/releases/`,
        // no `.../sounds/`.
        'sonidos' => [
            'core-es-alaw' => [
                'archivo' => 'asterisk-core-sounds-es-alaw-1.6.1.tar.gz',
                'sha256'  => 'e21319d9a1a19f1552a05a3826dfed6456deaafc2fc1f0e5df9d7b7413a8092a',
            ],
            'core-en-alaw' => [
                'archivo' => 'asterisk-core-sounds-en-alaw-1.6.1.tar.gz',
                'sha256'  => 'ff09b4c0512506bafc07a072a07bf08a316529469b63f0ebfd88f6f865faca60',
            ],
            'moh-opsound-alaw' => [
                'archivo' => 'asterisk-moh-opsound-alaw-2.03.tar.gz',
                'sha256'  => '8b6d63486fd58fd535eaed394f9bd32ecdf6e650975aaa258941f423c8150b81',
            ],
        ],

        // Idioma por omisión de los prompts. Va a `asterisk.conf`: sin esta línea
        // Asterisk ignora los sonidos en español y suenan en inglés, aunque estén
        // instalados. Es el ajuste 1 de los seis al script base.
        'idioma' => 'es',

        // Espacio libre mínimo para compilar, en MB. El provisionador ABORTA si
        // no lo hay — no solo avisa (ajuste 4 de los seis).
        'espacio_minimo_mb' => 3072,
    ],

];
