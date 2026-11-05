<?php

namespace App\Modules\Addons\Flotas\Support;

/**
 * Reglas de validación de una unidad — fuente única para el alta del panel
 * (/flotas) y el alta a nombre de un cliente desde su ficha.
 */
class FleetVehicleRules
{
    public static function store(): array
    {
        return [
            'plates'               => 'nullable|string|max:20',
            'brand'                => 'required|string|max:100',
            'model'                => 'required|string|max:100',
            'year'                 => 'nullable|integer|min:1900|max:2100',
            'color'                => 'nullable|string|max:50',
            'vin'                  => 'nullable|string|max:50',
            'motor_number'         => 'nullable|string|max:50',
            'vehicle_type'         => 'nullable|in:car,pickup,truck,motorcycle,other',
            'fuel_type'            => 'nullable|in:gasoline,diesel,electric,hybrid',
            'tank_capacity_liters' => 'nullable|numeric|min:0',
            'current_km'           => 'nullable|integer|min:0',
            'status'               => 'nullable|in:active,in_workshop,inactive',
            'habitual_location'    => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            'has_gps'              => 'boolean',
            'gps_brand'            => 'nullable|string|max:100',
            'gps_model'            => 'nullable|string|max:100',
            'gps_imei'             => 'nullable|string|max:20',
            'gps_sim'              => 'nullable|string|max:20',
            'gps_carrier'          => 'nullable|string|max:50',
        ];
    }

    /** Misma lista, pero cada campo es opcional (edición parcial). */
    public static function update(): array
    {
        return array_map(fn ($r) => 'sometimes|' . $r, self::store());
    }

    /** Campos comunes de un documento (panel y Portal Cliente). */
    public static function document(): array
    {
        return [
            'document_type'   => 'required|in:circulation_card,insurance_policy,tenencia,verification,operator_license,special_permit,other',
            'folio_number'    => 'nullable|string|max:100',
            'issued_by'       => 'nullable|string|max:200',
            'issue_date'      => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'cost'            => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
        ];
    }

    /** Campos comunes de un mantenimiento (sin vehicle_id). */
    public static function maintenance(): array
    {
        return [
            'type'              => 'required|in:preventive,corrective,emergency,verification',
            'service_date'      => 'required|date',
            'service_km'        => 'nullable|integer|min:0',
            'works'             => 'nullable|array',
            'works.*'           => 'string',
            'description'       => 'nullable|string',
            'provider_id'       => 'nullable|integer',
            'mechanic_name'     => 'nullable|string|max:150',
            'labor_cost'        => 'nullable|numeric|min:0',
            'parts_cost'        => 'nullable|numeric|min:0',
            'other_cost'        => 'nullable|numeric|min:0',
            'next_service_date' => 'nullable|date',
            'next_service_km'   => 'nullable|integer|min:0',
            'is_draft'          => 'boolean',
        ];
    }

    /** Campos comunes de una carga de combustible (sin vehicle_id). */
    public static function fuel(): array
    {
        return [
            'refuel_date'  => 'required|date',
            'liters'       => 'required|numeric|min:0.1',
            'cost'         => 'required|numeric|min:0',
            'km_at_refuel' => 'nullable|integer|min:0',
            'octane'       => 'nullable|string|max:10',
            'station_name' => 'nullable|string|max:150',
        ];
    }
}
