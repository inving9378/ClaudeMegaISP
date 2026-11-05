<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Servicio ÚNICO de alta/baja de GPS de una unidad (item #628). Lo usan el panel interno,
 * la ficha del cliente (personal) y el portal. La autorización y la búsqueda de la unidad
 * (->forClient) las hace el llamador; aquí vive validación y persistencia.
 */
class FleetGpsDeviceService
{
    /** Marcas con driver real. 'mock' solo para el panel interno (simulación). */
    public const BRANDS_EXTERNAL = ['ruptela'];
    public const BRANDS_INTERNAL = ['ruptela', 'concox', 'gt06_generic', 'mock'];

    public function vincular(FleetVehicle $vehicle, array $input, bool $interno = false): array
    {
        $brands = $interno ? self::BRANDS_INTERNAL : self::BRANDS_EXTERNAL;

        $data = Validator::make($input, [
            'brand'       => ['required', 'in:' . implode(',', $brands)],
            'model'       => 'nullable|string|max:100',
            'imei'        => 'required|string|max:20',
            'sim_number'  => 'nullable|string|max:20',
            'sim_carrier' => 'nullable|string|max:50',
        ])->validate();

        return DB::transaction(function () use ($vehicle, $data) {
            $vehicle = FleetVehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if (FleetDevice::where('vehicle_id', $vehicle->id)->exists()) {
                throw ValidationException::withMessages(['imei' => 'Esta unidad ya tiene un GPS vinculado. Quítalo antes de agregar otro.']);
            }

            // Mismo mensaje para "ya es de otra unidad" que para cualquier otro choque: no se revela de quién es.
            $existente = FleetDevice::withTrashed()->where('imei', $data['imei'])->lockForUpdate()->first();
            if ($existente && ($existente->vehicle_id || $existente->trashed())) {
                throw ValidationException::withMessages(['imei' => 'Este IMEI no se puede vincular. Verifica el número o contacta a soporte.']);
            }

            $isMock = $data['brand'] === 'mock';
            $campos = [
                'vehicle_id'   => $vehicle->id,
                'brand'        => $data['brand'],
                'model'        => $data['model'] ?? null,
                'sim_number'   => $data['sim_number'] ?? null,
                'sim_carrier'  => $data['sim_carrier'] ?? null,
                'status'       => $isMock ? 'active' : 'pending_first_connection',
                'installed_at' => now(),
            ];

            // IMEI que el listener ya vio (device 'unregistered' sin unidad): se reclama.
            $device = $existente ? tap($existente)->update($campos) : FleetDevice::create($campos + ['imei' => $data['imei']]);

            $vehicle->update([
                'has_gps'     => true,
                'gps_brand'   => $data['brand'],
                'gps_model'   => $data['model'] ?? null,
                'gps_imei'    => $data['imei'],
                'gps_sim'     => $data['sim_number'] ?? null,
                'gps_carrier' => $data['sim_carrier'] ?? null,
            ]);

            return ['device' => $device, 'listener' => $isMock ? null : $this->listener()];
        });
    }

    /** Desvincula: el device queda 'unregistered' sin unidad (conserva historial y se puede reclamar). */
    public function quitar(FleetVehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            FleetDevice::where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null, 'status' => 'unregistered']);
            $vehicle->update(['has_gps' => false, 'gps_brand' => null, 'gps_model' => null, 'gps_imei' => null, 'gps_sim' => null, 'gps_carrier' => null]);
        });
    }

    /** Estado público del GPS de una unidad (sin exponer devices ajenos). */
    public function resumen(FleetVehicle $vehicle): ?array
    {
        $d = FleetDevice::where('vehicle_id', $vehicle->id)->first();

        return $d ? [
            'brand' => $d->brand, 'imei' => $d->imei, 'status' => $d->status,
            'esperando_conexion' => $d->status === 'pending_first_connection',
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
        ] : null;
    }

    public function listener(): array
    {
        return ['ip' => config('gps.listener_public_ip'), 'port' => 5027, 'protocol' => 'TCP'];
    }
}
