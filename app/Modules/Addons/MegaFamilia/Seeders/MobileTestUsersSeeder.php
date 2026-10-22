<?php

namespace App\Modules\Addons\MegaFamilia\Seeders;

use App\Support\Seguridad\CuentasDemo;
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
        // Seguridad V1.64: sin contraseña en el código y nunca en producción (este seeder
        // reescribe por correo: en prod podría tomar la cuenta real de alguien).
        if (CuentasDemo::detenerSiEsProtegido($this->command)) {
            return;
        }
        Role::firstOrCreate(['name' => 'CLIENTE', 'guard_name' => 'web']);

        $this->upsertUser(
            email: 'irving@meganet.com',
            name: 'Irving Test',
            login: 'irving',
            password: CuentasDemo::contrasena(),
            role: 'Administrador',
        );

        $this->upsertUser(
            email: 'cliente@meganet.com',
            name: 'Cliente Test',
            login: 'cliente',
            password: CuentasDemo::contrasena(),
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
        CuentasDemo::mostrarUnaVez($this->command, $login, $password);
    }
}
