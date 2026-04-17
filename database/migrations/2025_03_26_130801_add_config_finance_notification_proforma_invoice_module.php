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
            'name' => 'ConfigFinanceNotificationProformaInvoice',
            'group' => 'Configuration',
            'is_main' => false,
            'main' => 'App\Models\ConfigFinanceNotification',
        ]);

        $fields = [
            [
                'name' => 'auto_send_notifications',
                'label' => 'Activar',
                'placeholder' => '',
                'type' => 16,
                'position' => 1,
                'additional_field' => false,
                'default_value' => true,
            ],
            [
                'name' => 'message_type',
                'label' => 'Tipo de Mensaje',
                'placeholder' => 'Seleccione Tipo de Mensaje',
                'type' => 22,
                'position' => 2,
                'additional_field' => false,
                'options' => json_encode([
                    'email' => 'Email',
                    'sms' => 'SMS',
                ])
            ],

            [
                'name' => 'email_template_id',
                'label' => 'Plantilla Correo',
                'placeholder' => 'Seleccione Plantilla',
                'type' => 22,
                'position' => 3,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            [
                'name' => 'sms_template',
                'label' => 'Plantilla SMS ',
                'placeholder' => 'Seleccione Plantilla',
                'type' => 22,
                'position' => 4,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],

            [
                'name' => 'email_bcc',
                'label' => 'Correo electrónico CCO',
                'placeholder' => 'Correo CCO, separado por comas',
                'type' => 1,
                'position' => 5,
                'additional_field' => false,
            ],

            [
                'name' => 'delay_hours',
                'label' => 'Retraso en el envío de notificaciones (horas)',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 7,
                'additional_field' => false,
                'options' => json_encode([
                    0 => '0',
                    1 => '1',
                    3 => '3',
                    6 => '6',
                    12 => '12',
                    24 => '24',
                    48 => '48',
                    72 => '72',
                ])
            ],
            [
                'name' => 'notification_days',
                'label' => 'Días de notificación',
                'placeholder' => 'Seleccione',
                'type' => 25,
                'position' => 8,
                'additional_field' => false,
                'options' => json_encode([
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miercoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sabado',
                    7 => 'Domingo',
                ])
            ],

            [
                'name' => 'notification_hours',
                'label' => 'Horario de las Notificaciones',
                'placeholder' => 'Seleccione',
                'type' => 25,
                'position' => 9,
                'additional_field' => false,
                'options' => json_encode([
                    '00:00' => '00:00',
                    '01:00' => '01:00',
                    '02:00' => '02:00',
                    '03:00' => '03:00',
                    '04:00' => '04:00',
                    '05:00' => '05:00',
                    '06:00' => '06:00',
                    '07:00' => '07:00',
                    '08:00' => '08:00',
                    '09:00' => '09:00',
                    '10:00' => '10:00',
                    '11:00' => '11:00',
                    '12:00' => '12:00',
                    '13:00' => '13:00',
                    '14:00' => '14:00',
                    '15:00' => '15:00',
                    '16:00' => '16:00',
                    '17:00' => '17:00',
                    '18:00' => '18:00',
                    '19:00' => '19:00',
                    '20:00' => '20:00',
                    '21:00' => '21:00',
                    '22:00' => '22:00',
                    '23:00' => '23:00'
                ])
            ],
        ];
        $module->fields()->createMany($fields);


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
        $module = Module::where('name', 'ConfigFinanceNotificationProformaInvoice')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
