<?php

namespace App\Modules\Addons\Talento\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T D8 — responsable principal + participantes de una orden. Solo datos: el pago no lo lee.
 * Detrás de unificacion.interruptores.participantes_d8.
 */
class OrderParticipantsService
{
    public function activo(): bool
    {
        return (bool) config('unificacion.interruptores.participantes_d8') && Schema::hasTable('talento_order_participants');
    }

    /** Lista ordenada (principal primero). Si no hay filas guardadas, deriva de task_user (el primero = principal) sin escribir. */
    public function listar(string $origen, int $id): array
    {
        if (! $this->activo()) {
            return [];
        }
        $filas = DB::table('talento_order_participants as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.origen', $origen)->where('p.orden_id', $id)
            ->orderByRaw("p.rol = 'principal' desc")->orderBy('p.id')
            ->get(['p.user_id', 'p.rol', 'p.aporte', 'u.name'])
            ->map(fn ($r) => (array) $r)->all();
        if ($filas || $origen !== 'task') {
            return $filas;
        }
        $ids = DB::table('task_user')->where('task_id', $id)->orderBy('user_id')->pluck('user_id');
        $nombres = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');
        return $ids->values()->map(fn ($uid, $i) => [
            'user_id' => $uid, 'rol' => $i === 0 ? 'principal' : 'participante', 'aporte' => null, 'name' => $nombres[$uid] ?? null,
        ])->all();
    }

    /** Fija responsable y participantes. $participantes: [['user_id'=>, 'aporte'=>?], ...]. Aditivo: no toca task_user. */
    public function fijar(string $origen, int $id, int $principalUserId, array $participantes = []): void
    {
        if (! $this->activo()) {
            return;
        }
        DB::transaction(function () use ($origen, $id, $principalUserId, $participantes) {
            DB::table('talento_order_participants')->where('origen', $origen)->where('orden_id', $id)->delete();
            $now = now();
            $filas = [['origen' => $origen, 'orden_id' => $id, 'user_id' => $principalUserId, 'rol' => 'principal', 'aporte' => null, 'created_at' => $now, 'updated_at' => $now]];
            foreach ($participantes as $p) {
                if ((int) $p['user_id'] === $principalUserId) {
                    continue;
                }
                $filas[] = ['origen' => $origen, 'orden_id' => $id, 'user_id' => (int) $p['user_id'], 'rol' => 'participante',
                    'aporte' => $p['aporte'] ?? null, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('talento_order_participants')->insert($filas);
        });
    }
}
