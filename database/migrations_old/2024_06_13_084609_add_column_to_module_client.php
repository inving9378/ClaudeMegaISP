<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->create([
            'name' => 'fecha_corte',
            'label' => 'Fecha de Corte',
            'order' => 91,
            'active' => true,
            'filter_name' => 'clients.fecha_corte'
        ]);

        $columns = [
            0 => "id",
            1 => "name",
            4 => "estado",
            8 => "amount",
            9 => "nif_pasaport",
            2 => "father_last_name",
            3 => "mother_last_name",
            12 => "phone",
            13 => "phone2",
            14 => "type_of_billing_id",
            15 => "email",
            16 => "street",
            17 => "zip",
            18 => "external_number",
            19 => "internal_number",
            20 => "category",
            21 => "modem_sn",
            22 => "gpon_ont",
            23 => "power_dbm",
            24 => "original_password",
            25 => "vendor",
            26 => "box_nomenclator",
            27 => "user_film",
            28 => "password_film",
            29 => "password_wifi",
            30 => "reinstatement",
            31 => "social_id",
            32 => "comment",
            33 => "installation_on_time",
            34 => "amount_technician_and_why",
            35 => "doubt_signed_contract",
            36 => "technician_attencion",
            37 => "last_time_online",
            38 => "billing_activated",
            39 => "type_billing_id",
            40 => "period",
            41 => "billing_date",
            42 => "billing_expiration",
            43 => "grace_period",
            44 => "autopay_invoice",
            45 => "send_financial_notification",
            46 => "partner_id",
            47 => "state_id",
            48 => "municipality_id",
            49 => "colony_id",
            50 => "internet_fees",
            51 => "voz_fees",
            52 => "custom_fees",
            53 => "bundle_fees",
            54 => "price",
            55 => "voz_price",
            56 => "custom_price",
            57 => "recurrent_price",
            58 => "ip_ranges",
            59 => "location_id",
            60 => "router",
            61 => "redes_adicionales",
            62 => "ipv6",
            63 => "ipv6_delegada",
            64 => "mac",
            65 => "payment_method_id",
            66 => "activate_reminders",
            67 => "type_of_message",
            68 => "reminder_1_days",
            69 => "reminder_2_days",
            70 => "reminder_3_days",
            71 => "reminder_payment_3",
            72 => "reminder_payment_amount",
            73 => "reminder_payment_comment",
            74 => "billing_name",
            75 => "billing_street",
            76 => "billing_zip_code",
            77 => "billing_city",
            78 => "internet_services_start_date",
            79 => "internet_services_end_date",
            80 => "voice_services_start_date",
            81 => "voice_services_end_date",
            82 => "custom_services_start_date",
            83 => "custom_services_end_date",
            84 => "recurring_services_start_date",
            85 => "recurring_services_end_date",
            86 => "custom_days_left",
            87 => "created_at",
            88 => "updated_at",
            89 => "user",
            90 => "password",
            92 => "action",
        ];
        foreach ($columns as $order => $value) {
            $module->columnsDatatable()->where('name', $value)->update([
                'order' => $order
            ]);
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'fecha_corte')->delete();
    }
};
