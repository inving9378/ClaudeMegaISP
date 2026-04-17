<?php

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
       $module = \App\Models\Module::where('name','NetworkIp')->first();
       $module->fields()->where('name','ip')->update([
            'type' => 2
       ]);
        $module->fields()->where('name','used')->update([
            'type' => 16
        ]);

        $module->fields()->where('name','used_by')->update([
            'type' => 2
        ]);

        $module->fields()->where('name','client_id')->update([
            'type' => 3
        ]);
        $module->fields()->where('name','location_id')->update([
            'type' => 3
        ]);
        $module->fields()->where('name','hostname')->update([
            'label' => "Host Name"
        ]);

        $module->fields()->where('name','comment')->update([
            'label' => "Comentario"
        ]);

        $module->fields()->where('name','host_category')->update([
            'type' => 22,
            'placeholder'=> 'Seleccione',
            'default_value' => 'Ninguno',
            'options' => json_encode([
                'Customer' => 'Customer',
                'Ninguno' => 'Ninguno',
                'Access point' => 'Access point',
                'Firewall' => 'Firewall',
                'DB Server' => 'DB Server',
                'L2 Device' => 'L2 Device',
                'L3 Device' => 'L3 Device',
                'Router' => 'Router',
                'Linux Server' => 'Linux Server',
                'Windows Server' => 'Windows Server',
                'Printer' => 'Printer',
                'VoIP Device' => 'VoIP Device',
                'Workstation' => 'Workstation',
                'Other' => 'Other',
                'Todo' => 'Todo'
            ])
        ]);

        $fields = [
            [
                'name' => 'user',
                'label' => 'Usuario',
                'placeholder' => '',
                'type' => 1,
                'additional_field' => false,
                'position' => 10,
            ],
            [
                'name' => 'password',
                'label' => 'Contraseña',
                'placeholder' => '********',
                'type' => 8,
                'additional_field' => false,
                'position' => 10,
            ]
        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','NetworkIp')->first();
        $module->fields()->where('name','ip')->update([
            'type' => 1
        ]);
        $module->fields()->where('name','used')->update([
            'type' => 1
        ]);
        $module->fields()->where('name','host_category')->update([
            'type' => 1
        ]);
        $module->fields()->where('name','client_id')->update([
            'type' => 1
        ]);
        $module->fields()->where('name','used_by')->update([
            'type' => 1
        ]);
        $module->fields()->where('name','user')->delete();
        $module->fields()->where('name','password')->delete();
    }
};
