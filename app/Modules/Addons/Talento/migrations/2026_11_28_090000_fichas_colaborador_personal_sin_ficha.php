<?php

use App\Models\User;
use App\Modules\Addons\Talento\Services\TalentoColaboradorSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 28-nov-2026 (David): todo el personal sale como colaborador. Desde hoy la ficha se crea al guardar el usuario en
 * Administración; esto se la crea a las cuentas activas del personal que ya existían sin ficha, para que entren a la
 * app y salgan para asignarles órdenes. Misma regla que la app (TalentoColaboradorSyncService::asegurarFicha): no
 * toca a clientes ni a «Solo lectura», ni a quien tuvo ficha y se la dieron de baja. Sin el alta de RH (documentos,
 * extensión): esa sigue siendo aparte.
 *
 * Solo agrega filas. El down() no las borra: desde que existen, la persona puede tener órdenes y asistencia ligadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talento_colaboradores') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $ids = DB::table('users as u')
            ->where('u.estado', 'activo')
            ->whereNotExists(fn ($q) => $q->from('talento_colaboradores as c')->whereColumn('c.user_id', 'u.id'))
            ->whereExists(fn ($q) => $q->from('model_has_roles as m')
                ->join('roles as r', 'r.id', '=', 'm.role_id')
                ->whereColumn('m.model_id', 'u.id')
                ->where('m.model_type', User::class)
                ->whereNotIn('r.name', ['client', 'Solo lectura']))
            ->pluck('u.id');

        $servicio = app(TalentoColaboradorSyncService::class);
        $creadas = 0;
        foreach (User::whereIn('id', $ids)->get() as $user) {
            if ($servicio->asegurarFicha($user)) {
                $creadas++;
            }
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            echo "  Fichas de colaborador creadas: {$creadas}\n";
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás automática (ver arriba).
    }
};
