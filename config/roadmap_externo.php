<?php

/*
|--------------------------------------------------------------------------
| Acceso externo a la Hoja de Ruta — Circuito de Mejora Continua
|--------------------------------------------------------------------------
|
| Tokens de acceso sin login para Claude Cowork (corre fuera de esta red).
| Los valores viven SOLO en .env (nunca en git). Se leen vía config() para
| que `config:cache` no rompa el acceso en producción.
|
| SEGURIDAD: el token viaja en el path → se filtra al access log de nginx.
| Mitigación (enmascarado del log) + ROTACIÓN PERIÓDICA obligatoria (cada 90 días
| o ante sospecha): ver docs/circuito-seguridad-tokens.md.
|
*/

return [
    // Token de solo lectura: GET /api/roadmap-externo/{token}
    'read_token'  => env('ROADMAP_EXTERNAL_READ_TOKEN'),

    // Token de escritura acotada: POST /api/roadmap-externo/{token}/item/{id}
    'write_token' => env('ROADMAP_EXTERNAL_WRITE_TOKEN'),

    /*
    | TORRE V2 — token de ESCRITURA EXTENDIDA: crear items y agregar reportes al historial.
    |
    | Es un scope más ancho que el write_token (que solo toca 3 campos de un item que ya existe):
    | crear items alimenta la cola de trabajo del circuito. Por eso admite token PROPIO y rotable
    | por separado, para poder revocarle a un integrador la capacidad de crear sin quitarle la de
    | revisar.
    |
    | Si no se define, CAE al write_token: así la extensión funciona el día uno sin tocar el .env
    | de nadie y sin romper a Cowork. Para separarlos, agregar ROADMAP_EXTERNAL_CREATE_TOKEN al
    | .env (mismo procedimiento de rotación que los otros: docs/circuito-seguridad-tokens.md).
    */
    'create_token' => env('ROADMAP_EXTERNAL_CREATE_TOKEN') ?: env('ROADMAP_EXTERNAL_WRITE_TOKEN'),

    // Tope de items que la vía externa puede crear por día. Freno de mano contra un lazo
    // descontrolado del otro lado (un agente en bucle llenando la Hoja de Ruta).
    'max_items_dia' => (int) env('ROADMAP_EXTERNAL_MAX_ITEMS_DIA', 60),

    // Límite de peticiones por minuto (rate limit) para cada verbo.
    'rate_read'   => (int) env('ROADMAP_EXTERNAL_RATE_READ', 60),
    'rate_write'  => (int) env('ROADMAP_EXTERNAL_RATE_WRITE', 30),
];
