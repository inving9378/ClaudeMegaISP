<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    public function up(): void
    {
        if (Module::where('name', 'SupplierProductPrice')->exists()) {
            return;
        }

        try {
            $module = Module::create([
                'name'    => 'SupplierProductPrice',
                'group'   => 'Supplier',
                'is_main' => true,
            ]);

            $module->columnsDatatable()->createMany([
                ['name' => 'media',             'label' => 'Imagen',        'order' => 0],
                ['name' => 'inventory_item_id', 'label' => 'Artículo',      'order' => 1],
                ['name' => 'base_price',        'label' => 'Precio Base',   'order' => 2],
                ['name' => 'price',             'label' => 'Precio',        'order' => 3],
                ['name' => 'bulk_price',        'label' => 'Precio Vol.',   'order' => 4],
                ['name' => 'bulk_min_quantity', 'label' => 'Cant. Mín.',    'order' => 5],
                ['name' => 'is_active',         'label' => 'Estado',        'order' => 6],
                ['name' => 'action',            'label' => 'Acciones',      'order' => 999],
            ]);

            $selectLongId = 43;
            $select2Id    = 23;

            $module->fields()->createMany([
                [
                    'name'             => 'inventory_item_id',
                    'label'            => 'Producto',
                    'type'             => $selectLongId,
                    'position'         => 1,
                    'include'          => true,
                    'additional_field' => false,
                    'search'           => json_encode([
                        'model'    => 'App\\Models\\InventoryItem',
                        'id'       => 'id',
                        'text'     => 'name',
                        'order_by' => 'name',
                    ]),
                ],
                [
                    'name'             => 'base_price',
                    'label'            => 'Precio Base',
                    'type'             => 1,
                    'position'         => 2,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'bulk_price',
                    'label'            => 'Precio por Volumen',
                    'type'             => 1,
                    'position'         => 3,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'bulk_min_quantity',
                    'label'            => 'Cant. Mínima',
                    'type'             => 1,
                    'position'         => 4,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'is_active',
                    'label'            => 'Activo',
                    'type'             => $select2Id,
                    'placeholder'      => 'Seleccione estado',
                    'options'          => json_encode([true => 'Activo', false => 'Inactivo']),
                    'position'         => 5,
                    'include'          => true,
                    'additional_field' => false,
                ],
            ]);

            $bootstrap_multiselect = [1, 2];
            $select2               = [4, 5];
            $chosen_select         = [21, 22];
            $toaster               = [3];
            $datatables_packages   = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
            $ckeditor              = [23];
            $apechart              = [20];
            $google                = [26];

            $packages = Arr::collapse([
                $bootstrap_multiselect,
                $toaster,
                $datatables_packages,
                $select2,
                $chosen_select,
                $ckeditor,
                $google,
                $apechart,
            ]);

            $module->packages()->attach($packages);
        } catch (\Exception $e) {
            \Log::error('SupplierProductPrice module migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'SupplierProductPrice')->first();
        if ($module) {
            $module->fields()->delete();
            $module->columnsDatatable()->delete();
            $module->packages()->detach();
            $module->delete();
        }
    }
};
