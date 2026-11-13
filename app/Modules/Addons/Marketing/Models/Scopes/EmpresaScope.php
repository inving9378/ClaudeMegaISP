<?php

namespace App\Modules\Addons\Marketing\Models\Scopes;

use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la empresa activa SOLO cuando hay contexto (rutas autenticadas de Marketing).
 * Sin contexto (webhooks, jobs, comandos, otros módulos) no hace nada: igual que antes de la Fase 0.
 */
class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $contexto = app(EmpresaActiva::class);

        if (! $contexto->hay()) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $contexto->id());
    }
}
