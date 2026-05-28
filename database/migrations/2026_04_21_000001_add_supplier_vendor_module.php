<?php

use App\Http\Repository\FieldTypeRepository;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    public function up(): void
    {
        if (Module::where('name', 'SupplierVendor')->exists()) {
            return;
        }

        try {
            $module = Module::create([
                'name'  => 'SupplierVendor',
                'group' => 'Supplier',
            ]);

            $columns = [
                ['name' => 'id',          'label' => 'ID',         'order' => 1],
                ['name' => 'supplier_id', 'label' => 'Proveedor',  'order' => 2],
                ['name' => 'name',        'label' => 'Nombre',     'order' => 3],
                ['name' => 'last_name',   'label' => 'Apellido',   'order' => 4],
                ['name' => 'phone',       'label' => 'Teléfono',   'order' => 5],
                ['name' => 'email',       'label' => 'Email',      'order' => 6],
                ['name' => 'position',    'label' => 'Puesto',     'order' => 7],
                ['name' => 'status',      'label' => 'Estado',     'order' => 8],
                ['name' => 'action',      'label' => 'Acciones',   'order' => 999],
            ];

            foreach ($columns as $colData) {
                $module->columnsDatatable()->updateOrCreate(
                    ['name' => $colData['name']],
                    $colData
                );
            }

            $fieldRepository = new FieldTypeRepository();

            $fields = [
                [
                    'name'             => 'name',
                    'label'            => 'Nombre',
                    'placeholder'      => 'Nombre del vendedor',
                    'type'             => 1,
                    'position'         => 1,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'last_name',
                    'label'            => 'Apellido',
                    'placeholder'      => 'Apellido del vendedor',
                    'type'             => 1,
                    'position'         => 2,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'phone',
                    'label'            => 'Teléfono',
                    'placeholder'      => 'Teléfono de contacto',
                    'type'             => 1,
                    'position'         => 3,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'email',
                    'label'            => 'Email',
                    'placeholder'      => 'Correo electrónico',
                    'type'             => 1,
                    'position'         => 4,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'position',
                    'label'            => 'Puesto',
                    'placeholder'      => 'Puesto o cargo',
                    'type'             => 1,
                    'position'         => 5,
                    'include'          => true,
                    'additional_field' => false,
                ],
                [
                    'name'             => 'status',
                    'label'            => 'Estado',
                    'placeholder'      => 'Seleccione estado',
                    'type'             => $fieldRepository->getIdByName('select-2-component'),
                    'position'         => 6,
                    'include'          => true,
                    'additional_field' => false,
                    'options'          => json_encode([
                        'active'   => 'Activo',
                        'inactive' => 'Inactivo',
                    ]),
                ],
            ];

            foreach ($fields as $fieldData) {
                $module->fields()->updateOrCreate(
                    ['name' => $fieldData['name']],
                    $fieldData
                );
            }

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
            \Log::error('SupplierVendor module migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'SupplierVendor')->first();
        if ($module) {
            $module->fields()->delete();
            $module->columnsDatatable()->delete();
            $module->packages()->detach();
            $module->delete();
        }
    }
};