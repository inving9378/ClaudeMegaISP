<?php

/*
 * Pasos del pipeline de release.
 *
 * Placeholders disponibles en `command`:
 *   {version}  — número de versión  (ej: "2026.06.09.2")
 *   {title}    — título de la release (ej: "Mejoras en facturación")
 *   {message}  — commit message completo
 *
 * Flags especiales:
 *   skip_on_nothing_to_commit — exit-code 1 en este paso = skip, no error (para git commit)
 *   skip_if_tag_exists        — si el tag ya existe localmente, el paso se marca skip
 *   skip_if_no_remote         — omite el paso si DEPLOY_REMOTE_URL no está configurado
 *   type: 'http'              — el paso llama al webhook del servidor remoto (no es shell)
 */

return [

    'steps' => [
        [
            'key'      => 'git_add',
            'name'     => 'Preparar archivos (git add)',
            'command'  => 'git add -A',
            'timeout'  => 30,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'git_commit',
            'name'     => 'Crear commit de release',
            'command'  => 'git commit -m "{message}"',
            'timeout'  => 30,
            'critical' => true,
            'enabled'  => true,
            'skip_on_nothing_to_commit' => true,
        ],
        [
            'key'      => 'git_tag',
            'name'     => 'Crear tag de versión ({version})',
            'command'  => 'git tag {version}',
            'timeout'  => 10,
            'critical' => true,
            'enabled'  => true,
            'skip_if_tag_exists' => true,
        ],
        [
            'key'      => 'git_push',
            'name'     => 'Publicar en GitHub (push + tag)',
            'command'  => 'git push origin main --follow-tags',
            'timeout'  => 180,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'               => 'remote_deploy',
            'name'              => 'Desplegar en servidor remoto',
            'type'              => 'http',
            'timeout'           => 700,
            'critical'          => false,
            'enabled'           => true,
            'skip_if_no_remote' => true,
        ],
    ],

    'git' => [
        'author_name'  => env('GIT_AUTHOR_NAME', 'MegaISP Release'),
        'author_email' => env('GIT_AUTHOR_EMAIL', 'releases@meganet.com'),
    ],

    // URL base del servidor remoto donde se desplegará (ej: http://192.168.105.11)
    // Dejar vacío para omitir el paso remote_deploy
    'remote_url'     => env('DEPLOY_REMOTE_URL', ''),

    // Token secreto compartido entre local y remoto para autenticar el webhook
    'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET', ''),

];
