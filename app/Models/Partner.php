<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Request;

class Partner extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function internet()
    {
        return $this->morphedByMany(Internet::class, 'partner_module', 'partner_module');
    }

    public function project()
    {
        return $this->morphedByMany(Project::class, 'partner_module', 'partner_module');
    }

    public function voz()
    {
        return $this->morphedByMany(Voise::class, 'partner_module', 'partner_module');
    }

    public function router()
    {
        return $this->morphedByMany(Router::class, 'partner_module', 'partner_module');
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

    public function getRequestAndStoreMethod()
    {
        $request = null;
        $storeMethod = 'App\Http\Controllers\Module\Administration\Partner\PartnerController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }
}
