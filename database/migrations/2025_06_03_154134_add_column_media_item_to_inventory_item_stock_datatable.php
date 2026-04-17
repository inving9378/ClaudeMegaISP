<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'InventoryItemStock')->first();
        $columnsDatatables = $module->columnsDatatable()->where('name', '!=', 'id')->get();
        foreach ($columnsDatatables as $col) {
            $col->update([
                'order' => $col->order + 1
            ]);
        }

        $module->columnsDatatable()->create([
            'name' => 'media',
            "filter_name" => null,
            "label" => "Imagenes",
            "order" => 2
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItemStock')->first();

        $columnsDatatables = $module->columnsDatatable()->where('name', '!=', 'id')->get();
        foreach ($columnsDatatables as $col) {
            $col->update([
                'order' => $col->order - 1
            ]);
        }
        $columnsDatatable = $module->columnsDatatable()->where('name', 'media')->first();
        if($columnsDatatable){
            $columnsDatatable->delete();
        }
    }
};
