<?php

namespace App\Modules\Addons\MegaFamilia\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Usuarios de prueba para validar /api/megafamilia/auth/login desde el
 * cliente móvil Flutter. No correr en producción real — son credenciales
 * conocidas. Idempotente: actualiza si ya existen.
 */
class MobileTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'CLIENTE', 'guard_name' => 'web']);

        $this->upsertUser(
            email: 'irving@meganet.com',
            name: 'Irving Test',
            login: 'irving',
            password: 'MegaTest2024',
            role: 'Administrador',
        );

        $this->upsertUser(
            email: 'cliente@meganet.com',
            name: 'Cliente Test',
            login: 'cliente',
            password: 'MegaTest2024',
            role: 'CLIENTE',
        );
    }

    private function upsertUser(string $email, string $name, string $login, string $password, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->login_user = $login;
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();
        $user->syncRoles([$role]);
    }
}
