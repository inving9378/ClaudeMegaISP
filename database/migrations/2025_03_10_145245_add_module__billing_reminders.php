<?php

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
        $module = Module::create([
            'name' => 'BillingReminder',
            'group' => 'Configuration'
        ]);
        $arrayHasta31 = [];
        for ($i = 1; $i <= 31; $i++) {
            $arrayHasta31[$i] = $i;
        }


        $fields = [
            [
                'name' => 'enable_reminders',
                'label' => 'Habilitar Recordatorios',
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
                'name' => 'send_time',
                'label' => 'Hora de Envío',
                'placeholder' => 'Seleccione Hora de Envío',
                'type' => 21,
                'position' => 3,
                'additional_field' => false,
            ],
            //reminder_1_days
            [
                'name' => 'reminder_1_days',
                'label' => 'Recordatorio #1',
                'placeholder' => 'Seleccione Día 1',
                'type' => 22,
                'position' => 4,
                'additional_field' => false,
                'options' => json_encode($arrayHasta31)
            ],
            //reminder_1_subject
            [
                'name' => 'reminder_1_subject',
                'label' => 'Asunto Recordatorio #1',
                'placeholder' => 'Asunto Recordatorio #1',
                'type' => 1,
                'position' => 5,
                'additional_field' => false,
            ],
            //reminder_1_email_template
            [
                'name' => 'reminder_1_email_template',
                'label' => 'Plantilla Recordatorio #1',
                'placeholder' => 'Seleccione Plantilla Recordatorio #1',
                'type' => 22,
                'position' => 6,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'typeEmail'
                ])
            ],
            //reminder_1_sms_template
            [
                'name' => 'reminder_1_sms_template',
                'label' => 'Plantilla SMS Recordatorio #1',
                'placeholder' => 'Seleccione Plantilla SMS Recordatorio #1',
                'type' => 22,
                'position' => 7,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            //reminder_2_days
            [
                'name' => 'reminder_2_days',
                'label' => 'Recordatorio #2',
                'placeholder' => 'Seleccione Día 2',
                'type' => 22,
                'position' => 8,
                'additional_field' => false,
                'options' => json_encode($arrayHasta31)
            ],
            //reminder_2_subject
            [
                'name' => 'reminder_2_subject',
                'label' => 'Asunto Recordatorio #2',
                'placeholder' => 'Asunto Recordatorio #2',
                'type' => 1,
                'position' => 9,
                'additional_field' => false,
            ],
            //reminder_2_email_template
            [
                'name' => 'reminder_2_email_template',
                'label' => 'Plantilla Recordatorio #2',
                'placeholder' => 'Seleccione Plantilla Recordatorio #2',
                'type' => 22,
                'position' => 10,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'typeEmail'
                ])
            ],
            //reminder_2_sms_template
            [
                'name' => 'reminder_2_sms_template',
                'label' => 'Plantilla SMS Recordatorio #2',
                'placeholder' => 'Seleccione Plantilla SMS Recordatorio #2',
                'type' => 22,
                'position' => 11,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            //reminder_3_days
            [
                'name' => 'reminder_3_days',
                'label' => 'Recordatorio #3',
                'placeholder' => 'Seleccione Día 3',
                'type' => 22,
                'position' => 12,
                'additional_field' => false,
                'options' => json_encode($arrayHasta31)
            ],
            //reminder_3_subject
            [
                'name' => 'reminder_3_subject',
                'label' => 'Asunto Recordatorio #3',
                'placeholder' => 'Asunto Recordatorio #3',
                'type' => 1,
                'position' => 13,
                'additional_field' => false,
            ],
            //reminder_3_email_template
            [
                'name' => 'reminder_3_email_template',
                'label' => 'Plantilla Recordatorio #3',
                'placeholder' => 'Seleccione Plantilla Recordatorio #3',
                'type' => 22,
                'position' => 14,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'typeEmail'
                ])
            ],
            //reminder_3_sms_template
            [
                'name' => 'reminder_3_sms_template',
                'label' => 'Plantilla SMS Recordatorio #3',
                'placeholder' => 'Seleccione Plantilla SMS Recordatorio #3',
                'type' => 22,
                'position' => 15,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],

            //all_payment_methods
            [
                'name' => 'all_payment_methods',
                'label' => 'Todos los metodos de pago',
                'type' => 16,
                'position' => 16,
                'additional_field' => false,
                'default_value' => false
            ],
            //payment_reminder_methods TODO DESPUES CAMBIAR
            [
                'name' => 'payment_reminder_methods',
                'label' => 'Metodos de pago recordatorio',
                'type' => 1,
                'position' => 17,
                'additional_field' => false
            ],
            //attach_paid_invoices
            [
                'name' => 'attach_paid_invoices',
                'label' => 'Adjuntar facturas pagadas',
                'type' => 16,
                'position' => 18,
                'additional_field' => false
            ]
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
        $module = Module::where('name', 'BillingReminder')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
