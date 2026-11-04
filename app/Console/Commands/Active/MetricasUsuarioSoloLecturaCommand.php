<?php

namespace App\Console\Commands\Active;

use App\Models\Router;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Item #988: captura (o quita) el usuario de SOLO LECTURA que usa el monitoreo de dedicados en
 * un router. La contraseña se pide oculta (nunca como argumento, para que no quede en el
 * historial del shell) y se guarda cifrada. El router se busca por id o por título: en PROD hay
 * que usar el título, porque los ids de DEV y PROD no coinciden.
 */
class MetricasUsuarioSoloLecturaCommand extends Command
{
    protected $signature = 'metricas:usuario-solo-lectura
        {router : id o título del router (p. ej. ROUTER-01)}
        {usuario? : usuario de solo lectura creado en el MikroTik}
        {--quitar : borra el usuario de solo lectura (el monitoreo vuelve al usuario de respaldo)}';

    protected $description = 'Captura el usuario de solo lectura del MikroTik para el monitoreo de dedicados';

    public function handle(): int
    {
        $clave = (string) $this->argument('router');
        $routers = Router::with('mikrotik')
            ->where(fn ($q) => $q->where('title', $clave)->when(ctype_digit($clave), fn ($q) => $q->orWhere('id', (int) $clave)))
            ->get();

        if ($routers->count() !== 1) {
            $this->error($routers->isEmpty() ? "No hay ningún router «{$clave}»." : "Hay {$routers->count()} routers que coinciden con «{$clave}»: usa el id.");

            return self::FAILURE;
        }

        $router = $routers->first();
        $mikrotik = $router->mikrotik;
        if (! $mikrotik) {
            $this->error("El router «{$router->title}» no tiene datos de API (mikrotiks).");

            return self::FAILURE;
        }

        // Directo a la tabla, NUNCA $mikrotik->save(): MikrotikObserver::updated manda
        // MikrotikRulesJob, que escribe reglas en el router. Aquí no se toca el router.
        $guardar = fn (array $valores) => DB::table('mikrotiks')->where('id', $mikrotik->id)->update($valores);

        if ($this->option('quitar')) {
            $guardar(['login_api_ro' => null, 'password_api_ro' => null]);
            $this->info("Router «{$router->title}»: se quitó el usuario de solo lectura; el monitoreo usará el de respaldo.");

            return self::SUCCESS;
        }

        $usuario = trim((string) $this->argument('usuario'));
        if ($usuario === '') {
            $this->error('Falta el usuario.');

            return self::FAILURE;
        }

        $password = (string) $this->secret("Contraseña de «{$usuario}» en «{$router->title}» (no se muestra)");
        if ($password === '') {
            $this->error('La contraseña no puede ir vacía.');

            return self::FAILURE;
        }

        $guardar(['login_api_ro' => $usuario, 'password_api_ro' => Crypt::encryptString($password)]);
        $this->info("Router «{$router->title}»: usuario de solo lectura «{$usuario}» guardado. Pruébalo con: php artisan metricas:colectar --dry-run");

        return self::SUCCESS;
    }
}
