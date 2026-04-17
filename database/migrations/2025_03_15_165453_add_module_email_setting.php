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
            'name' => 'EmailSetting',
            'group' => 'Configuration'
        ]);


        $fields = [
            [
                'name' => 'mail_mailer',
                'label' => 'MAIL_MAILER',
                'placeholder' => '',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_host',
                'label' => 'MAIL_HOST',
                'placeholder' => '',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_port',
                'label' => 'MAIL_PORT',
                'placeholder' => '',
                'type' => 1,
                'position' => 3,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_username',
                'label' => 'MAIL_USERNAME',
                'placeholder' => '',
                'type' => 1,
                'position' => 4,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_password',
                'label' => 'MAIL_PASSWORD',
                'placeholder' => '',
                'type' => 8,
                'position' => 5,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_encryption',
                'label' => 'MAIL_ENCRYPTION',
                'placeholder' => '',
                'type' => 1,
                'position' => 6,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_from_address',
                'label' => 'MAIL_FROM_ADDRESS',
                'placeholder' => '',
                'type' => 1,
                'position' => 7,
                'additional_field' => false,
            ],
            [
                'name' => 'mail_from_name',
                'label' => 'MAIL_FROM_NAME',
                'placeholder' => '',
                'type' => 1,
                'position' => 8,
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
        $module = Module::where('name', 'EmailSetting')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
