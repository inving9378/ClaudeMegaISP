<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiIntegrationProvider extends BaseModel
{
    use SoftDeletes;

    public const TYPES = ['ia', 'servicios'];

    protected $table = 'api_integration_providers';

    protected $fillable = [
        'slug', 'name', 'description', 'type', 'icon', 'docs_url',
        'key_format', 'has_config', 'is_system', 'active',
    ];

    protected $casts = [
        'has_config' => 'boolean',
        'is_system'  => 'boolean',
        'active'     => 'boolean',
    ];

    public function integrations()
    {
        return $this->hasMany(ApiIntegration::class, 'provider', 'slug');
    }
}
