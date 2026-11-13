<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa (marca) del grupo en Marketing IA. La 1 es Meganet, la principal: no se desactiva y
 * es la única desde la que se edita la configuración compartida (Brand Kit, nichos, Meta…).
 * No lleva el scope de empresa: es el catálogo de empresas.
 */
class Empresa extends BaseModel
{
    public const PRINCIPAL_ID = 1;

    protected $table = 'marketing_empresas';

    protected $fillable = ['nombre', 'slug', 'color_primario', 'color_secundario', 'activo'];

    protected $casts = [
        'activo' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $empresa) {
            $empresa->created_by = auth()->id();
            $empresa->updated_by = auth()->id();
        });

        static::updating(function (self $empresa) {
            $empresa->updated_by = auth()->id();
        });
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(EmpresaUsuario::class, 'company_id');
    }

    public function esPrincipal(): bool
    {
        return (int) $this->getKey() === self::PRINCIPAL_ID;
    }
}
