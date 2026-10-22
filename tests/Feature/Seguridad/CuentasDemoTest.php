<?php

namespace Tests\Feature\Seguridad;

use App\Modules\Addons\Empresa\Seeders\TalentoManualDemoAccountSeeder;
use App\Modules\Addons\MegaFamilia\Seeders\MobileTestUsersSeeder;
use Database\Seeders\ReferralDemoDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * Seguridad V1.64: la migración desactiva las cuentas demo cuya contraseña estuvo publicada (sin
 * tocar la cuenta real «irving»), y los seeders demo no corren en un servidor protegido. BD de
 * pruebas dentro de una transacción que se revierte; usuarios sintéticos.
 */
class CuentasDemoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const MIGRACION = 'database/migrations/2026_10_22_100000_desactiva_cuentas_demo_con_contrasena_publicada.php';

    private function usuario(array $datos): int
    {
        return DB::table('users')->insertGetId($datos + [
            'name' => 'Sintético', 'email' => Str::random(10) . '@example.test', 'login_user' => 'sint_' . Str::random(8),
            'password' => bcrypt('conocida'), 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_desactiva_las_cuentas_demo_y_no_toca_la_cuenta_real(): void
    {
        DB::table('users')->whereIn('login_user', ['manual_demo_tecnico'])->delete();
        $demo = $this->usuario(['login_user' => 'manual_demo_tecnico']);
        $demoMf = $this->usuario(['email' => 'irving@meganet.com', 'name' => 'Irving Test', 'login_user' => 'irving_demo_' . Str::random(4)]);
        $real = $this->usuario(['name' => 'Irving Real', 'login_user' => 'irving_real_' . Str::random(4)]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => $demo, 'name' => 'app', 'token' => hash('sha256', Str::random(40)),
            'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        (require base_path(self::MIGRACION))->up();

        foreach ([$demo, $demoMf] as $id) {
            $u = DB::table('users')->find($id);
            $this->assertSame('inactivo', $u->estado);
            $this->assertFalse(password_verify('conocida', $u->password), 'Recibe una contraseña que nadie conoce.');
        }
        $this->assertNotNull(DB::table('personal_access_tokens')->where('tokenable_id', $demo)->value('expires_at'), 'Sus tokens de la app quedan vencidos.');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'seguridad')->where('subject_id', $demo)->count());

        $intacta = DB::table('users')->find($real);
        $this->assertSame('activo', $intacta->estado);
        $this->assertTrue(password_verify('conocida', $intacta->password));

        // Idempotente: correrla otra vez no truena y la cuenta sigue inactiva.
        (require base_path(self::MIGRACION))->up();
        $this->assertSame('inactivo', DB::table('users')->find($demo)->estado);
    }

    public function test_los_seeders_demo_no_corren_en_un_servidor_protegido(): void
    {
        config(['updates.enabled' => true]);
        $antes = DB::table('users')->count();
        $clientesAntes = DB::table('clients')->count();

        foreach ([TalentoManualDemoAccountSeeder::class, MobileTestUsersSeeder::class, ReferralDemoDataSeeder::class] as $seeder) {
            (new $seeder())->run();
        }

        $this->assertSame($antes, DB::table('users')->count());
        $this->assertSame($clientesAntes, DB::table('clients')->count());
    }

    public function test_los_seeders_demo_ya_no_traen_contrasena_en_el_codigo(): void
    {
        foreach ([
            'app/Modules/Addons/Empresa/Seeders/TalentoManualDemoAccountSeeder.php',
            'app/Modules/Addons/MegaFamilia/Seeders/MobileTestUsersSeeder.php',
            'database/seeders/ReferralDemoDataSeeder.php',
        ] as $archivo) {
            $codigo = file_get_contents(base_path($archivo));
            $this->assertDoesNotMatchRegularExpression("/(PASSWORD\\s*=|password:\\s*|'password'\\s*=>\\s*)'[^']{6,}'/", $codigo, $archivo);
        }
    }
}
