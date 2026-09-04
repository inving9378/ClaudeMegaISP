<?php

/**
 * Documentación Corporativa — repositorio documental (Fase 2a, item roadmap #767).
 */
return [
    'documentos' => [
        // 20 MB por archivo, configurable sin redeploy vía .env.
        'max_bytes' => (int) env('DC_DOCUMENTOS_MAX_BYTES', 20 * 1024 * 1024),

        'extensiones_permitidas' => [
            'pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'xlsx', 'csv', 'zip',
        ],
    ],
];
