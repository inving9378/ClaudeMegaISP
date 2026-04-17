<?php

use App\Models\ConfigFinanceNotification;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $arrayHasta30 = [];
        for ($i = 1; $i <= 30; $i++) {
            $arrayHasta30[$i] = $i;
        }

        ConfigFinanceNotification::create([
            'group' => 'Prepaid',
            'type_config' => 'prepaid_blocking_wave',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 3,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => null,
            'notification_hours' => '12:00',
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);

        $module = Module::create([
            'name' => 'ConfigFinanceNotificationPrepaidBlockingWave',
            'group' => 'Configuration',
            'is_main' => false,
            'main' => 'App\Models\ConfigFinanceNotification',
        ]);

        $fields = [
            [
                'name' => 'auto_send_notifications',
                'label' => 'Enviar notificación de bloqueo',
                'placeholder' => '',
                'type' => 16,
                'position' => 1,
                'additional_field' => false,
                'default_value' => true,
            ],
            [
                'name' => 'notification_hours',
                'label' => 'Horario de las Notificaciones',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
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
            [
                'name' => 'message_type',
                'label' => 'Tipo de Mensaje',
                'placeholder' => 'Seleccione Tipo de Mensaje',
                'type' => 22,
                'position' => 3,
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
                'position' => 4,
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
                'position' => 5,
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
                'position' => 6,
                'additional_field' => false,
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


        //////1 ola de bloqueo
        ConfigFinanceNotification::create([
            'group' => 'Prepaid',
            'type_config' => 'prepaid_first_blocking_wave',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 3,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => null,
            'notification_hours' => '12:00',
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);

        $module = Module::create([
            'name' => 'ConfigFinanceNotificationPrepaidFirstBlockingWave',
            'group' => 'Configuration',
            'is_main' => false,
            'main' => 'App\Models\ConfigFinanceNotification',
        ]);

        $fields = [
            [
                'name' => 'auto_send_notifications',
                'label' => 'Enviar Notificaciones',
                'placeholder' => '',
                'type' => 16,
                'position' => 1,
                'additional_field' => false,
                'default_value' => true,
            ],
            [
                'name' => 'notification_hours',
                'label' => 'Horario de las Notificaciones',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
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
            [
                'name' => 'delay_hours',
                'label' => 'Cantidad de días antes del bloqueo',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
                'additional_field' => false,
                'options' => json_encode($arrayHasta30),
            ],

            [
                'name' => 'message_type',
                'label' => 'Tipo de Mensaje',
                'placeholder' => 'Seleccione Tipo de Mensaje',
                'type' => 22,
                'position' => 3,
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
                'position' => 4,
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
                'position' => 5,
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
                'position' => 6,
                'additional_field' => false,
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


        //2da Ola
        ConfigFinanceNotification::create([
            'group' => 'Prepaid',
            'type_config' => 'prepaid_second_blocking_wave',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 3,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => null,
            'notification_hours' => '12:00',
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);
        $module = Module::create([
            'name' => 'ConfigFinanceNotificationPrepaidSecondBlockingWave',
            'group' => 'Configuration',
            'is_main' => false,
            'main' => 'App\Models\ConfigFinanceNotification',
        ]);

        $fields = [
            [
                'name' => 'auto_send_notifications',
                'label' => 'Enviar Notificaciones',
                'placeholder' => '',
                'type' => 16,
                'position' => 1,
                'additional_field' => false,
                'default_value' => true,
            ],
            [
                'name' => 'notification_hours',
                'label' => 'Horario de las Notificaciones',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
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
            [
                'name' => 'delay_hours',
                'label' => 'Cantidad de días antes del bloqueo',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
                'additional_field' => false,
                'options' => json_encode($arrayHasta30),
            ],

            [
                'name' => 'message_type',
                'label' => 'Tipo de Mensaje',
                'placeholder' => 'Seleccione Tipo de Mensaje',
                'type' => 22,
                'position' => 3,
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
                'position' => 4,
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
                'position' => 5,
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
                'position' => 6,
                'additional_field' => false,
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

        //3ra Ola
        ConfigFinanceNotification::create([
            'group' => 'Prepaid',
            'type_config' => 'prepaid_third_blocking_wave',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 3,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => null,
            'notification_hours' => '12:00',
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);
        $module = Module::create([
            'name' => 'ConfigFinanceNotificationPrepaidThirdBlockingWave',
            'group' => 'Configuration',
            'is_main' => false,
            'main' => 'App\Models\ConfigFinanceNotification',
        ]);

        $fields = [
            [
                'name' => 'auto_send_notifications',
                'label' => 'Enviar Notificaciones',
                'placeholder' => '',
                'type' => 16,
                'position' => 1,
                'additional_field' => false,
                'default_value' => true,
            ],
            [
                'name' => 'notification_hours',
                'label' => 'Horario de las Notificaciones',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
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
            [
                'name' => 'delay_hours',
                'label' => 'Cantidad de días antes del bloqueo',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 2,
                'additional_field' => false,
                'options' => json_encode($arrayHasta30),
            ],

            [
                'name' => 'message_type',
                'label' => 'Tipo de Mensaje',
                'placeholder' => 'Seleccione Tipo de Mensaje',
                'type' => 22,
                'position' => 3,
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
                'position' => 4,
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
                'position' => 5,
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
                'position' => 6,
                'additional_field' => false,
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
        $module = Module::where('name', 'ConfigFinanceNotificationPrepaidBlockingWave')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
        ConfigFinanceNotification::where('type_config', 'prepaid_blocking_wave')->delete();

        $module = Module::where('name', 'ConfigFinanceNotificationPrepaidFirstBlockingWave')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
        ConfigFinanceNotification::where('type_config', 'prepaid_first_blocking_wave')->delete();

        $module = Module::where('name', 'ConfigFinanceNotificationPrepaidSecondBlockingWave')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
        ConfigFinanceNotification::where('type_config', 'prepaid_second_blocking_wave')->delete();

        $module = Module::where('name', 'ConfigFinanceNotificationPrepaidThirdBlockingWave')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
        ConfigFinanceNotification::where('type_config', 'prepaid_third_blocking_wave')->delete();
    }
};
