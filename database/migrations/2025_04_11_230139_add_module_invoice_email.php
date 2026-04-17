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
            'name' => 'InvoiceEmail',
            'group' => 'Configuration'
        ]);

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],
            [
                'name' => 'client_id',
                'label' => 'Cliente',
                'order' => 2,
            ],

            [
                'name' => 'type',
                'label' => 'Tipo',
                'order' => 3,
            ],

            [
                'name' => 'via',
                'label' => 'Via',
                'order' => 4,
            ],

            [
                'name' => 'status',
                'label' => 'Estado',
                'order' => 5,
            ],

            [
                'name' => 'due_date',
                'label' => 'Fecha de vencimiento',
                'order' => 6,
            ],

            [
                'name' => 'sent_at',
                'label' => 'Enviado',
                'order' => 7,
            ],

            [
                'name' => 'error_message',
                'label' => 'Error',
                'order' => 8,
            ],

            [
                'name' => 'email_if_error',
                'label' => 'Enviar correo en caso de error',
                'order' => 9,
            ],
            [
                'name' => 'recipient_email',
                'label' => 'Correo Cliente',
                'order' => 10,
            ],
            [
                'name' => 'recipient_phone',
                'label' => 'Telefono',
                'order' => 11,
            ],
            [
                'name' => 'subject',
                'label' => 'Asunto',
                'order' => 12,
            ],
            [
                'name' => 'cc_email',
                'label' => 'CC',
                'order' => 13,
            ],
            [
                'name' => 'created_at',
                'label' => 'Creado',
                'order' => 14,
            ],

            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];

        $module->columnsDatatable()->createMany($columnsDatatable);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InvoiceEmail')->first();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
