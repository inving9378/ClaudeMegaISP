<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * UNI-T C1 (#877) — Informe de conciliación cuentas / fichas de colaborador / sellers.
 * SOLO LECTURA: no modifica ningún dato. Escribe el detalle (con ids) en
 * storage/app/uni/identidad/ y muestra solo conteos agregados.
 */
class ConciliarIdentidadCommand extends Command
{
    protected $signature = 'identidad:conciliar {--salida= : Carpeta de salida (default storage/app/uni/identidad)}';

    protected $description = 'Clasifica cuentas dueñas de ventas, sellers y fichas de colaborador (solo lectura)';

    public function handle(): int
    {
        $fichas = TalentoColaborador::withTrashed()->get()->keyBy('user_id');
        $sellers = DB::table('sellers')->pluck('id', 'user_id');
        $ventas = DB::table('client_main_information')->whereNotNull('seller_id')
            ->select('seller_id', DB::raw('count(*) as n'))->groupBy('seller_id')->pluck('n', 'seller_id');
        $vendedorRol = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('r.name', 'Vendedor')->where('mr.model_type', 'App\\Models\\User')
            ->pluck('mr.model_id')->flip();

        $ids = collect()->merge($fichas->keys())->merge($sellers->keys())->merge($ventas->keys())
            ->merge($vendedorRol->keys())->unique()->values();
        $users = DB::table('users')->whereIn('id', $ids)
            ->get(['id', 'estado', 'is_system', 'is_seller', 'email'])->keyBy('id');

        // Duplicados: mismo correo en más de una cuenta del universo.
        $dupEmail = $users->filter(fn ($u) => $u->email)->groupBy(fn ($u) => mb_strtolower($u->email))
            ->filter(fn ($g) => $g->count() > 1)->flatten(1)->pluck('id')->flip();

        $casos = [];
        foreach ($ids as $id) {
            $u = $users->get($id);
            $f = $fichas->get($id);
            $banderas = [];
            if (! $u) { $banderas[] = 'cuenta_inexistente'; }
            if ($f && $f->trashed()) { $banderas[] = 'ficha_borrada_logicamente'; }
            if ($f && ! $f->trashed() && $f->status === 'active' && $u && $u->estado !== 'activo') { $banderas[] = 'ficha_activa_cuenta_inactiva'; }
            if ($ventas->has($id) && ! $f) { $banderas[] = 'dueña_de_ventas_sin_ficha'; }
            if ($ventas->has($id) && ! $sellers->has($id)) { $banderas[] = 'dueña_de_ventas_sin_seller'; }
            if ($ventas->has($id) && ! $vendedorRol->has($id)) { $banderas[] = 'dueña_de_ventas_sin_rol_vendedor'; }
            if ($sellers->has($id) && ! $vendedorRol->has($id)) { $banderas[] = 'seller_sin_rol_vendedor'; }
            if ($vendedorRol->has($id) && ! $sellers->has($id)) { $banderas[] = 'rol_vendedor_sin_seller'; }
            if ($dupEmail->has($id)) { $banderas[] = 'correo_repetido'; }

            if ($u && $u->is_system) {
                $cat = 'cuenta_de_sistema';
            } elseif ($dupEmail->has($id)) {
                $cat = 'duplicado';
            } elseif ($f && ! $f->trashed() && $f->status === 'active' && $u && $u->estado === 'activo') {
                $cat = 'persona_activa';
            } elseif ($f || ($u && $u->estado !== 'activo')) {
                $cat = 'persona_historica';
            } else {
                $cat = 'sin_clasificar';
            }

            $casos[] = [
                'user_id' => $id,
                'categoria_propuesta' => $cat,
                'cuenta_estado' => $u->estado ?? null,
                'ficha_id' => $f->id ?? null,
                'ficha_status' => $f->status ?? null,
                'seller_id' => $sellers->get($id),
                'rol_vendedor' => $vendedorRol->has($id),
                'filas_de_ventas' => (int) $ventas->get($id, 0),
                'banderas' => $banderas,
                'requiere_pregunta' => $cat === 'sin_clasificar' || (bool) array_intersect($banderas, [
                    'dueña_de_ventas_sin_ficha', 'ficha_activa_cuenta_inactiva', 'correo_repetido', 'cuenta_inexistente',
                ]),
            ];
        }

        $conteo = fn (callable $fn) => collect($casos)->filter($fn)->count();
        $resumen = [
            'generado' => now()->toDateTimeString(),
            'universo_cuentas' => count($casos),
            'fichas_total' => $fichas->count(),
            'fichas_borradas_logicamente' => $fichas->filter->trashed()->count(),
            'sellers_total' => $sellers->count(),
            'por_categoria' => collect($casos)->countBy('categoria_propuesta')->all(),
            'por_bandera' => collect($casos)->pluck('banderas')->flatten()->countBy()->all(),
            'con_pregunta_para_irving' => $conteo(fn ($c) => $c['requiere_pregunta']),
            'sin_categoria_ni_pregunta' => $conteo(fn ($c) => $c['categoria_propuesta'] === 'sin_clasificar' && ! $c['requiere_pregunta']),
        ];
        $preguntas = [
            ['codigo' => 'dueña_de_ventas_sin_ficha', 'pregunta' => 'Cuentas dueñas de ventas sin ficha: ¿persona histórica (se deja así), cuenta de sistema o persona a dar de alta?'],
            ['codigo' => 'ficha_activa_cuenta_inactiva', 'pregunta' => 'Ficha activa con cuenta inactiva: ¿se reactiva la cuenta o se pasa la ficha a inactiva?'],
            ['codigo' => 'ficha_borrada_logicamente', 'pregunta' => 'Fichas borradas lógicamente bloquean un nuevo alta (UNIQUE user_id): ¿se restauran o se permite re-alta?'],
            ['codigo' => 'seller_sin_rol_vendedor / rol_vendedor_sin_seller', 'pregunta' => '¿Vendedor se define por rol, por fila en sellers o por ambos?'],
            ['codigo' => 'correo_repetido', 'pregunta' => 'Cuentas con correo repetido: ¿cuál es la vigente?'],
        ];

        $dir = $this->option('salida') ?: storage_path('app/uni/identidad');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/conciliacion.json', json_encode(compact('resumen', 'preguntas', 'casos'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line(json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Detalle con ids: {$dir}/conciliacion.json (cero cambios de datos)");

        return self::SUCCESS;
    }
}
