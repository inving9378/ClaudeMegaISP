<?php

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
];
