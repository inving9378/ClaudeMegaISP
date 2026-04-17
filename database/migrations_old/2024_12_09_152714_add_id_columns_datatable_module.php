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
        $modules = Module::all();
        $columnsDatatableByModule = [
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "ID",
                'order' => 0
            ],
        ];
        foreach ($modules as $module) {
            $columnDatatbleId = $module->columnsDatatable()->where('name', 'id')->first();
            $orderColumn0 = $module->columnsDatatable()->where('order', 0)->first();
            if (!$columnDatatbleId) {
                $module->columnsDatatable()->createMany($columnsDatatableByModule);
            }
            if ($orderColumn0) {
                $orderColumn0->order = 1;
                $orderColumn0->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
