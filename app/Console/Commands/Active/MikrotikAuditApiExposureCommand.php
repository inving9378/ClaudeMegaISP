<?php

namespace App\Console\Commands\Active;

use App\Http\Traits\RouterConnection;
use App\Models\Router;
use Illuminate\Console\Command;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

class MikrotikAuditApiExposureCommand extends Command
{
    use RouterConnection;

    protected $signature = 'mikrotik:audit-api-exposure';
    protected $description = 'Auditoría de solo lectura (item roadmap #979): reporta qué routers Mikrotik activos tienen servicios de gestión (api/api-ssl/winbox/ssh/www) sin restricción de "address" de origen';

    private const SERVICIOS_A_AUDITAR = ['api', 'api-ssl', 'winbox', 'ssh', 'www'];

    public function handle()
    {
        $routers = Router::where('type_of_nas', Router::IS_MIKROTIK)
            ->whereHas('mikrotik', fn ($q) => $q->where('active', true))
            ->with('mikrotik')
            ->get();

        if ($routers->isEmpty()) {
            $this->info('No hay routers Mikrotik activos configurados.');
            return self::SUCCESS;
        }

        $rows = [];
        $expuestos = 0;

        foreach ($routers as $router) {
            $connected = $this->getConnectionByRouter($router);
            if (!$connected) {
                $rows[] = [$router->id, $router->title, '-', 'SIN CONEXIÓN'];
                continue;
            }

            foreach (self::SERVICIOS_A_AUDITAR as $servicio) {
                $printRequest = new Request('/ip/service/print');
                $printRequest->setQuery(Query::where('name', $servicio));

                $address = null;
                $disabled = null;
                foreach ($connected->sendSync($printRequest) as $response) {
                    if ($response->getType() === Response::TYPE_DATA) {
                        $address = $response->getProperty('address');
                        $disabled = $response->getProperty('disabled');
                    }
                }

                if ($disabled === 'true') {
                    continue;
                }

                $sinRestriccion = empty($address);
                if ($sinRestriccion) {
                    $expuestos++;
                }

                $rows[] = [$router->id, $router->title, $servicio, $sinRestriccion ? 'SIN RESTRICCIÓN' : $address];
            }
        }

        $this->table(['Router ID', 'Router', 'Servicio', 'address restringido'], $rows);

        if ($expuestos > 0) {
            $this->warn("{$expuestos} servicio(s) de gestión sin restricción de origen. Ver runbook: docs/runbook-mikrotik-restriccion-api-address.md");
        }

        return self::SUCCESS;
    }
}
