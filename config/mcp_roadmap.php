<?php

/*
|--------------------------------------------------------------------------
| Conector MCP de la Hoja de Ruta — Circuito de Mejora Continua
|--------------------------------------------------------------------------
|
| Servidor MCP remoto (transporte Streamable HTTP / JSON-RPC 2.0) que expone la
| Hoja de Ruta como "custom connector" de claude.ai para Claude Cowork. Se agrega
| en claude.ai pegando la URL CON el secreto en el path (sin OAuth): claude.ai no
| permite fijar un bearer estático, así que el secreto viaja en el path — mismo
| patrón que `roadmap-externo`. Comparación timing-safe, rate limit y auditoría.
|
| El secreto vive SOLO en .env (nunca en git). Se lee vía config() para que
| `config:cache` no rompa el acceso.
|
*/

return [
    // Secreto del conector: POST/GET https://dev.meganett.com.mx/mcp/{secret}
    'secret' => env('MCP_ROADMAP_SECRET'),

    // Límite de peticiones por minuto.
    'rate' => (int) env('MCP_ROADMAP_RATE', 120),

    // Versión del protocolo MCP que anunciamos por defecto (si el cliente no negocia otra).
    'protocol_version' => '2025-06-18',

    // Metadatos del servidor devueltos en initialize.
    'server_name' => 'megaisp-roadmap',
    'server_version' => '1.0.0',
];
