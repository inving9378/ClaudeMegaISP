<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * FASE PAGOS 3 (pieza 1) — Crea el usuario de sistema "MEGAISP" para
 * trazabilidad: las acciones automáticas del sistema/IA (aplicar pagos, etc.)
 * se atribuirán a este usuario (add_by) → en el historial se lee
 * "aplicado por MEGAISP", igual que "aplicado por Diana/Tere".
 *
 * Nombre visible LIMPIO: name='MEGAISP' y apellidos vacíos, así el nombre
 * compuesto (name + father + mother) queda solo "MEGAISP" sin apellidos colgando.
 *
 * Login IMPOSIBLE (defensa en profundidad):
 *  - password = bcrypt de un secreto aleatorio nunca divulgado.
 *  - estado = 'inactivo' (LoginController exige estado === 'activo';
 *    'estado' es enum('activo','bloqueado','inactivo'), sin valor 'sistema').
 *  - login_user = 'megaisp_sistema' (la columna es NOT NULL; da igual: el
 *    password inusable + estado ya bloquean cualquier intento).
 *  - active = 0.
 *
 * SIN rol/permisos elevados: solo identidad. El permiso de aplicar pagos se
 * le dará en la pieza donde el sistema lo use.
 *
 * Idempotente por email (User::SYSTEM_BOT_EMAIL).
 */
return new class extends Migration
{
    public function up(): void
    {
        User::updateOrCreate(
            ['email' => User::SYSTEM_BOT_EMAIL],
            [
                'name'             => 'MEGAISP',
                'father_last_name' => '',
                'mother_last_name' => '',
                'is_system'        => true,
                'estado'           => 'inactivo',
                'active'           => 0,
                'login_user'       => 'megaisp_sistema',
                // Password inusable: nadie conoce el texto plano.
                'password'         => Hash::make(Str::random(64)),
                // Color distintivo (cian) para verlo al instante en el historial.
                'color'            => 'rgba(0,184,217,1)',
            ]
        );
    }

    public function down(): void
    {
        // Borrado solo del usuario de sistema (seguro: es el bot).
        User::where('email', User::SYSTEM_BOT_EMAIL)
            ->where('is_system', true)
            ->delete();
    }
};
