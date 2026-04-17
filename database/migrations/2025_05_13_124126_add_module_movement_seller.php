<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::create([
            'name' => 'InventoryMovementSeller',
            'is_main' => false,
            'main' => 'InventoryMovement',
            'group' => 'Administracion',
        ]);

        $module->fields()->create([
            'name' =>  'quantity',
            'type' => 15,
            'position' => 2,
            'label' => 'Cantidad',
            'options' => json_encode([
                'min' => 1
            ]),
            'default_value' => 1
        ]);
        $module->fields()->create([
            'name' =>  'select_to',
            'type' => 22,
            'position' => 4,
            'label' => 'Seleccione Destino',
            'options' => json_encode([
                'store' => 'Almacén',
                'user' => 'Usuario',
                'client' => 'Cliente',
            ]),

            'class_col' => 'full',
            'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field' => 'col-sm-12 col-md-9',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_user_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_user_enable',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_client_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_client_enable',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_store_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_store_enable',
        ]);

        $module->fields()->create([
            'name' =>  'user_from',
            'type' => 30,
            'position' => 100,
            'label' => 'user_from',
        ]);

        $module->fields()->create([
            'name' =>  'store_to',
            'type' => 30,
            'position' => 100,
            'label' => 'store_to',
        ]);

        $module->fields()->create([
            'name' =>  'user_to',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to',
        ]);
        $module->fields()->create([
            'name' =>  'client_to',
            'type' => 30,
            'position' => 100,
            'label' => 'client_to',
        ]);

        $module->fields()->create([
            'name' =>  'id_item',
            'type' => 30,
            'position' => 100,
            'label' => 'id_item',
        ]);



        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [14];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryMovementSeller')->first();
        $module->packages()->detach();
        $module->fields()->delete();
        $module->delete();
    }
};
