<?php

/*
|--------------------------------------------------------------------------
| JUEGO ÚNICO DE CREDENCIALES — no duplicar (#9990718 §6)
|--------------------------------------------------------------------------
|
| AMI  → variables `AMI_*`          (AMI_HOST, AMI_PORT, AMI_USERNAME, AMI_SECRET)
| ARI  → variables `ASTERISK_ARI_*`
|
| Había un tercer juego, `ASTERISK_AMI_*`, que duplicaba al de AMI para el mismo
| Asterisk y **no lo leía nadie**: ningún archivo PHP lo referenciaba, y su
| contraseña seguía siendo el marcador `CAMBIAR_AMI_PASS_VOIP` sin sustituir.
|
| Dos juegos de credenciales para el mismo servicio no son redundancia útil: son
| ambigüedad. Al automatizar, el provisionador tiene que saber cuál escribir en
| `manager.conf`, y adivinar entre dos —uno de ellos con un marcador dentro— es
| exactamente cómo se acaba con una central que autentica contra credenciales que
| nadie recuerda haber puesto.
|
| Las credenciales de AMI y ARI las GENERA el provisionador y quedan registradas
| en la tabla de estado: quién y cuándo. El valor no se escribe en el log.
|
*/

return [
    'ari_host' => env('ASTERISK_ARI_HOST', '127.0.0.1'),
    'ari_port' => env('ASTERISK_ARI_PORT', 8088),
    'ari_user' => env('ASTERISK_ARI_USER', 'medussa'),
    'ari_pass' => env('ASTERISK_ARI_PASS', ''),

    'ami_host' => env('AMI_HOST', '127.0.0.1'),
    'ami_port' => env('AMI_PORT', 5038),
    'ami_user' => env('AMI_USERNAME', 'megaisp'),
    'ami_pass' => env('AMI_SECRET', ''),

    // Contexto del dialplan de Asterisk usado por AmiConnectionService::originate()
    // (CobranzaBlaster). Item roadmap #793.
    'ami_context' => env('AMI_CONTEXT', 'cobranza-blaster'),

    // Identidad de la troncal que provisiona Core\Voice\VoiceGateway (ps_endpoints/
    // ps_auths/ps_aors realtime). Default = los valores históricos de la troncal
    // Servnet de Meganet: se dejan así a propósito (decisión de Irving, item
    // #9990714 q2) para no renombrar en vivo un endpoint con contactos registrados;
    // otra instalación puede apuntar a su propio proveedor sin tocar código.
    'trunk_endpoint_id' => env('VOIP_TRUNK_ENDPOINT_ID', 'servnet'),
    'trunk_auth_id'     => env('VOIP_TRUNK_AUTH_ID', 'servnet-auth'),
    'trunk_aor_id'      => env('VOIP_TRUNK_AOR_ID', 'servnet-aor'),
    'trunk_context'     => env('VOIP_TRUNK_CONTEXT', 'from-servnet'),

    // Rango de sistema que VoiceGateway nunca debe crear/pisar al provisionar la
    // troncal: un id de trunk reservado (compat con la troncal secundaria previa
    // a PJSIP realtime) + el rango de extensiones internas de la oficina. Default
    // = los valores de la instalación de Meganet; cada instalación fija los suyos.
    'reserved_trunk_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VOIP_RESERVED_TRUNK_IDS', 'trunk_2'))
    ))),
    'reserved_extension_range' => [
        (int) env('VOIP_RESERVED_EXTENSION_FROM', 1001),
        (int) env('VOIP_RESERVED_EXTENSION_TO', 1004),
    ],

    // Consumidas por GeneradorConfigAsterisk::valoresDelServidor() para rellenar
    // las plantillas de pjsip/manager (#9991217, continuación de #794 — antes
    // vivían como env() runtime fuera de config/, bloqueando `config:cache`).
    'asterisk' => [
        'odbc_dsn'            => env('ASTERISK_ODBC_DSN', 'asterisk-connector'),
        'bind_sip'            => env('ASTERISK_BIND_SIP', '0.0.0.0'),
        'external_media'      => env('ASTERISK_EXTERNAL_MEDIA', ''),
        'external_signaling'  => env('ASTERISK_EXTERNAL_SIGNALING', ''),
        'local_net'           => env('ASTERISK_LOCAL_NET', ''),
        'rtp_start'           => env('ASTERISK_RTP_START', 10000),
        'rtp_end'             => env('ASTERISK_RTP_END', 20000),
        'ami_permit'          => env('ASTERISK_AMI_PERMIT', '127.0.0.1/255.255.255.255'),
    ],

    // MegaVoz Fase 3 — grabación de llamadas de la cola. Directorio compartido
    // entre Asterisk (usuario `asterisk`, escribe con MixMonitor) y MegaISP
    // (usuario `www-data`, lee/purga) — mismo patrón de permisos de grupo que
    // `asterisk.generados_dir` más arriba, aplicado por el provisionador.
    'grabaciones' => [
        'dir'            => env('MEGAISP_ASTERISK_GRABACIONES_DIR', '/var/lib/megaisp/grabaciones'),
        'retencion_dias' => (int) env('MEGAVOZ_GRABACIONES_RETENCION_DIAS', 90),
    ],

    // MegaVoz Fase 6 — dónde escucha el daemon del motor de voz
    // (voip:bot-voz-escuchar). Debe coincidir con lo que arranca el propio
    // comando (--host/--port) y con lo que este mismo valor le manda a
    // AudioSocket() en el dialplan generado — DialplanGeneratorService lee
    // este MISMO config, una sola fuente de verdad.
    'bot_voz' => [
        'host' => env('MEGAVOZ_BOT_VOZ_HOST', '127.0.0.1'),
        'port' => (int) env('MEGAVOZ_BOT_VOZ_PORT', 9099),
    ],

    // MegaVoz — servidor TURN (coturn, 25-sep-2026) para el mini-teléfono
    // WebRTC. Sin esto, el navegador solo reúne candidatos ICE "host"
    // (locales) — confirmado en vivo con `rtp set debug on` que Asterisk
    // termina mandando el audio a una IP privada inalcanzable. El STUN
    // simple (Google) sí deja que el navegador encuentre su propia IP
    // pública (verificado con una prueba dedicada), pero eso solo no basta
    // para que la llamada conecte de forma confiable — un TURN (relay)
    // propio es la pieza que faltaba. `MiTelefonoController::credenciales()`
    // reenvía esto al navegador — la contraseña NUNCA va hardcodeada en el
    // .vue (viola la convención de secretos solo-en-.env), viaja por este
    // mismo endpoint que ya manda el secret SIP de cada quien.
    'turn' => [
        'url'        => env('MEGAVOZ_TURN_URL', 'turn:38.123.192.199:3478'),
        'username'   => env('MEGAVOZ_TURN_USERNAME'),
        'credential' => env('MEGAVOZ_TURN_PASSWORD'),
    ],

    // MegaVoz Fase 5 — de dónde importa megavoz:importar-queue-log. Es el log
    // NATIVO de app_queue (formato propio de Asterisk, no realtime) — única
    // fuente confiable para KPIs por agente.
    'queue_log_path' => env('MEGAISP_ASTERISK_QUEUE_LOG', '/var/log/asterisk/queue_log'),
];
