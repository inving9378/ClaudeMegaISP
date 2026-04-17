<?php


namespace App\Http\HelpersModule\module\inventory\storezone;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryStore;
use App\Models\StoreZone;
use Illuminate\Support\Arr;

class StoreZoneDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(StoreZone::class, 'StoreZone');
    }
}
