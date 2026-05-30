<?php

namespace App\Modules\Contracts;

/**
 * Contrato de ciclo de vida de cada módulo.
 *
 * Getters leen de module.json (vía manifest()). Los hooks install/upgrade/uninstall
 * son para lógica extra del módulo (seeders propios, etc.); la infraestructura
 * genérica (migraciones, permisos Spatie, registro de menú/config/admin) la
 * maneja ModuleLifecycleService, no esta clase.
 */
abstract class ModuleDefinition
{
    private array $manifestData = [];

    // ── Identidad ────────────────────────────────────────────────────────────

    abstract public function moduleDir(): string;

    public function manifest(): array
    {
        if (empty($this->manifestData)) {
            $path = $this->moduleDir() . '/module.json';
            $this->manifestData = file_exists($path)
                ? (json_decode(file_get_contents($path), true) ?? [])
                : [];
        }
        return $this->manifestData;
    }

    public function slug(): string         { return $this->manifest()['slug'] ?? ''; }
    public function name(): string         { return $this->manifest()['name'] ?? ''; }
    public function version(): string      { return $this->manifest()['version'] ?? '0.1.0'; }
    public function type(): string         { return $this->manifest()['type'] ?? 'addon'; }
    public function dependencies(): array  { return $this->manifest()['dependencies'] ?? []; }

    // ── Áreas declarativas (leídas del manifiesto) ────────────────────────

    public function menu(): array              { return $this->manifest()['menu'] ?? []; }
    public function adminCards(): array        { return $this->manifest()['admin_cards'] ?? []; }
    public function configSections(): array    { return $this->manifest()['config_sections'] ?? []; }
    public function apiEndpoints(): array      { return $this->manifest()['api_endpoints'] ?? []; }
    public function ai(): array                { return $this->manifest()['ai'] ?? []; }
    public function clientTab(): ?array        { return $this->manifest()['client_tab'] ?? null; }
    public function serviceType(): ?array      { return $this->manifest()['service_type'] ?? null; }

    // ── Hooks de ciclo de vida ───────────────────────────────────────────────
    // ModuleLifecycleService los llama antes/después de su propia infraestructura.

    /** Lógica extra post-instalación (ej. seeders propios del módulo). */
    public function install(): void {}

    /**
     * Lógica extra al actualizar de $fromVersion a $toVersion.
     * Las migraciones delta las corre ModuleLifecycleService automáticamente.
     */
    public function upgrade(string $fromVersion, string $toVersion): void {}

    /** Lógica extra pre-desinstalación (ej. limpiar cache propio, jobs pendientes). */
    public function uninstall(bool $keepData = false): void {}
}
