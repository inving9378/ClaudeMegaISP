<?php

namespace App\Modules\Addons\Marketing\Models\Concerns;

use App\Modules\Addons\Marketing\Models\Scopes\EmpresaScope;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\Model;

/**
 * Aísla un modelo de Marketing por empresa (company_id) cuando hay empresa activa en la petición.
 *
 * Con contexto:
 *  - consultas: WHERE {tabla}.company_id = empresa activa (un id de otra empresa → 404);
 *  - creating: company_id = empresa activa SIEMPRE (pisa lo que venga del request o del código);
 *  - updating: un company_id cambiado se regresa en silencio al original (no se mueve de empresa).
 * Sin contexto: no hace nada.
 */
trait PerteneceAEmpresa
{
    public static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope());

        static::creating(function (Model $model) {
            $contexto = app(EmpresaActiva::class);

            if ($contexto->hay()) {
                $model->setAttribute('company_id', $contexto->id());
            }
        });

        static::updating(function (Model $model) {
            $contexto = app(EmpresaActiva::class);

            if ($contexto->hay() && $model->isDirty('company_id')) {
                $model->setAttribute('company_id', $model->getOriginal('company_id'));
            }
        });
    }
}
