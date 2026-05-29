<?php

namespace App\Modules\Addons\Marketing\Services\AgentTools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueryPlansTool
{
    public static function schema(): array
    {
        return [
            'name'         => 'query_plans',
            'description'  => 'Consulta el catálogo de planes de internet disponibles. Úsala cuando el cliente pregunte por precios, velocidades o disponibilidad de planes.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'zone'      => ['type' => ['string', 'null'], 'description' => 'Zona o ciudad para filtrar planes (opcional)'],
                    'max_price' => ['type' => ['number', 'null'], 'description' => 'Precio máximo mensual en MXN (opcional)'],
                    'min_speed' => ['type' => ['number', 'null'], 'description' => 'Velocidad mínima en Mbps (opcional)'],
                ],
            ],
        ];
    }

    public function execute(?string $zone = null, ?int $maxPrice = null, ?int $minSpeed = null): array
    {
        // Check for internet plans table (Medussa uses plan_internets)
        $table = $this->detectPlanTable();
        if (!$table) {
            return [
                'success' => false,
                'error'   => 'Catálogo de planes no configurado en este sistema.',
                'plans'   => [],
            ];
        }

        try {
            $query = DB::table($table)->where('active', 1)->limit(5);

            // Apply optional filters if columns exist
            $cols = array_column(DB::select("DESCRIBE {$table}"), 'Field');

            if ($maxPrice && in_array('price', $cols)) {
                $query->where('price', '<=', $maxPrice);
            }
            if ($minSpeed && in_array('speed', $cols)) {
                $query->where('speed', '>=', $minSpeed);
            }

            $rows = $query->get();

            $plans = $rows->map(function ($row) {
                $r = (array) $row;
                return [
                    'id'          => $r['id'] ?? null,
                    'name'        => $r['name'] ?? $r['title'] ?? 'Plan',
                    'speed_mbps'  => $r['speed'] ?? $r['download_speed'] ?? null,
                    'price'       => $r['price'] ?? $r['cost'] ?? null,
                    'currency'    => 'MXN',
                    'description' => $r['description'] ?? '',
                ];
            })->values()->toArray();

            return ['success' => true, 'plans' => $plans, 'count' => count($plans)];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error consultando planes: ' . $e->getMessage(), 'plans' => []];
        }
    }

    private function detectPlanTable(): ?string
    {
        $candidates = ['plan_internets', 'internet_plans', 'plans', 'plan_internet_clients'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                return $t;
            }
        }
        return null;
    }
}
