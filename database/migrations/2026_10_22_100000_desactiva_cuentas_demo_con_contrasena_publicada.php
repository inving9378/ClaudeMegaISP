<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seguridad V1.64 (2026-10-22). Las contraseñas de estas cuentas demo estuvieron escritas en
 * seeders del repo, que es público. Si alguna existe en el servidor donde corre la migración,
 * se desactiva y recibe una contraseña aleatoria que nadie conoce; no se borra, para no
 * perder su historial. Sus tokens de la app móvil quedan vencidos.
 *
 * Las cuentas se reconocen sin escribir su contraseña aquí:
 * - manual_demo_tecnico: login exclusivo de la cuenta del manual (TalentoManualDemoAccountSeeder);
 * - irving@meganet.com / cliente@meganet.com con el nombre que les pone MobileTestUsersSeeder
 *   («Irving Test», «Cliente Test»). Por login no: «irving» es también la cuenta real de Irving.
 * Además, la contraseña de portal de los clientes demo de ReferralDemoDataSeeder
 * (correo @demo.meganet.com) se reemplaza por una aleatoria.
 *
 * Idempotente: una segunda corrida vuelve a poner contraseña aleatoria a una cuenta ya inactiva,
 * sin efecto práctico. down() no hace nada: no se reactiva una cuenta con contraseña publicada.
 */
return new class extends Migration
{
    private const CUENTAS = [
        ['login_user' => 'manual_demo_tecnico'],
        ['email' => 'irving@meganet.com', 'name' => 'Irving Test'],
        ['email' => 'cliente@meganet.com', 'name' => 'Cliente Test'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach (self::CUENTAS as $criterio) {
            foreach (DB::table('users')->where($criterio)->get(['id', 'login_user', 'estado']) as $usuario) {
                DB::table('users')->where('id', $usuario->id)->update([
                    'estado' => 'inactivo',
                    'password' => bcrypt(Str::random(64)),
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);

                $tokens = 0;
                if (Schema::hasTable('personal_access_tokens')) {
                    $tokens = DB::table('personal_access_tokens')
                        ->where('tokenable_id', $usuario->id)
                        ->where('tokenable_type', 'like', '%User')
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->update(['expires_at' => now()]);
                }

                $this->registrar($usuario->id, "Cuenta demo desactivada: su contraseña estuvo publicada en el repo. Estado anterior: {$usuario->estado}; tokens de la app vencidos: {$tokens}.");
                Log::warning("[seguridad V1.64] cuenta demo {$usuario->login_user} (#{$usuario->id}) desactivada y con contraseña aleatoria.");
            }
        }

        if (Schema::hasTable('client_main_information')) {
            foreach (DB::table('client_main_information')->where('email', 'like', '%@demo.meganet.com')->pluck('id') as $id) {
                DB::table('client_main_information')->where('id', $id)->update(['password' => Str::random(32)]);
            }
        }
    }

    public function down(): void
    {
        // A propósito vacío: no se reactiva una cuenta cuya contraseña es pública.
    }

    private function registrar(int $usuarioId, string $descripcion): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }
        DB::table('activity_log')->insert([
            'log_name' => 'seguridad',
            'description' => $descripcion,
            'subject_type' => 'App\\Models\\User',
            'subject_id' => $usuarioId,
            'event' => 'updated',
            'properties' => json_encode(['origen' => 'migración V1.64']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
