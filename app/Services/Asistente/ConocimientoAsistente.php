<?php

namespace App\Services\Asistente;

use App\Modules\Core\ModuleManager\Services\ModuleRegistry;

/**
 * Lo que JARVIS "sabe" del sistema: instrucciones, guía, enlaces, límites y ajustes de memoria.
 * Todo vive en archivos versionados en resources/asistente/ (MD para lo que se lee, JSON para lo
 * que se configura), así que cambiar el comportamiento de JARVIS no requiere tocar código.
 */
class ConocimientoAsistente
{
    private array $cache = [];

    public function __construct(private ModuleRegistry $registry)
    {
    }

    public function ruta(string $relativa = ''): string
    {
        return resource_path('asistente' . ($relativa !== '' ? '/' . ltrim($relativa, '/') : ''));
    }

    /** Instrucciones fijas + mapa del sistema. Es la parte estable del arranque de cada mensaje. */
    public function arranqueFijo(): string
    {
        return trim($this->leer('system-prompt.md')) . "\n\n" . trim($this->leer('guia/_indice.md'));
    }

    /** Temas de la guía que se pueden pedir con consultar_guia. */
    public function temas(): array
    {
        $temas = [];
        foreach (glob($this->ruta('guia/*.md')) ?: [] as $archivo) {
            $nombre = basename($archivo, '.md');
            if ($nombre !== '_indice') {
                $temas[] = $nombre;
            }
        }
        $temas[] = 'modulos';
        sort($temas);

        return $temas;
    }

    public function guia(string $tema): ?string
    {
        $tema = strtolower(trim($tema));
        if ($tema === 'modulos') {
            return $this->guiaModulos();
        }
        if (!preg_match('/^[a-z0-9-]+$/', $tema) || !is_file($this->ruta("guia/{$tema}.md"))) {
            return null;
        }

        return $this->leer("guia/{$tema}.md");
    }

    /** Qué hace cada módulo, según lo que el propio módulo declara (sección "ai" de su module.json). */
    private function guiaModulos(): string
    {
        $lineas = ['# Módulos del sistema y para qué sirven', ''];
        foreach ($this->registry->getAiContext() as $mod) {
            $nombre = $mod['_module'] ?? 'módulo';
            $lineas[] = '## ' . preg_replace('/^(addon|core)-/', '', $nombre);
            if (!empty($mod['knowledge'])) {
                $lineas[] = trim((string) $mod['knowledge']);
            }
            if (!empty($mod['example_intents'])) {
                $lineas[] = 'Ejemplos de lo que se hace ahí: ' . implode('; ', array_slice($mod['example_intents'], 0, 4)) . '.';
            }
            $lineas[] = '';
        }

        return implode("\n", $lineas);
    }

    public function enlace(string $clave, int|string|null $id = null): ?string
    {
        $plantilla = $this->json('enlaces.json')[$clave] ?? null;
        if (!is_string($plantilla) || !str_starts_with($plantilla, '/')) {
            return null;
        }

        return str_replace('{id}', (string) $id, $plantilla);
    }

    public function limites(): array
    {
        return $this->json('limites.json');
    }

    /** 'permitida' | 'confirmar' | 'bloqueada' para una herramienta, con el default según su tipo. */
    public function modoDe(string $herramienta, bool $esCambio): string
    {
        $modo = $this->limites()['herramientas'][$herramienta] ?? null;

        return in_array($modo, ['permitida', 'confirmar', 'bloqueada'], true) ? $modo : ($esCambio ? 'confirmar' : 'permitida');
    }

    public function limite(string $clave, int $default): int
    {
        return (int) ($this->limites()[$clave] ?? $default);
    }

    public function memoria(string $clave, int $default): int
    {
        return (int) ($this->json('memoria.json')[$clave] ?? $default);
    }

    private function leer(string $relativa): string
    {
        $archivo = $this->ruta($relativa);
        if (!is_file($archivo)) {
            return '';
        }

        return $this->cache["md:{$relativa}"] ??= (string) file_get_contents($archivo);
    }

    private function json(string $relativa): array
    {
        if (!isset($this->cache["json:{$relativa}"])) {
            $datos = json_decode($this->leer($relativa), true);
            $this->cache["json:{$relativa}"] = is_array($datos) ? $datos : [];
        }

        return $this->cache["json:{$relativa}"];
    }
}
