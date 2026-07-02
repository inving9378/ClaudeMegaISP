<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * FASE PAGOS 3 (pieza 1) — Crea el usuario de sistema "Asistente IA" para
 * trazabilidad: las acciones automáticas de la IA (aplicar pagos, etc.) se
 * atribuirán a este usuario (add_by) igual que a Diana/Tere.
 *
 * Login IMPOSIBLE (defensa en profundidad):
 *  - password = bcrypt de un secreto aleatorio nunca divulgado.
 *  - estado = 'inactivo' (LoginController exige estado === 'activo';
 *    'estado' es enum('activo','bloqueado','inactivo'), sin valor 'sistema').
 *  - login_user = 'asistente_ia_sistema' (la columna es NOT NULL; da igual:
 *    aunque se intente por este usuario, el password inusable + estado lo bloquean).
 *  - active = 0.
 *
 * SIN rol/permisos elevados: solo identidad. El permiso de aplicar pagos se
 * le dará en la pieza donde la IA lo use.
 *
 * Idempotente por email (User::SYSTEM_AI_EMAIL).
 */
return new class extends Migration
{
    public function up(): void
    {
        User::updateOrCreate(
            ['email' => User::SYSTEM_AI_EMAIL],
            [
                'name'             => 'Asistente IA',
                'father_last_name' => null,
                'mother_last_name' => null,
                'is_system'        => true,
                'estado'           => 'inactivo',
                'active'           => 0,
                'login_user'       => 'asistente_ia_sistema',
                // Password inusable: nadie conoce el texto plano.
                'password'         => Hash::make(Str::random(64)),
                // Color distintivo (cian) para verla al instante en el historial.
                'color'            => 'rgba(0,184,217,1)',
            ]
        );
    }

    public function down(): void
    {
        // Borrado físico solo del usuario de sistema (seguro: es el bot).
        User::where('email', User::SYSTEM_AI_EMAIL)
            ->where('is_system', true)
            ->delete();
    }
};
