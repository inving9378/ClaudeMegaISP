<?php

/*
 * Pasos del pipeline de deploy.
 * Agregar/reordenar/deshabilitar aquí sin tocar código PHP.
 *
 * Campos por paso:
 *   key      — identificador único (snake_case)
 *   name     — label legible para la UI
 *   command  — string de shell o array de argumentos
 *   timeout  — segundos máximos para este paso
 *   critical — si true y falla, el deploy se detiene inmediatamente
 *   enabled  — false = el paso se omite (útil para rollouts graduales)
 */

return [

    'steps' => [
        [
            'key'      => 'git_pull',
            'name'     => 'Obtener cambios (git pull)',
            'command'  => 'git pull origin main',
            'timeout'  => 60,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'composer',
            'name'     => 'Instalar dependencias PHP',
            'command'  => 'composer install --no-dev --optimize-autoloader --no-interaction',
            'timeout'  => 120,
            'critical' => true,
            'enabled'  => true,
        ],
        [
            'key'      => 'npm_build',
            'name'     => 'Compilar assets (npm run prod)',
            'command'  => 'npm run prod',
            'timeout'  => 240,
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

];
