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
            'name' => 'CompanyInformation',
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google, $apechart]);

        $fields = [
            [
                'name' => 'company_name',
                'label' => 'Npmbre de la Empresa',
                'placeholder' => 'Nombre de la Empresa',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'company_postal_code',
                'label' => 'Código Postal',
                'placeholder' => 'Código Postal',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],

            [
                'name' => 'country',
                'label' => 'País',
                'placeholder' => 'País',
                'type' => 1,
                'position' => 3,
                'additional_field' => false,
            ],

            [
                'name' => 'colony_id',
                'label' => 'App\Models\CompanyInformation',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 24,
                'position' => 4,
                'additional_field' => false,
            ],

            [
                'name' => 'state_id',
                'label' => 'Estado',
                'placeholder' => 'Seleccionar Estado',
                'type' => 30,
                'position' => 5,
                'include' => false,
                'additional_field' => false,
            ],

            [
                'name' => 'municipality_id',
                'label' => 'Municipio',
                'placeholder' => 'Seleccionar Municipio',
                'type' => 30,
                'position' => 7,
                'include' => false,
                'additional_field' => false,
            ],

            [
                'name' => 'email',
                'label' => 'Correo Electrónico',
                'placeholder' => 'Correo Electrónico',
                'type' => 1,
                'position' => 8,
                'additional_field' => false,
            ],
            [
                'name' => 'atention_client_phone',
                'label' => 'Teléfono Atención a Clientes',
                'placeholder' => 'Teléfono Atención a Clientes',
                'type' => 1,
                'position' => 9,
                'additional_field' => false,
            ],

            [
                'name' => 'rfc',
                'label' => 'RFC de la Empresa',
                'placeholder' => 'RFC de la Empresa',
                'type' => 1,
                'position' => 10,
                'additional_field' => false,
            ],


            [
                'name' => 'iva',
                'label' => 'IVA',
                'placeholder' => 'IVA',
                'type' => 1,
                'position' => 11,
                'additional_field' => false,
            ],
            [
                'name' => 'bank_name',
                'label' => 'Nombre del Banco',
                'placeholder' => 'Nombre del Banco',
                'type' => 1,
                'position' => 12,
                'additional_field' => false,
            ],
            [
                'name' => 'bank_account',
                'label' => 'Cuenta Bancaria',
                'placeholder' => 'Cuenta Bancaria',
                'type' => 1,
                'position' => 13,
                'additional_field' => false,
            ],
            [
                'name' => 'cominion_partner',
                'label' => 'Comisión a Socios',
                'placeholder' => 'Comisión a Socios',
                'type' => 1,
                'position' => 14,
                'additional_field' => false,
            ]
        ];
        $module->fields()->createMany($fields);


        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
