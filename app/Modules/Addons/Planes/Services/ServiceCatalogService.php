<?php

namespace App\Modules\Addons\Planes\Services;

use App\Modules\Addons\Planes\Models\ContractableService;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;

/**
 * Motor de servicios contratables (Item #006).
 *
 * Fusiona los `service_type` declarados por los módulos activos (autoritativos
 * en capacidades) con la fila de overlay administrable `contractable_services`
 * (precio, etiqueta, activo). Produce el catálogo unificado que consumen los
 * addons que venden servicios (MegaFamilia) y el constructor de paquetes.
 */
class ServiceCatalogService
{
    public function __construct(private ModuleRegistry $registry)
    {
    }

    /**
     * Sincroniza el manifiesto → tabla overlay. Inserta filas para service_types
     * nuevos y refresca capacidades/etiqueta sin pisar el precio configurado por
     * el admin. Devuelve [created, updated].
     */
    public function syncFromManifests(): array
    {
        $created = 0;
        $updated = 0;

        foreach ($this->registry->getServiceTypes() as $st) {
            $key = $st['key'] ?? null;
            if (! $key) {
                continue;
            }

            $row = ContractableService::firstOrNew(['service_type_key' => $key]);
            $isNew = ! $row->exists;

            // Capacidades: el manifiesto manda siempre.
            $row->module_slug         = $st['_module'] ?? $row->module_slug ?? '';
            $row->price_configurable  = (bool) ($st['price_configurable'] ?? false);
            $row->supports_promotions = (bool) ($st['supports_promotions'] ?? false);
            $row->bundleable          = (bool) ($st['bundleable'] ?? false);

            // Etiqueta y precio: solo se inicializan en la primera alta.
            if ($isNew) {
                $row->label  = $st['label'] ?? $key;
                $row->active = true;
                $row->price  = null;
            }

            $row->save();
            $isNew ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Catálogo unificado. Cada entrada combina overlay (si existe) + manifiesto.
     * Las entradas declaradas en manifiesto pero aún no sincronizadas aparecen
     * con `synced => false` para que la UI ofrezca el botón de sincronizar.
     */
    public function catalog(): array
    {
        $overlays = ContractableService::all()->keyBy('service_type_key');
        $catalog  = [];
        $seen     = [];

        foreach ($this->registry->getServiceTypes() as $st) {
            $key = $st['key'] ?? null;
            if (! $key) {
                continue;
            }
            $seen[$key] = true;
            $overlay = $overlays->get($key);

            $catalog[] = [
                'key'                 => $key,
                'label'               => $overlay->label ?? ($st['label'] ?? $key),
                'module_slug'         => $st['_module'] ?? ($overlay->module_slug ?? null),
                'price'               => $overlay?->price !== null ? (float) $overlay->price : null,
                'price_configurable'  => (bool) ($st['price_configurable'] ?? false),
                'supports_promotions' => (bool) ($st['supports_promotions'] ?? false),
                'bundleable'          => (bool) ($st['bundleable'] ?? false),
                'active'              => $overlay ? (bool) $overlay->active : true,
                'synced'             => (bool) $overlay,
            ];
        }

        // Overlays huérfanos (módulo desactivado): se muestran inactivos.
        foreach ($overlays as $key => $overlay) {
            if (isset($seen[$key])) {
                continue;
            }
            $catalog[] = [
                'key'                 => $key,
                'label'               => $overlay->label,
                'module_slug'         => $overlay->module_slug,
                'price'               => $overlay->price !== null ? (float) $overlay->price : null,
                'price_configurable'  => (bool) $overlay->price_configurable,
                'supports_promotions' => (bool) $overlay->supports_promotions,
                'bundleable'          => (bool) $overlay->bundleable,
                'active'              => false,
                'synced'             => true,
                'orphan'             => true,
            ];
        }

        usort($catalog, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $catalog;
    }

    /** Subconjunto contratable y empaquetable: lo que el constructor de bundles ofrece. */
    public function bundleable(): array
    {
        return array_values(array_filter(
            $this->catalog(),
            fn ($e) => $e['bundleable'] && $e['active'] && empty($e['orphan'])
        ));
    }
}
