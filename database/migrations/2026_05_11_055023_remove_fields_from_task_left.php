<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Module;
use App\Modules\Core\Configuracion\Models\FieldModule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'TaskLeft')->first();

        if(!$module) return;



        FieldModule::where('module_id', $module->id)
            ->where('name','location_id')
            ->delete();

        FieldModule::where('module_id', $module->id)
            ->where('type', 34)
            ->update(['type' => 35]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'TaskLeft')->first();

        if (!$module) return;


        FieldModule::create([
            'module_id'      => $module->id,
            'include'        => 1,
            'name'           => 'location_id',
            'type'           => 54,
            'label'          => 'Ubicación',
            'hint'           => null,
            'placeholder'    => '',
            'value'          => '',
            'options'        => null,
            'search'         => json_encode([
                'model' => 'App\\Models\\Location',
                'id'    => 'id',
                'text'  => 'name'
            ]),
            'inputGroup'     => null,
            'inputGroupEnd'  => null,
            'depend'         => null,
            'inputs_depend'  => null,
            'position'       => 11,
            'disabled'       => null,
            'default_value'  => null,
            'partition'      => null,
            'rule'           => null,
            'step'           => null,
            'additional_field' => 0,
            'class_col'      => 'full',
            'class_label'    => 'col-12',
            'class_field'    => 'col-12',
        ]);
        FieldModule::where('module_id', $module->id)
            ->where('type', 35)
            ->update(['type' => 34]);
    }


};
