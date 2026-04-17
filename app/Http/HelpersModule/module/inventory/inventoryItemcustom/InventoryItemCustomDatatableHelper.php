<?php


namespace App\Http\HelpersModule\module\inventory\inventoryItemcustom;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryItemCustom;

class InventoryItemCustomDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryItemCustom::class, 'InventoryItemCustom');
    }    
}
