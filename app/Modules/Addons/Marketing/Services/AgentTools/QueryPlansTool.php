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
        $table = $this->detectPlanTable();
        if (!$table) {
            return [
                'success' => false,
                'error'   => 'Catálogo de planes no configurado en este sistema.',
                'plans'   => [],
            ];
        }

        try {
            $map  = $this->columnMap($table);
            $cols = array_column(DB::select("DESCRIBE {$table}"), 'Field');

            $query = DB::table($table);

            // Solo registros activos según la tabla lo permita
            if ($map['active_col'] && in_array($map['active_col'], $cols)) {
                $query->where($map['active_col'], 1);
            }

            // `bundles` mezcla servicios, descuentos, pagos y reembolsos en una sola tabla:
            // quedarse solo con los "Servicio" reales y con precio > 0 (excluye p.ej. "Administracion paquete").
            if ($table === 'bundles') {
                $query->whereIn('transaction_category', ['Servicio', ''])
                      ->where('price', '>', 0);
            }

            if ($maxPrice && in_array($map['price_col'], $cols)) {
                $query->where($map['price_col'], '<=', $maxPrice);
            }
            // min_speed solo se filtra en SQL si la tabla tiene columna de velocidad;
            // en `bundles` la velocidad va embebida en el nombre y se filtra en PHP más abajo.
            if ($minSpeed && $map['speed_col'] && in_array($map['speed_col'], $cols)) {
                $query->where($map['speed_col'], '>=', $minSpeed);
            }

            // Orden por precio ascendente: una consulta genérica devuelve primero los planes
            // residenciales económicos, no los enlaces dedicados/empresariales.
            $rows = $query->orderBy($map['price_col'])->limit(20)->get();

            $plans = $rows->map(function ($row) use ($map) {
                $r     = (array) $row;
                $name  = $r[$map['name_col']] ?? 'Plan';
                $desc  = $map['desc_col'] ? ($r[$map['desc_col']] ?? '') : '';
                $speed = ($map['speed_col'] && isset($r[$map['speed_col']]) && $r[$map['speed_col']] !== null)
                    ? (int) $r[$map['speed_col']]
                    : $this->parseSpeed($name);

                return [
                    'id'          => $r['id'] ?? null,
                    'name'        => $name,
                    'speed_mbps'  => $speed,
                    'price'       => isset($r[$map['price_col']]) ? (float) $r[$map['price_col']] : null,
                    'currency'    => 'MXN',
                    'description' => $desc,
                ];
            });

            // Filtro de velocidad mínima cuando la velocidad se dedujo del nombre
            if ($minSpeed && !$map['speed_col']) {
                $plans = $plans->filter(fn ($p) => $p['speed_mbps'] && $p['speed_mbps'] >= $minSpeed);
            }

            $plans = $plans->take(5)->values()->toArray();

            return ['success' => true, 'plans' => $plans, 'count' => count($plans)];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error consultando planes: ' . $e->getMessage(), 'plans' => []];
        }
    }

    /**
     * Mapeo de columnas por tabla. La velocidad en Mbps no es columna en `bundles`
     * (va en el nombre comercial), por eso `speed_col` es null ahí.
     */
    private function columnMap(string $table): array
    {
        return match ($table) {
            'bundles' => [
                'name_col'   => 'title',
                'price_col'  => 'price',
                'speed_col'  => null,
                'desc_col'   => 'service_description',
                'active_col' => null,
            ],
            default => [ // tablas normalizadas tipo Medussa (plan_internets, internet_plans, plans)
                'name_col'   => 'name',
                'price_col'  => 'price',
                'speed_col'  => 'speed',
                'desc_col'   => 'description',
                'active_col' => 'active',
            ],
        };
    }

    /**
     * Extrae la velocidad en Mbps del nombre comercial.
     * Ej: "Internet 150+", "100mb Promo 70", "50+tel empleados".
     * Conservador a propósito: si no hay una señal clara de velocidad devuelve null
     * (mejor sin dato que inventar una velocidad — p.ej. confundir el precio con Mbps).
     */
    private function parseSpeed(string $name): ?int
    {
        // Número seguido de mb/mbps/mega o de "+" (convención local "150+")
        if (preg_match('/(\d{2,4})\s*(?:mbps|mb|mega|\+)/i', $name, $m)) {
            return (int) $m[1];
        }
        // "Internet 100 ...", "Dedicado 200MB" (número justo tras la palabra del producto)
        if (preg_match('/\b(?:internet|dedicado|simetrico|plan)\s+(\d{2,4})\b/i', $name, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function detectPlanTable(): ?string
    {
        // Tablas normalizadas (Medussa) primero; si no existen, el catálogo real vendible es `bundles`.
        $candidates = ['plan_internets', 'internet_plans', 'plans', 'plan_internet_clients', 'bundles'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                return $t;
            }
        }
        return null;
    }
}
