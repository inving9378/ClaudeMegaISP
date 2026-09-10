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
];
