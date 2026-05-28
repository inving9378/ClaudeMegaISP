<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use App\Http\Repository\FieldTypeRepository;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where([
            'name' => 'Supplier',
            'group' => 'Inventory'
        ])->first();

        if (!$module) {
            return;
        }

        $fieldRepository = new FieldTypeRepository();

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];

        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([
            $bootstrap_multiselect,
            $toaster,
            $datatables_packages,
            $select2,
            $chosen_select,
            $ckeditor,
            $google,
            $apechart,
        ]);

        $fields = [
            [
                'name'        => 'name',
                'label'       => 'Nombre',
                'placeholder' => 'Nombre del proveedor',
                'type'        => 1,
                'position'    => 1,
                'include'     => true,
                'additional_field' => false,
            ],
            [
                'name'        => 'rfc',
                'label'       => 'RFC',
                'placeholder' => 'RFC del proveedor',
                'type'        => 1,
                'position'    => 2,
                'additional_field' => false,
                'include'     => true,
            ],
            [
                'name'        => 'phone',
                'label'       => 'Teléfono',
                'placeholder' => 'Teléfono de contacto',
                'type'        => 1,
                'position'    => 3,
                'include'     => true,
                'additional_field' => false,
            ],
            [
                'name'        => 'email',
                'label'       => 'Email',
                'placeholder' => 'Correo electrónico',
                'type'        => 1,
                'position'    => 4,
                'additional_field' => false,
                'include'     => true,
            ],
            [
                'name'        => 'address',
                'label'       => 'Dirección',
                'placeholder' => 'Dirección del proveedor',
                'type'        => 5,
                'position'    => 5,
                'additional_field' => false,
                'include'     => true,
            ],
            [
                'name'        => 'status',
                'label'       => 'Estado',
                'placeholder' => 'Seleccione estado',
                'type'        => $fieldRepository->getIdByName('select-2-component'),
                'include'     => true,
                'position'    => 6,
                'additional_field' => false,
                'options'     => json_encode([
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

        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    public function down(): void
    {
        $module = Module::where('name', 'Supplier')->first();
        if ($module) {
            $module->packages()->detach();
            $module->fields()->delete();
        }
    }
};