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
 */

return [

    'steps' => [
        [
            'key'      => 'npm_build',
            'name'     => 'Compilar assets (npm run prod)',
            'command'  => 'npm run prod',
            'timeout'  => 240,
            'critical' => true,
            'enabled'  => true,
        ],
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
            'timeout'  => 60,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'migrate',
            'name'     => 'Ejecutar migraciones',
            'command'  => 'php artisan migrate --force',
            'timeout'  => 60,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'optimize',
            'name'     => 'Optimizar cachés',
            'command'  => 'php artisan optimize',
            'timeout'  => 30,
            'critical' => false,
            'enabled'  => true,
        ],
        [
            'key'      => 'queue_restart',
            'name'     => 'Reiniciar workers de cola',
            'command'  => 'php artisan queue:restart',
            'timeout'  => 10,
            'critical' => false,
            'enabled'  => true,
        ],
    ],

    'git' => [
        'author_name'  => env('GIT_AUTHOR_NAME', 'MegaISP Release'),
        'author_email' => env('GIT_AUTHOR_EMAIL', 'releases@meganet.com'),
    ],

];
