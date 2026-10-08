<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use App\Services\Asistente\Entrenamiento\FuentesSistema;
use App\Services\Asistente\Entrenamiento\GeneradorGuias;
use App\Services\Asistente\RegistroHerramientas;
use Illuminate\Console\Command;

/**
 * Entrena a JARVIS (el robotsito): escribe una guía por módulo en lenguaje de usuario con la IA
 * asignada en Integraciones (clave asistente.entrenamiento; si no tiene, usa la del chat).
 * Solo regenera las guías cuyo material cambió. Las guías quedan en resources/asistente/guia/
 * modulos/ y se versionan: en producción no hace falta correrlo.
 */
class AsistenteAprenderCommand extends Command
{
    protected $signature = 'asistente:aprender
        {modulos?* : Módulos a regenerar (nombre del archivo en docs/modulos). Sin nada: todos}
        {--forzar : Regenerar aunque su material no haya cambiado}
        {--ver : Solo mostrar el material que se le daría a la IA, sin llamarla}';

    protected $description = 'JARVIS: genera sus guías por módulo a partir de la documentación, el menú y las pantallas de alta';

    public function handle(RegistroHerramientas $registro): int
    {
        $login = (string) ((json_decode((string) @file_get_contents(resource_path('asistente/entrenamiento.json')), true) ?: [])['usuario_menu'] ?? '');
        $usuario = User::where('login_user', $login)->first();
        if (!$usuario) {
            $this->error("No existe el usuario «{$login}» (usuario_menu en resources/asistente/entrenamiento.json): con él se arma el menú completo.");

            return self::FAILURE;
        }

        $generador = new GeneradorGuias(new FuentesSistema($usuario), $registro);
        $modulos = $this->argument('modulos') ?: $generador->modulos();
        $desconocidos = array_diff($modulos, $generador->modulos());
        if ($desconocidos) {
            $this->error('No hay guía para: ' . implode(', ', $desconocidos) . '. Opciones: ' . implode(', ', $generador->modulos()));

            return self::FAILURE;
        }

        if ($this->option('ver')) {
            foreach ($modulos as $slug) {
                $m = $generador->material($slug);
                $this->line("<info>{$slug}</info>: documentación " . mb_strlen($m['documentacion']) . ' car. · manual ' . mb_strlen($m['manual'])
                    . ' car. · ' . count($m['pantallas']) . ' pantallas · ' . count($m['altas']) . ' altas'
                    . ($m['nota'] !== '' ? ' · con nota' : ''));
            }

            return self::SUCCESS;
        }

        $errores = 0;
        foreach ($modulos as $slug) {
            try {
                $r = $generador->generar($slug, (bool) $this->option('forzar'));
            } catch (\Throwable $e) {
                $r = ['estado' => 'error', 'detalle' => $e->getMessage()];
            }
            $errores += $r['estado'] === 'error' ? 1 : 0;
            $color = ['nueva' => 'info', 'igual' => 'comment', 'error' => 'error'][$r['estado']] ?? 'line';
            $this->line("<{$color}>{$slug}</{$color}> — {$r['estado']}: {$r['detalle']}");
        }

        $n = $generador->escribirIndice();
        $this->info("Índice de JARVIS actualizado con {$n} guías.");

        return $errores ? self::FAILURE : self::SUCCESS;
    }
}
