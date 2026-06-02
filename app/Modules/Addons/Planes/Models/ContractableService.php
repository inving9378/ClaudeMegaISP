<?php

namespace App\Modules\Addons\Planes\Models;

use App\Models\BaseModel;

/**
 * Overlay administrable de un service_type declarado por un módulo.
 * Las capacidades vienen del manifiesto; aquí vive lo configurable por el admin.
 *
 * @see \App\Modules\Addons\Planes\Services\ServiceCatalogService
 */
class ContractableService extends BaseModel
{
    protected $table = 'contractable_services';

    protected $fillable = [
        'service_type_key',
        'module_slug',
        'label',
        'price',
        'price_configurable',
        'supports_promotions',
        'bundleable',
        'active',
        'meta',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'price_configurable'  => 'boolean',
        'supports_promotions' => 'boolean',
        'bundleable'          => 'boolean',
        'active'              => 'boolean',
        'meta'                => 'array',
    ];
}
