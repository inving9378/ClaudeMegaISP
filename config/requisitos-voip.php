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

    /*
    | Versión del PROVISIONADOR, no de Asterisk. Se incrementa cuando cambia su
    | lógica: qué pasos hace, en qué orden, o qué considera "completado".
    |
    | Cada registro de `voip_provision_estado` guarda con qué versión se ejecutó su
    | paso. Un cliente puede quedarse a medias con una versión y actualizar MegaISP
    | antes de reintentar; sin ese dato el estado es mixto —unos pasos con la lógica
    | vieja, otros por hacer con la nueva— y reintentar encima es adivinar si lo ya
    | hecho sigue valiendo.
    */
    'provisionador_version' => '1.0.0',

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
        // ⚠️ EN NULL A PROPÓSITO — el huevo y la gallina, resuelto con una bandera.
        //
        // La revisión que corresponde a una versión de Asterisk solo se conoce
        // ejecutándola, pero el manifiesto tiene que declararla para validarla.
        //
        //   · Primera vez  → se corre con ASTERISK_MODO_DESCUBRIMIENTO=1. No exige
        //                    la revisión: ejecuta `alembic upgrade head`, REPORTA
        //                    la resultante, y ese valor se fija aquí.
        //   · De ahí en más → se valida contra este campo y se ABORTA si no coincide.
        //
        // El modo se activa con bandera EXPLÍCITA, nunca automáticamente por
        // encontrar este campo vacío. En la instalación de un cliente el manifiesto
        // siempre viene completo, y un descubrimiento disparado por accidente allí
        // aceptaría en silencio cualquier esquema que saliera — que es exactamente
        // cómo se llegó al esquema remendado que este trabajo viene a corregir.
        //
        // Con el campo vacío y sin la bandera, el provisionador aborta antes de
        // tocar la base.
        'esquema_realtime' => null,

        // Dónde sobrevive el árbol de Alembic a la limpieza de fuentes.
        // `contrib/ast-db-manage` vive DENTRO del tarball y es lo único que genera
        // el esquema de las `ps_*`: si se borra con el árbol, el provisionador se
        // queda sin con qué crear la base realtime.
        'soporte_dir' => env('MEGAISP_ASTERISK_SOPORTE_DIR', '/usr/share/megaisp-asterisk'),

        // Dónde deja MegaISP los .conf que GENERA y que Asterisk incluye
        // (dialplan, grupos, registros de troncal).
        //
        // Fuera del árbol de la aplicación web a propósito (#9990718 §6): antes
        // vivían en storage/app/asterisk/ y /etc/asterisk los incluía por ruta
        // absoluta, así que la configuración que Asterisk lee dependía de dónde
        // estuviera instalado MegaISP. Mover la aplicación rompía la telefonía, y
        // Asterisk —que corre como root— leía configuración de un directorio del
        // árbol web, escribible por www-data.
        'generados_dir' => env('MEGAISP_ASTERISK_GENERADOS_DIR', '/etc/asterisk/megaisp.d'),

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

        // Nombre del objeto `transport` de PJSIP.
        //
        // Vive aquí porque lo nombran DOS lados que tienen que coincidir: la
        // plantilla pjsip.conf, que lo declara, y las extensiones y troncales que
        // MegaISP publica al realtime, que lo referencian en su columna
        // `transport`. Cuando cada uno lo escribía por su cuenta divergieron —la
        // plantilla decía `transport-udp` y el seeder `udp`— y Asterisk rechazaba
        // toda llamada con «Unable to retrieve PJSIP transport 'udp'» y un 500 al
        // teléfono. Las extensiones registraban igual, así que parecía cosa del
        // plan de marcado.
        'transporte' => 'transport-udp',

        // Idioma por omisión de los prompts. Va a `asterisk.conf`: sin esta línea
        // Asterisk ignora los sonidos en español y suenan en inglés, aunque estén
        // instalados. Es el ajuste 1 de los seis al script base.
        'idioma' => 'es',

        // Espacio libre mínimo para compilar, en MB. El provisionador ABORTA si
        // no lo hay — no solo avisa (ajuste 4 de los seis).
        'espacio_minimo_mb' => 3072,
    ],

];
