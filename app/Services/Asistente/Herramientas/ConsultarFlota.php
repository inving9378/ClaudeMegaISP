<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Services\Tenant\CurrentClientResolver;

/** Consultas de solo lectura sobre la flota: vehículos, documentos por vencer, mantenimientos y gasto. */
class ConsultarFlota extends Herramienta
{
    private const LIMITE = 15;

    public function nombre(): string
    {
        return 'consultar_flota';
    }

    public function descripcion(): string
    {
        return 'Consulta la flota vehicular. consulta="documentos_por_vencer" lista documentos vencidos o que vencen en los '
            . 'próximos N días (default 30); "vehiculos" lista los vehículos; "mantenimientos" los últimos servicios '
            . '(opcional placas); "gasto" suma el gasto en mantenimiento de un año (default el actual).';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'consulta' => ['type' => 'string', 'enum' => ['documentos_por_vencer', 'vehiculos', 'mantenimientos', 'gasto']],
                'dias'     => ['type' => 'integer', 'description' => 'Ventana en días para documentos por vencer (default 30).'],
                'placas'   => ['type' => 'string', 'description' => 'Filtra por placas (parcial).'],
                'anio'     => ['type' => 'integer', 'description' => 'Año para el gasto.'],
            ],
            'required'   => ['consulta'],
        ];
    }

    public function permiso(): string|array|null
    {
        return 'fleet.view';
    }

    public function ejecutar(User $user, array $args): array
    {
        $clientId = app(CurrentClientResolver::class)->resolve();
        $placas = trim((string) ($args['placas'] ?? ''));

        switch ($args['consulta'] ?? '') {
            case 'documentos_por_vencer':
                $dias = max(1, min(365, (int) ($args['dias'] ?? 30)));
                $q = FleetDocument::forClient($clientId)->with('vehicle:id,plates,brand,model,year')
                    ->whereNotNull('expiration_date')->whereDate('expiration_date', '<=', today()->addDays($dias))
                    ->orderBy('expiration_date');
                if ($placas !== '') {
                    $q->whereHas('vehicle', fn ($v) => $v->where('plates', 'like', "%{$placas}%"));
                }
                $total = (clone $q)->count();

                return [
                    'dias' => $dias, 'total' => $total,
                    'documentos' => $q->limit(self::LIMITE)->get()->map(fn ($d) => [
                        'tipo'     => $d->document_type,
                        'vehiculo' => $d->vehicle ? trim("{$d->vehicle->brand} {$d->vehicle->model} {$d->vehicle->year}") . " ({$d->vehicle->plates})" : 'Conductor',
                        'vence'    => $d->expiration_date->format('d/m/Y'),
                        'estado'   => $d->status === 'vencido' ? 'vencido' : 'por vencer',
                    ])->all(),
                    'enlace' => $this->enlace('flotas'),
                ];

            case 'vehiculos':
                $q = FleetVehicle::forClient($clientId)->orderBy('plates');
                if ($placas !== '') {
                    $q->where('plates', 'like', "%{$placas}%");
                }
                $total = (clone $q)->count();

                return [
                    'total' => $total,
                    'vehiculos' => $q->limit(self::LIMITE)->get(['id', 'plates', 'brand', 'model', 'year', 'status'])->map(fn ($v) => [
                        'vehiculo' => trim("{$v->brand} {$v->model} {$v->year}"), 'placas' => $v->plates, 'estado' => $v->status,
                        'enlace'   => $this->enlace('flota_vehiculo', $v->id),
                    ])->all(),
                    'enlace' => $this->enlace('flotas'),
                ];

            case 'mantenimientos':
                $q = FleetMaintenance::with('vehicle:id,plates,brand,model,client_id')
                    ->whereHas('vehicle', fn ($v) => $v->forClient($clientId))->where('is_draft', false)
                    ->orderByDesc('service_date');
                if ($placas !== '') {
                    $q->whereHas('vehicle', fn ($v) => $v->where('plates', 'like', "%{$placas}%"));
                }

                return [
                    'mantenimientos' => $q->limit(self::LIMITE)->get()->map(fn ($m) => [
                        'vehiculo' => $m->vehicle ? trim("{$m->vehicle->brand} {$m->vehicle->model}") . " ({$m->vehicle->plates})" : null,
                        'fecha'    => optional($m->service_date)->format('d/m/Y'),
                        'tipo'     => $m->type,
                        'costo'    => '$' . number_format((float) $m->total_cost, 2),
                        'proximo'  => optional($m->next_service_date)->format('d/m/Y'),
                    ])->all(),
                    'enlace' => $this->enlace('flotas'),
                ];

            case 'gasto':
                $anio = (int) ($args['anio'] ?? now()->year);
                $q = FleetMaintenance::whereHas('vehicle', fn ($v) => $v->forClient($clientId))
                    ->where('is_draft', false)->whereYear('service_date', $anio);

                return [
                    'anio' => $anio, 'servicios' => (clone $q)->count(),
                    'gasto_mantenimiento' => '$' . number_format((float) $q->sum('total_cost'), 2),
                    'enlace' => $this->enlace('flotas'),
                ];
        }

        return ['error' => 'Consulta no reconocida. Usa documentos_por_vencer, vehiculos, mantenimientos o gasto.'];
    }
}
