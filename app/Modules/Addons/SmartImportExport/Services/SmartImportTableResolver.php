<?php

namespace App\Modules\Addons\SmartImportExport\Services;

use Illuminate\Support\Arr;

class SmartImportTableResolver
{
    private SmartImportModelDiscovery $modelDiscovery;

    public function __construct(?SmartImportModelDiscovery $modelDiscovery = null)
    {
        $this->modelDiscovery = $modelDiscovery ?? new SmartImportModelDiscovery();
    }

    public function resolve(string $table, ?array $legacyRule = null): array
    {
        $override = $this->overrideFor($table);
        $model = $this->resolveModel($table, $override, $legacyRule);
        $hasModel = is_string($model) && class_exists($model);
        $overrideMode = $override['mode'] ?? null;
        $legacyMode = $legacyRule['mode'] ?? null;
        $strict = $overrideMode === 'strict_model'
            || (bool) ($override['strict_model'] ?? false)
            || in_array($table, config('smart_import.strict_tables', []), true);

        $mode = match ($overrideMode) {
            'raw' => 'raw',
            'model', 'strict_model' => $hasModel ? 'model' : 'raw',
            default => $hasModel ? 'model' : (($legacyMode === 'raw') ? 'raw' : 'raw'),
        };

        $descriptorSource = $this->descriptorSource($table, $override, $model, $legacyRule);
        $module = $override['module']
            ?? ($legacyRule['module'] ?? null)
            ?? $this->modelDiscovery->moduleLabelForModel($model)
            ?? $this->moduleLabelFromTable($table);

        $conflictKeys = $override['conflict_keys'] ?? ($legacyRule['conflict_keys'] ?? []);
        $conflictSource = array_key_exists('conflict_keys', $override)
            ? 'override_conflict_keys'
            : (is_array($legacyRule) && array_key_exists('conflict_keys', $legacyRule) ? 'legacy_conflict_keys' : null);
        $modelCandidates = $this->modelDiscovery->modelsForTable($table);

        return [
            'known'              => $descriptorSource !== 'schema',
            'module'             => $module,
            'model'              => $hasModel ? $model : null,
            'mode'               => $mode,
            'raw_mode'           => $mode === 'raw' || !$hasModel,
            'has_model'          => $hasModel,
            'strict_model'       => $strict,
            'descriptor_source'  => $descriptorSource,
            'model_candidates'   => $modelCandidates,
            'conflict_rule'      => [
                'conflict_keys'        => array_values(array_filter(Arr::wrap($conflictKeys))),
                'conflict_keys_source' => $conflictSource,
                'identity_priority'    => $override['identity_priority'] ?? null,
            ],
            'normalizers'        => array_values(array_filter(Arr::wrap($override['normalizers'] ?? []))),
            'warnings'           => $this->descriptorWarnings($descriptorSource, $hasModel, $mode, $modelCandidates),
        ];
    }

    public function hasDescriptor(string $table, ?array $legacyRule = null): bool
    {
        return $this->overrideFor($table) !== []
            || $this->modelDiscovery->hasTable($table)
            || is_array($legacyRule);
    }

    /**
     * @return array<string, string[]>
     */
    public function conflictKeyHints(array $legacyMap): array
    {
        $hints = [];
        foreach ($legacyMap as $table => $rule) {
            $keys = array_values(array_filter($rule['conflict_keys'] ?? []));
            if ($keys !== []) {
                $hints[$table] = $keys;
            }
        }

        foreach (config('smart_import.overrides', []) as $table => $override) {
            $keys = array_values(array_filter($override['conflict_keys'] ?? []));
            if ($keys !== []) {
                $hints[$table] = $keys;
            }
        }

        return $hints;
    }

    private function overrideFor(string $table): array
    {
        $overrides = config('smart_import.overrides', []);
        $override = $overrides[$table] ?? [];

        return is_array($override) ? $override : [];
    }

    private function resolveModel(string $table, array $override, ?array $legacyRule): ?string
    {
        $overrideModel = $override['model'] ?? null;
        if (is_string($overrideModel) && class_exists($overrideModel)) {
            return $overrideModel;
        }

        $legacyModel = $legacyRule['model'] ?? null;
        $legacyModel = is_string($legacyModel) && class_exists($legacyModel) ? $legacyModel : null;

        $discovered = $this->modelDiscovery->modelForTable($table, $legacyModel);
        if ($discovered) {
            return $discovered;
        }

        if ($legacyModel) {
            return $legacyModel;
        }

        return null;
    }

    private function descriptorSource(string $table, array $override, ?string $model, ?array $legacyRule): string
    {
        if ($override !== []) {
            return 'override';
        }

        if ($model !== null && in_array($model, $this->modelDiscovery->modelsForTable($table), true)) {
            return 'model_discovery';
        }

        if (is_array($legacyRule)) {
            return 'legacy_map';
        }

        return 'schema';
    }

    private function descriptorWarnings(string $descriptorSource, bool $hasModel, string $mode, array $modelCandidates): array
    {
        $warnings = [];
        if (!$hasModel) {
            $warnings[] = 'sin_modelo_asociado';
        }
        if ($descriptorSource === 'schema') {
            $warnings[] = 'sin_regla_explicita';
        }
        if ($mode === 'raw' && $hasModel) {
            $warnings[] = 'modelo_disponible_pero_forzado_raw';
        }
        if (count($modelCandidates) > 1) {
            $warnings[] = 'multiples_modelos_para_tabla';
        }

        return $warnings;
    }

    private function moduleLabelFromTable(string $table): string
    {
        $prefix = str($table)->before('_')->toString();
        if ($prefix === '' || $prefix === $table) {
            return 'Sin clasificar';
        }

        return str($prefix)->headline()->toString();
    }
}
