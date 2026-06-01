<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WarRoomSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedSectionTemplates();

        $this->command->info('WarRoom: permisos y templates creados.');
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'warroom.view',
            'warroom.meeting.create',
            'warroom.meeting.moderate',
            'warroom.meeting.delete',
            'warroom.action_items.assign',
            'warroom.snapshots.regenerate',
            'warroom.insights.regenerate',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = Role::whereIn('name', ['ADMINISTRADOR_COMPLETO', 'DESARROLLADOR', 'super-administrator'])->get();
        foreach ($roles as $rol) {
            $rol->givePermissionTo($permissions);
        }
    }

    private function seedSectionTemplates(): void
    {
        $templates = [
            ['key' => 'resumen',     'order_position' => 1, 'time_minutes' => 7,  'color' => '#534AB7', 'icon' => 'ti-target-arrow',    'default_presenter_role' => 'ADMINISTRADOR_COMPLETO', 'label' => 'Resumen ejecutivo'],
            ['key' => 'finanzas',    'order_position' => 2, 'time_minutes' => 10, 'color' => '#1D9E75', 'icon' => 'ti-coin',             'default_presenter_role' => 'CONTADOR',               'label' => 'Finanzas'],
            ['key' => 'operaciones', 'order_position' => 3, 'time_minutes' => 12, 'color' => '#BA7517', 'icon' => 'ti-tools',            'default_presenter_role' => 'SUPERVISOR_MOSTRADOR',   'label' => 'Operaciones'],
            ['key' => 'ventas',      'order_position' => 4, 'time_minutes' => 10, 'color' => '#185FA5', 'icon' => 'ti-trending-up',      'default_presenter_role' => 'VENDEDOR',               'label' => 'Ventas y Embajadores'],
            ['key' => 'red',         'order_position' => 5, 'time_minutes' => 8,  'color' => '#1D9E75', 'icon' => 'ti-network',          'default_presenter_role' => 'TECNICO_PLANTA',          'label' => 'Red e Infraestructura'],
            ['key' => 'marketing',   'order_position' => 6, 'time_minutes' => 5,  'color' => '#D4537E', 'icon' => 'ti-brand-whatsapp',   'default_presenter_role' => 'SUPERVISOR_MOSTRADOR',   'label' => 'Marketing'],
        ];

        foreach ($templates as $data) {
            \App\Modules\Addons\WarRoom\Models\SectionTemplate::updateOrCreate(
                ['key' => $data['key']],
                array_merge($data, ['active' => true])
            );
        }
    }
}
