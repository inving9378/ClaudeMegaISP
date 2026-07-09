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

    // Límite de peticiones por minuto (rate limit) para cada verbo.
    'rate_read'   => (int) env('ROADMAP_EXTERNAL_RATE_READ', 60),
    'rate_write'  => (int) env('ROADMAP_EXTERNAL_RATE_WRITE', 30),
];
