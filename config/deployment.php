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
 *   type: 'github_release'   — crea/actualiza un GitHub Release con las mejoras de la versión
 *   type: 'backup'           — respaldo de la BD ANTES de publicar (streaming, en el worker)
 */

// Allowlist de artefactos que un release legítimo puede commitear = salidas de Laravel
// Mix bajo public/ que SÍ están trackeadas en git (ver webpack.mix.js). ÚNICA fuente de
// verdad: la consume el paso git_staging_gate y el comando de git_add. NUNCA `git add -A`.
// NOTA: public/mix-manifest.json NO va aquí — está gitignored (.gitignore:21) y `git add`
// sobre un archivo ignorado devuelve exit 1, lo que rompería el paso crítico git_add.
$releaseArtifacts = [
    'public/js',             // app.js + chunks async (js/NNN.js) + *.LICENSE.txt
    'public/css',            // app.css
    'public/chart.js',       // copia estática (mix.copy) — solo cambia en bump de chart.js
    'public/images/vendor',  // assets de leaflet extraídos por webpack — solo en bump de esas libs
];

return [

    'steps' => [
        [
            'key'      => 'db_backup',
            'name'     => 'Respaldo de base de datos ({version})',
            'type'     => 'backup',
            // Generoso: el dump tardó ~115s y crecerá. El kill real lo gobierna el
            // timeout del proceso mysqldump (3600s) y el de DeployJob (1800s).
            'timeout'  => 1200,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'git_check_secrets',
            'name'     => 'Verificar archivos sensibles',
            'type'     => 'secret_check',
            'timeout'  => 15,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'git_staging_gate',
            'name'     => 'Validar staging (allowlist de artefactos)',
            'type'     => 'staging_gate',
            'timeout'  => 15,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'git_add',
            'name'     => 'Preparar archivos (git add)',
            // Allowlist explícito desde $releaseArtifacts — NUNCA `git add -A` (barría
            // archivos ajenos/sensibles del working tree al commit de release).
            'command'  => 'git add ' . implode(' ', $releaseArtifacts),
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
            // Tag ANOTADO (-a -m): `git push --follow-tags` solo empuja tags anotados.
            // Con tag lightweight (git tag {version}) el tag nunca llegaba a origin.
            'command'  => 'git tag -a {version} -m "Release {version}"',
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
            'key'      => 'github_release',
            'name'     => 'Crear GitHub Release con mejoras',
            'type'     => 'github_release',
            'timeout'  => 30,
            'critical' => false,
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

    // Allowlist de artefactos del release (definido arriba). Lo consume el gate
    // (executeStagingGate) y el comando de git_add. Única fuente de verdad.
    'release_artifacts' => $releaseArtifacts,

    'git' => [
        'author_name'  => env('GIT_AUTHOR_NAME', 'MegaISP Release'),
        'author_email' => env('GIT_AUTHOR_EMAIL', 'releases@meganet.com'),
    ],

    // Integración con GitHub API (para publicar releases y detectar actualizaciones)
    'github' => [
        'token' => env('GITHUB_TOKEN', ''),
        'repo'  => env('GITHUB_REPO', 'inving9378/ClaudeMegaISP'),
    ],

    // URL base del servidor remoto donde se desplegará (ej: http://192.168.105.11)
    // Dejar vacío para omitir el paso remote_deploy
    'remote_url'     => env('DEPLOY_REMOTE_URL', ''),

    // Token secreto compartido entre local y remoto para autenticar el webhook
    'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET', ''),

];
