<?php

namespace App\Http\Controllers;

use App\Exports\ClientsActivationPaymentExport;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CommandConfigRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientInternetService;
use App\Models\ClientInvoice;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\NetworkIp;
use App\Models\ObservationTask;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\CheckIndexService;
use App\Services\ClientService\BillingExpirationService;
use App\Services\ClientService\BillingPaymentDateService;
use App\Services\ConfigFinanceNotificationService;
use App\Services\FormatDateService;
use App\Services\MikrotikService;
use App\Services\NetworkIpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\InformationService;
use App\Services\MikrotikScriptService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PEAR2\Net\RouterOS\Response;
use Spatie\Activitylog\Models\Activity;

class TestScriptController extends Controller
{
    use RouterConnection;


    /* public function script(ClientRepository $clientRepository)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0'); // Deshabilita restricciones de clave externa
        $pdf = Pdf::loadView('meganet.plantillas-arregldas.Contrato-Fijo-Revalidacion-12-Meses-recurrente');
        return $pdf->stream();

        DB::statement('SET FOREIGN_KEY_CHECKS=1'); // Habilita restricciones de clave externa
    } */


    public function fixAndTriggerRadius()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        if (!$connection) return "Error de conexión";

        // --- PASO 1: Buscar el ID del Radius con esa IP ---
        $printRequest = new \PEAR2\Net\RouterOS\Request('/radius print');
        $printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('address', '38.123.192.198'));
        $responses = $connection->sendSync($printRequest);

        $radiusId = null;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $radiusId = $r->getProperty('.id');
                break;
            }
        }

        if (!$radiusId) {
            return "No se encontró un servidor Radius con la IP 38.123.192.198";
        }

        // --- PASO 2: Aplicar la configuración usando el ID encontrado ---
        $setRequest = new \PEAR2\Net\RouterOS\Request('/radius set');
        $setRequest->setArgument('.id', $radiusId); // Usamos el ID (ej: *2)
        $setRequest->setArgument('service', 'ppp,login');
        $setRequest->setArgument('timeout', '3000ms');
        $setRequest->setArgument('require-message-auth', 'no');
        $connection->sendSync($setRequest);

        // --- PASO 3: Recuperar acceso a Winbox (Desactivar Radius para login local) ---
        $winboxRequest = new \PEAR2\Net\RouterOS\Request('/user aaa set');
        $winboxRequest->setArgument('use-radius', 'no');
        $connection->sendSync($winboxRequest);

        // --- PASO 4: Forzar reporte cada 1 minuto ---
        $pppRequest = new \PEAR2\Net\RouterOS\Request('/ppp aaa set');
        $pppRequest->setArgument('use-radius', 'yes');
        $pppRequest->setArgument('accounting', 'yes');
        $pppRequest->setArgument('interim-update', '00:01:00');
        $connection->sendSync($pppRequest);

        return "Configuración aplicada con éxito sobre el ID: " . $radiusId;
    }

    public function checkMikrotikIPs()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        // Listamos todas las IPs del MikroTik
        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip address print'));

        $ips = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $ips[] = [
                    'address' => $r->getProperty('address'),
                    'network' => $r->getProperty('network'),
                    'interface' => $r->getProperty('interface'),
                ];
            }
        }
        dd($ips);
    }

    public function setRadiusSource()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        // 1. Buscamos el ID del Radius (38.123.192.198)
        $printRequest = new \PEAR2\Net\RouterOS\Request('/radius print');
        $printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('address', '38.123.192.198'));
        $responses = $connection->sendSync($printRequest);

        $radiusId = null;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $radiusId = $r->getProperty('.id');
                break;
            }
        }

        if ($radiusId) {
            // 2. Seteamos la src-address para que coincida con lo que pusimos en clients.conf
            $setRequest = new \PEAR2\Net\RouterOS\Request('/radius set');
            $setRequest->setArgument('.id', $radiusId);
            $setRequest->setArgument('src-address', '38.123.192.193');
            $connection->sendSync($setRequest);
            return "IP de origen configurada a 38.123.192.193";
        }
        return "No se encontró el RADIUS";
    }

    public function monitorRadiusStatus()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        if (!$connection) return "Error de conexión";

        // 1. Buscamos el ID del servidor Radius (el que tiene la IP de tu App)
        $printRequest = new \PEAR2\Net\RouterOS\Request('/radius print');
        $printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('address', '192.168.105.108'));
        $responses = $connection->sendSync($printRequest);

        $radiusId = null;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $radiusId = $r->getProperty('.id');
                break;
            }
        }

        if (!$radiusId) return "No se encontró el servidor Radius con esa IP";

        // 2. Ejecutamos el comando MONITOR (usamos 'once' para que no se quede pegado el PHP)
        $monitorRequest = new \PEAR2\Net\RouterOS\Request('/radius monitor');
        $monitorRequest->setArgument('numbers', $radiusId);
        $monitorRequest->setArgument('once', ''); // Importante: 'once' para recibir un solo reporte

        $monitorData = $connection->sendSync($monitorRequest);

        $results = [];
        foreach ($monitorData as $m) {
            if ($m->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $results = [
                    'requests' => $m->getProperty('requests'),
                    'responses' => $m->getProperty('responses'),
                    'timeouts' => $m->getProperty('timeouts'),
                    'pending' => $m->getProperty('pending'),
                    'state' => $m->getProperty('state'),
                ];
            }
        }

        dd([
            'radius_id' => $radiusId,
            'status' => $results
        ]);
    }

    public function finalRadiusFix()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        if (!$connection) return "Error de conexión";

        // 1. Buscamos el ID del Radius (buscamos el que tenga comentario o la IP vieja)
        $printRequest = new \PEAR2\Net\RouterOS\Request('/radius print');
        $responses = $connection->sendSync($printRequest);

        $radiusId = null;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                // Usamos el que ya tenías como producción (*2)
                if ($r->getProperty('.id') == '*2' || $r->getProperty('comment') == 'RADIUS_PRODUCCION_MEGANET') {
                    $radiusId = $r->getProperty('.id');
                    break;
                }
            }
        }

        if (!$radiusId) return "No se encontró el objeto Radius para editar";

        // 2. CONFIGURACIÓN CRÍTICA:
        $setRequest = new \PEAR2\Net\RouterOS\Request('/radius set');
        $setRequest->setArgument('.id', $radiusId);
        $setRequest->setArgument('address', '192.168.105.108');
        $setRequest->setArgument('src-address', '192.168.105.1');
        $setRequest->setArgument('secret', 'meganet123');
        $setRequest->setArgument('timeout', '3000ms');
        $setRequest->setArgument('service', 'ppp,login');
        $connection->sendSync($setRequest);

        // 3. ASEGURAR EL REPORTE DE CONSUMO
        $pppRequest = new \PEAR2\Net\RouterOS\Request('/ppp aaa set');
        $pppRequest->setArgument('use-radius', 'yes');
        $pppRequest->setArgument('accounting', 'yes');
        $pppRequest->setArgument('interim-update', '00:01:00'); // Reporte cada 1 minuto
        $connection->sendSync($pppRequest);

        return "Configuración terminada. Mikrotik (105.1) -> Servidor (105.108)";
    }

    public function triggerAuthTraffic()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/user aaa set use-radius=yes'));

        return "Regla de login activada. Ahora intenta entrar al MikroTik por SSH o Winbox con un usuario falso.";
    }

    public function snifferWide()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip firewall mangle print'));

        $rules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $rules[] = [
                    'id' => $r->getProperty('.id'),
                    'chain' => $r->getProperty('chain'),
                    'action' => $r->getProperty('action'),
                    'dst-port' => $r->getProperty('dst-port'),
                    'new-dst-address' => $r->getProperty('new-dst-address'),
                    'comment' => $r->getProperty('comment'),
                ];
            }
        }
        dd($rules);
    }

    public function networkAudit()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);
        $serverIp = '38.123.192.198';

        $audit = [];

        // 1. ¿Cómo resuelve el router la ruta al servidor?
        $audit['ruta_exacta'] = $connection->sendSync(
            (new \PEAR2\Net\RouterOS\Request('/ip route get'))->setArgument('address', $serverIp)
        )->getProperty('interface');

        // 2. ¿Qué DNS tiene el router? (Por el misterio del puerto 53)
        $audit['config_dns'] = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip dns print'));

        // 3. ¿Hay algún "secuestro" de DNS en NAT?
        $natRequest = new \PEAR2\Net\RouterOS\Request('/ip firewall nat print');
        $natRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('dst-port', '53'));
        $audit['nat_dns'] = $connection->sendSync($natRequest);

        // 4. Ver todas las IPs que tiene el BRIDGE ADMIN
        $ipRequest = new \PEAR2\Net\RouterOS\Request('/ip address print');
        $ipRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('interface', 'BRIDGE ADMIN'));
        $audit['ips_bridge'] = $connection->sendSync($ipRequest);

        dd($audit);
    }

    public function switchToPrivate()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        // 1. Buscamos el ID del Radius (*2)
        $printRequest = new \PEAR2\Net\RouterOS\Request('/radius print');
        $responses = $connection->sendSync($printRequest);
        $radiusId = $responses[0]->getProperty('.id');

        // 2. Apuntamos a la IP privada del servidor (105.108)
        // y usamos la IP privada del Mikrotik como origen (105.1)
        $setRequest = new \PEAR2\Net\RouterOS\Request('/radius set');
        $setRequest->setArgument('.id', $radiusId);
        $setRequest->setArgument('address', '192.168.105.108');
        $setRequest->setArgument('src-address', '192.168.105.1');
        $setRequest->setArgument('service', 'ppp');
        $connection->sendSync($setRequest);

        return "Mikrotik re-configurado por la red privada.";
    }


    public function snifferCheck()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/tool sniffer quick');
        // Buscamos paquetes que vayan al puerto 1813 en CUALQUIER interfaz
        $request->setArgument('port', '1813');
        $request->setArgument('duration', '20s');

        $responses = $connection->sendSync($request);

        $data = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $data[] = [
                    'from' => $r->getProperty('src-address'),
                    'to' => $r->getProperty('dst-address'),
                    'interface' => $r->getProperty('interface'),
                ];
            }
        }
        dd($data);
    }

    public function traceNatErrors($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Obtenemos todas las reglas de NAT
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $responses = $connection->sendSync($request);

        $suspiciousRules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $toPorts = $r->getProperty('to-ports');
                $action = $r->getProperty('action');

                // Buscamos reglas que redirijan al 1813 o que no tengan puerto definido
                if ($toPorts == '1813' || ($action == 'redirect' && !$r->getProperty('dst-port'))) {
                    $suspiciousRules[] = [
                        'id' => $r->getProperty('.id'),
                        'chain' => $r->getProperty('chain'),
                        'action' => $action,
                        'protocol' => $r->getProperty('protocol') ?? 'TODOS (Peligro)',
                        'dst-port' => $r->getProperty('dst-port') ?? 'TODOS (Peligro)',
                        'to-ports' => $toPorts,
                        'comment' => $r->getProperty('comment') ?? 'Sin comentario',
                        'src-address-list' => $r->getProperty('src-address-list') ?? 'Cualquiera'
                    ];
                }
            }
        }
        return $suspiciousRules;
    }

    public function resetAAA()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        // 1. Apagamos el uso de Radius un segundo
        $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp aaa set use-radius=no'));

        sleep(1); // Esperamos un momento

        // 2. Lo encendemos de nuevo con un tiempo de reporte MUY AGRESIVO (10 segundos)
        // Esto es solo para la prueba, luego lo subiremos a 5 min.
        $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp aaa set use-radius=yes accounting=yes interim-update=00:00:10'));

        return "Servicio AAA reiniciado. Ahora el Mikrotik DEBE intentar enviar cada 10 segundos.";
    }


    public function logClient(Request $request)
    {
        $fechaInicioRaw = $request->get('fecha_inicio');
        $fechaFinRaw = $request->get('fecha_fin');

        // 2. Parsear con Carbon para asegurar el principio y fin del día
        try {
            $fechaInicio = Carbon::parse($fechaInicioRaw)->startOfDay();
            $fechaFin = Carbon::parse($fechaFinRaw)->endOfDay();
        } catch (\Exception $e) {
            // En caso de que el formato de fecha enviado sea inválido
            return response("Formato de fecha inválido", 400);
        }
        $clientId = $request->get('client_id') ?? null;

        $query = Activity::select([
            'id',
            'description',
            'client_id',
            'causer_type',
            'event',
            'subject_type',
            'subject_id',
            'properties',
            'created_at as fechas'
        ])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        if ($clientId) {
            $query->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                    ->orWhere('description', 'like', "%$clientId%")
                    ->orWhere('subject_id', $clientId)
                    ->orWhere('properties->client_id', $clientId)
                    ->orWhere('properties->attributes->client_id', $clientId)
                    ->orWhere('properties->paymentable_id', $clientId)
                    ->orWhere('properties->attributes->paymentable_id', $clientId);
            });
        }

        $logs = $query->get();
        // Empezamos a construir el HTML en una variable
        $html = '
    <div style="font-family: sans-serif; padding: 20px;">
        <h2>Log de Actividades (' . $fechaInicio . ' - ' . $fechaFin . '</h2>
        <table border="1" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background-color: #333; color: white; text-align: left;">
                 <th style="padding: 10px;">Id del Log</th>
                    <th style="padding: 10px;">Fecha</th>
                    <th style="padding: 10px;">Evento</th>
                    <th style="padding: 10px;">Descripción</th>
                    <th style="padding: 10px;">Client ID</th>
                    <th style="padding: 10px;">Sujeto</th>
                    <th style="padding: 10px;">Causante</th>
                    <th style="padding: 10px;">Propiedades</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($logs as $log) {
            // Formatear propiedades JSON para que no se rompa el HTML
            $properties = json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $html .= "
            <tr>
                <td style='padding: 8px; border: 1px solid #ccc;'>{$log->id}</td>
                <td style='padding: 8px; border: 1px solid #ccc;'>{$log->fechas}</td>
                <td style='padding: 8px; border: 1px solid #ccc;'><strong>{$log->event}</strong></td>
                <td style='padding: 8px; border: 1px solid #ccc;'>{$log->description}</td>
                <td style='padding: 8px; border: 1px solid #ccc;'>" . ($log->client_id ?? 'N/A') . "</td>
                <td style='padding: 8px; border: 1px solid #ccc;'>{$log->subject_type}</td>
                <td style='padding: 8px; border: 1px solid #ccc;'>{$log->causer_type}</td>
                <td style='padding: 8px; border: 1px solid #ccc;'>
                    <pre style='margin:0; font-size: 11px; max-height: 300px; overflow: auto;'>{$properties}</pre>
                </td>
            </tr>";
        }

        $html .= '
            </tbody>
        </table>
    </div>';

        return response($html);

    }

    public function checkRadiusStats($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $request->setArgument('stats', ''); // Esto trae estadísticas de éxito/fallo

        $responses = $connection->sendSync($request);
        $stats = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $stats[] = [
                    'address' => $r->getProperty('address'),
                    'requests' => $r->getProperty('requests'),
                    'accepts' => $r->getProperty('accepts'), // Login OK
                    'rejects' => $r->getProperty('rejects'), // Login Fallido
                    'timeouts' => $r->getProperty('timeouts'), // NO HAY CONEXION
                    'pending' => $r->getProperty('pending'),
                ];
            }
        }
        return $stats;
    }

    public function snifferRadiusSpecific($routerId, $linuxServerIp)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Solo capturamos lo que entra o sale hacia la IP de tu servidor Linux
        $request = new \PEAR2\Net\RouterOS\Request('/tool sniffer quick');
        $request->setArgument('ip-address', $linuxServerIp);
        $request->setArgument('duration', '15s');

        $responses = $connection->sendSync($request);

        $packets = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $packets[] = [
                    'time' => $r->getProperty('time'),
                    'src' => $r->getProperty('src-address'),
                    'dst' => $r->getProperty('dst-address'),
                    'proto' => $r->getProperty('protocol'),
                    'size' => $r->getProperty('size'),
                    'interface' => $r->getProperty('interface')
                ];
            }
        }
        return $packets;
    }

    public function auditRadiusConfig($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $audit = [];

        // 1. Ver si RADIUS está activo para PPP (Acceso de clientes)
        $audit['ppp_aaa'] = $this->getItems($connection, '/ppp/aaa/');

        // 2. Ver la configuración del servidor RADIUS (IP, Secret, Puertos)
        $audit['radius_server'] = $this->getItems($connection, '/radius/');

        // 3. Ver si hay algún "Domain" o configuración que filtre el tráfico
        $audit['radius_incoming'] = $this->getItems($connection, '/radius/incoming/');

        // 4. Ver si el perfil de los usuarios tiene RADIUS activado
        $audit['ppp_profiles'] = $this->getItems($connection, '/ppp/profile/');

        return $audit;
    }

// Método auxiliar para limpiar la respuesta (puedes ponerlo en tu Trait)
    protected function getItems($connection, $command)
    {
        $request = new \PEAR2\Net\RouterOS\Request($command . 'print');
        $responses = $connection->sendSync($request);
        $data = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $data[] = iterator_to_array($r);
            }
        }
        return $data;
    }

    public function checkArpServer($routerId, $serverIp = '192.168.105.108')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/arp/print');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('address', $serverIp));

        $responses = $connection->sendSync($request);
        $data = [];
        foreach ($responses as $r) {
            $data[] = iterator_to_array($r);
        }
        return $data; // Si vuelve vacío y el servidor es local, hay un problema de cable/vlan.
    }

    public function pingTest($routerId, $serverIp = '192.168.105.108', $srcIp = '192.168.105.1')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Ejecutamos un ping simulando ser el cliente RADIUS
        $request = new \PEAR2\Net\RouterOS\Request('/ping');
        $request->setArgument('address', $serverIp);
        $request->setArgument('src-address', $srcIp);
        $request->setArgument('count', '3');

        $responses = $connection->sendSync($request);
        $results = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $results[] = [
                    'host' => $r->getProperty('host'),
                    'status' => $r->getProperty('status') ?? 'sent',
                    'time' => $r->getProperty('time')
                ];
            }
        }
        return $results;
    }

    public function findRadiusHijack($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Buscamos en NAT cualquier regla que use el puerto 1813
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $responses = $connection->sendSync($request);

        $found = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $dstPort = $r->getProperty('dst-port');
                $toPorts = $r->getProperty('to-ports');

                if ($dstPort == '1813' || $toPorts == '1813' || $dstPort == '1812') {
                    $found[] = [
                        'chain' => $r->getProperty('chain'),
                        'dst-address' => $r->getProperty('dst-address') ?? 'todas',
                        'to-addresses' => $r->getProperty('to-addresses') ?? 'sin cambio',
                        'action' => $r->getProperty('action'),
                        'comment' => $r->getProperty('comment') ?? 'Sin comentario'
                    ];
                }
            }
        }
        return $found;
    }

    public function checkFirewallOutput($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/filter/print');
        // Buscamos reglas en la cadena 'output' (tráfico generado por el MikroTik)
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('chain', 'output'));

        $responses = $connection->sendSync($request);
        $rules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $rules[] = [
                    'action' => $r->getProperty('action'),
                    'protocol' => $r->getProperty('protocol') ?? 'todos',
                    'dst-port' => $r->getProperty('dst-port') ?? 'todos',
                    'comment' => $r->getProperty('comment') ?? 'Sin comentario'
                ];
            }
        }
        return $rules;
    }

    public function countActiveSessions($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ppp/active/print');
        $responses = $connection->sendSync($request);

        $count = 0;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) $count++;
        }
        return $count; // Si esto es 0, por eso el sniffer está vacío.
    }

    public function verifySrcAddress($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/address/print');
        $responses = $connection->sendSync($request);

        $addresses = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $addresses[] = [
                    'address' => $r->getProperty('address'),
                    'interface' => $r->getProperty('interface')
                ];
            }
        }
        return $addresses; // Busca si 192.168.105.1 está aquí.
    }

    public function checkMangleOutput($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/print');
        // Buscamos reglas que afecten a la cadena 'output'
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('chain', 'output'));

        $responses = $connection->sendSync($request);
        $rules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $rules[] = [
                    'action' => $r->getProperty('action'),
                    'new-routing-mark' => $r->getProperty('new-routing-mark'),
                    'comment' => $r->getProperty('comment')
                ];
            }
        }
        return $rules; // Si hay reglas aquí, podrían estar desviando el RADIUS a Internet.
    }

    public function fixRadiusLocalRouting($routerId, $linuxServerIp = '192.168.105.108')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Comprobamos si ya existe la regla para no duplicarla
        $check = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/print');
        $check->setQuery(\PEAR2\Net\RouterOS\Query::where('dst-address', $linuxServerIp));
        $exists = $connection->sendSync($check);

        if (count($exists) == 0) {
            // Añadimos una regla de ACCEPT al principio del Mangle Output
            // Esto hace que el tráfico al servidor Linux ignore cualquier balanceo de carga
            $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/add');
            $request->setArgument('chain', 'output');
            $request->setArgument('dst-address', $linuxServerIp);
            $request->setArgument('action', 'accept');
            $request->setArgument('place-before', '0'); // Crucial: Ponerla de primero
            $request->setArgument('comment', 'EXCLUIR_RADIUS_DE_BALANCEO');

            $connection->sendSync($request);
            return "Regla de exclusión creada. Prueba el sniffer ahora.";
        }

        return "La regla ya existía.";
    }

    public function checkMangleStats($routerId, $linuxServerIp = '192.168.105.108')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/print');
        $request->setArgument('stats', '');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('dst-address', $linuxServerIp));

        $responses = $connection->sendSync($request);
        $results = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $results[] = [
                    'chain' => $r->getProperty('chain'),
                    'packets' => $r->getProperty('packets'), // ¡ESTO ES LO IMPORTANTE!
                    'bytes' => $r->getProperty('bytes'),
                    'comment' => $r->getProperty('comment')
                ];
            }
        }
        return $results;
    }

    public function deepSniffer($routerId, $linuxServerIp = '192.168.105.108')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Sniffer filtrado por la IP del servidor en el puerto 1813
        $request = new \PEAR2\Net\RouterOS\Request('/tool sniffer quick');
        $request->setArgument('ip-address', $linuxServerIp);
        $request->setArgument('port', '1813');
        $request->setArgument('duration', '10s');

        $responses = $connection->sendSync($request);
        $data = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $data[] = [
                    'src' => $r->getProperty('src-address'),
                    'dst' => $r->getProperty('dst-address'),
                    'interface' => $r->getProperty('interface'), // ¿Por dónde sale realmente?
                    'direction' => $r->getProperty('direction') // rx (entra) o tx (sale)
                ];
            }
        }
        return $data;
    }

    public function checkFirewallOrder($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/filter/print');
        $request->setArgument('stats', '');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('chain', 'output'));

        $responses = $connection->sendSync($request);
        $rules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $rules[] = [
                    'id' => $r->getProperty('.id'),
                    'action' => $r->getProperty('action'),
                    'packets' => $r->getProperty('packets'),
                    'comment' => $r->getProperty('comment') ?? 'Sin comentario'
                ];
            }
        }
        return $rules; // Si ves un "drop" con paquetes arriba de tus "accept", ahí está el problema.
    }

    public function findPhantomIpInNat($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        // Buscamos cualquier mención a la IP que vimos en el sniffer original
        $responses = $connection->sendSync($request);

        $found = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $toAddr = $r->getProperty('to-addresses');
                $dstAddr = $r->getProperty('dst-address');

                if (str_contains($toAddr, '38.123.192') || str_contains($dstAddr, '38.123.192')) {
                    $found[] = [
                        'chain' => $r->getProperty('chain'),
                        'action' => $r->getProperty('action'),
                        'to-addresses' => $toAddr,
                        'comment' => $r->getProperty('comment') ?? 'Sin comentario'
                    ];
                }
            }
        }
        return $found;
    }

    public function fixRadiusNatBypass($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. IP del MikroTik y del Servidor
        $srcIp = '192.168.105.1';
        $dstIp = '192.168.105.108';

        // 2. Comprobamos si ya existe la regla de bypass
        $check = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $check->setQuery(\PEAR2\Net\RouterOS\Query::where('comment', 'BYPASS_RADIUS_NAT'));
        $exists = $connection->sendSync($check);

        if (count($exists) == 0) {
            // 3. Creamos la regla de ACCEPT en SRCNAT en la posición 0
            $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
            $request->setArgument('chain', 'srcnat');
            $request->setArgument('src-address', $srcIp);
            $request->setArgument('dst-address', $dstIp);
            $request->setArgument('action', 'accept'); // ACCEPT en NAT significa "No aplicar NAT"
            $request->setArgument('place-before', '0'); // Al principio de todo
            $request->setArgument('comment', 'BYPASS_RADIUS_NAT');

            $connection->sendSync($request);
            return "Regla de Bypass NAT creada con éxito.";
        }

        return "La regla de Bypass ya existe.";
    }

    public function inspectBypassRule($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Buscamos la regla por su comentario y pedimos las estadísticas
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $request->setArgument('stats', '');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('comment', 'BYPASS_RADIUS_NAT'));

        $responses = $connection->sendSync($request);
        $data = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $data[] = [
                    'id' => $r->getProperty('.id'),
                    'packets' => $r->getProperty('packets'),
                    'src-address' => $r->getProperty('src-address'),
                    'dst-address' => $r->getProperty('dst-address'),
                    'action' => $r->getProperty('action'),
                ];
            }
        }
        return $data;
    }

    public function checkRadiusClientHealth($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $request->setArgument('stats', '');
        $responses = $connection->sendSync($request);

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                return [
                    'address' => $r->getProperty('address'),
                    'requests' => $r->getProperty('requests'),
                    'accepts' => $r->getProperty('accepts'),
                    'rejects' => $r->getProperty('rejects'),
                    'timeouts' => $r->getProperty('timeouts'), // SI ESTO SUBE, EL PROBLEMA ES EL SERVER O LA RUTA
                    'pending' => $r->getProperty('pending'),
                ];
            }
        }
    }

    public function setupPerfectRadius($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. IPs Correctas
        $serverIp = '192.168.105.108'; // Tu servidor Linux (Privada)
        $mikrotikIp = '192.168.105.1'; // Tu MikroTik (Privada)
        $secret = 'meganet123';

        // 2. Limpiar RADIUS (Borrar todo lo anterior para evitar conflictos de IDs)
        $allRadius = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/radius/print'));
        foreach ($allRadius as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $del = new \PEAR2\Net\RouterOS\Request('/radius/remove');
                $del->setArgument('numbers', $r->getProperty('.id'));
                $connection->sendSync($del);
            }
        }

        // 3. Crear el nuevo RADIUS limpio
        $addRadius = new \PEAR2\Net\RouterOS\Request('/radius/add');
        $addRadius->setArgument('service', 'ppp');
        $addRadius->setArgument('address', $serverIp);
        $addRadius->setArgument('src-address', $mikrotikIp); // Forzamos que salga por la red local
        $addRadius->setArgument('secret', $secret);
        $addRadius->setArgument('authentication-port', '1812');
        $addRadius->setArgument('accounting-port', '1813');
        $addRadius->setArgument('timeout', '3s');
        $addRadius->setArgument('comment', 'RADIUS_PRODUCCION_FINAL');
        $connection->sendSync($addRadius);

        // 4. Configurar el motor de PPP para que use este RADIUS
        $pppAaa = new \PEAR2\Net\RouterOS\Request('/ppp/aaa/set');
        $pppAaa->setArgument('use-radius', 'yes');
        $pppAaa->setArgument('accounting', 'yes');
        $pppAaa->setArgument('interim-update', '00:05:00'); // IMPORTANTE: Cada 5 min, no cada 10 seg.
        $connection->sendSync($pppAaa);

        return "MikroTik re-configurado con éxito.";
    }

    private function updateAAA()
    {
        $router = Router::find(2);
        $connection = $this->getConnectionByRouter($router);

        $pppAaa = new \PEAR2\Net\RouterOS\Request('/ppp/aaa/set');
        $pppAaa->setArgument('use-radius', 'yes');
        $pppAaa->setArgument('accounting', 'yes');
        $pppAaa->setArgument('interim-update', '00:01:00'); // Cambiado a 1 minuto
        $connection->sendSync($pppAaa);

        return "Intervalo actualizado a 1 minuto. Espera 60 segundos para ver cambios.";

    }

    public function checkPppConfig($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ppp/aaa/print');
        $responses = $connection->sendSync($request);

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                return [
                    'use-radius' => $r->getProperty('use-radius'),
                    'accounting' => $r->getProperty('accounting'),
                    'interim-update' => $r->getProperty('interim-update'), // Aquí debe decir 00:01:00
                ];
            }
        }
    }

    public function monitorRadiusLive($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Consultamos el monitor del servidor radius (asumiendo que es el ID *1 o el primero)
        $request = new \PEAR2\Net\RouterOS\Request('/radius/monitor');
        $request->setArgument('numbers', '0'); // El primer servidor configurado
        $request->setArgument('once', '');    // Solo una lectura

        $response = $connection->sendSync($request);

        foreach ($response as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                return [
                    'requests' => $r->getProperty('requests'),
                    'timeouts' => $r->getProperty('timeouts'),
                    'pending' => $r->getProperty('pending'),
                    'accepts' => $r->getProperty('accepts'),
                    'rejects' => $r->getProperty('rejects'),
                ];
            }
        }
    }

    public function listAllIps($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/address/print'));

        $ips = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $ips[] = [
                    'address' => $r->getProperty('address'),
                    'interface' => $r->getProperty('interface'),
                    'network' => $r->getProperty('network')
                ];
            }
        }
        return $ips;
    }

    public function findTheSaboteur($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $results = [];

        // 1. Buscamos en NAT reglas que redirijan al puerto 22
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $responses = $connection->sendSync($request);
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                if ($r->getProperty('to-ports') == '22' || $r->getProperty('dst-port') == '22') {
                    $results['nat_rules'][] = [
                        'id' => $r->getProperty('.id'),
                        'chain' => $r->getProperty('chain'),
                        'comment' => $r->getProperty('comment') ?? 'Sin comentario',
                        'to-addresses' => $r->getProperty('to-addresses')
                    ];
                }
            }
        }

        // 2. Buscamos en MANGLE reglas que marquen tráfico hacia tu servidor
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/print');
        $responses = $connection->sendSync($request);
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                if ($r->getProperty('dst-address') == '192.168.105.108') {
                    $results['mangle_rules'][] = [
                        'id' => $r->getProperty('.id'),
                        'action' => $r->getProperty('action'),
                        'comment' => $r->getProperty('comment') ?? 'Sin comentario'
                    ];
                }
            }
        }

        return $results;
    }

    public function fixWebAccessAndRadius($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $serverIp = '192.168.105.108';

        // 1. DESACTIVAR LA REGLA SABOTEADORA (*9D)
        $disable = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/disable');
        $disable->setArgument('numbers', '*9D');
        $connection->sendSync($disable);

        // 2. CREAR REGLA ESPECÍFICA PARA HTTP (Puerto 80)
        // Solo redirige tráfico TCP al puerto 80, no toca el UDP/RADIUS
        $httpReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $httpReq->setArgument('chain', 'dstnat');
        $httpReq->setArgument('protocol', 'tcp');
        $httpReq->setArgument('dst-port', '80');
        $httpReq->setArgument('action', 'dst-nat');
        $httpReq->setArgument('to-addresses', $serverIp);
        $httpReq->setArgument('to-ports', '80');
        $httpReq->setArgument('comment', 'ACCESO_WEB_MEGANET_HTTP');
        $connection->sendSync($httpReq);

        // 3. CREAR REGLA ESPECÍFICA PARA HTTPS (Puerto 443)
        $httpsReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $httpsReq->setArgument('chain', 'dstnat');
        $httpsReq->setArgument('protocol', 'tcp');
        $httpsReq->setArgument('dst-port', '443');
        $httpsReq->setArgument('action', 'dst-nat');
        $httpsReq->setArgument('to-addresses', $serverIp);
        $httpsReq->setArgument('to-ports', '443');
        $httpsReq->setArgument('comment', 'ACCESO_WEB_MEGANET_HTTPS');
        $connection->sendSync($httpsReq);

        return "Operación completada: Regla *9D desactivada y accesos web 80/443 creados correctamente.";
    }

    public function restoreSSH($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $serverIp = '192.168.105.108';

        // Creamos una regla específica para SSH
        // Esto mapea el puerto 22 del MikroTik al puerto 22 del Servidor
        $sshReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $sshReq->setArgument('chain', 'dstnat');
        $sshReq->setArgument('protocol', 'tcp');
        $sshReq->setArgument('dst-port', '22'); // Puerto que usas para conectar desde afuera
        $sshReq->setArgument('action', 'dst-nat');
        $sshReq->setArgument('to-addresses', $serverIp);
        $sshReq->setArgument('to-ports', '22');
        $sshReq->setArgument('comment', 'ACCESO_SSH_SEGURO_RECUPERADO');
        $sshReq->setArgument('place-before', '0'); // La ponemos de primera para asegurar

        $connection->sendSync($sshReq);

        return "Regla SSH creada. Intenta entrar de nuevo por SSH ahora mismo.";
    }

    public function emergencyRestore($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $serverIp = '192.168.105.108';

        // A. Re-activar la regla vieja para recuperar acceso YA
        $enableOld = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/enable');
        $enableOld->setArgument('numbers', '*9D');
        $connection->sendSync($enableOld);

        // B. Crear la regla SSH limpia (por si la vieja no funciona)
        $sshReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $sshReq->setArgument('chain', 'dstnat');
        $sshReq->setArgument('protocol', 'tcp');
        $sshReq->setArgument('dst-port', '22');
        $sshReq->setArgument('action', 'dst-nat');
        $sshReq->setArgument('to-addresses', $serverIp);
        $sshReq->setArgument('to-ports', '22');
        $sshReq->setArgument('comment', 'RESCATE_SSH');
        $sshReq->setArgument('place-before', '0');
        $connection->sendSync($sshReq);

        return "Acceso de emergencia ejecutado. Intenta conectar por SSH ahora.";
    }


    public function finalCleanAndFix($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);
        $serverIp = '192.168.105.108';

        // 1. DESACTIVAR LA REGLA "SABOTEADORA" DEFINITIVAMENTE (*9D)
        $disableReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/disable');
        $disableReq->setArgument('numbers', '*9D');
        $connection->sendSync($disableReq);

        // 2. CREAR REGLA ESPECÍFICA PARA SSH (Puerto 22)
        $sshReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $sshReq->setArgument('chain', 'dstnat');
        $sshReq->setArgument('protocol', 'tcp');
        $sshReq->setArgument('dst-port', '22');
        $sshReq->setArgument('action', 'dst-nat');
        $sshReq->setArgument('to-addresses', $serverIp);
        $sshReq->setArgument('to-ports', '22');
        $sshReq->setArgument('comment', 'PUERTO_SSH_SEGURO');
        $connection->sendSync($sshReq);

        // 3. CREAR REGLA ESPECÍFICA PARA WEB HTTP (Puerto 80)
        $httpReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $httpReq->setArgument('chain', 'dstnat');
        $httpReq->setArgument('protocol', 'tcp');
        $httpReq->setArgument('dst-port', '80');
        $httpReq->setArgument('action', 'dst-nat');
        $httpReq->setArgument('to-addresses', $serverIp);
        $httpReq->setArgument('to-ports', '80');
        $httpReq->setArgument('comment', 'PUERTO_WEB_HTTP');
        $connection->sendSync($httpReq);

        // 4. CREAR REGLA ESPECÍFICA PARA WEB HTTPS (Puerto 443)
        $httpsReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $httpsReq->setArgument('chain', 'dstnat');
        $httpsReq->setArgument('protocol', 'tcp');
        $httpsReq->setArgument('dst-port', '443');
        $httpsReq->setArgument('action', 'dst-nat');
        $httpsReq->setArgument('to-addresses', $serverIp);
        $httpsReq->setArgument('to-ports', '443');
        $httpsReq->setArgument('comment', 'PUERTO_WEB_HTTPS');
        $connection->sendSync($httpsReq);

        // 5. REGLA DE ORO: BYPASS PARA RADIUS (Local a Local) en SRC-NAT
        $bypassReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $bypassReq->setArgument('chain', 'srcnat');
        $bypassReq->setArgument('src-address', '192.168.105.1');
        $bypassReq->setArgument('dst-address', $serverIp);
        $bypassReq->setArgument('action', 'accept');
        $bypassReq->setArgument('place-before', '0');
        $bypassReq->setArgument('comment', 'BYPASS_LOCAL_RADIUS');
        $connection->sendSync($bypassReq);

        return "Limpieza completada exitosamente. SSH, Web y Radius ahora están separados.";
    }

    public function inspectEmergencyRule($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('.id', '*9D'));
        $responses = $connection->sendSync($request);

        $details = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                // Esto nos dirá exactamente qué pide la regla para funcionar
                return iterator_to_array($r);
            }
        }
    }

    public function definitiveFix($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);
        $serverIp = '192.168.105.108';

        // 1. REGLA DE PROTECCIÓN RADIUS (DST-NAT)
        // Decimos: "Si el destino es el puerto 1812 o 1813 UDP, NO APLIQUES NAT"
        // Esto evita que cualquier otra regla 'atrapa-todo' lo desvíe.
        $protRadius = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $protRadius->setArgument('chain', 'dstnat');
        $protRadius->setArgument('protocol', 'udp');
        $protRadius->setArgument('dst-port', '1812,1813');
        $protRadius->setArgument('action', 'accept');
        $protRadius->setArgument('place-before', '0'); // Al principio de todo
        $protRadius->setArgument('comment', 'PROTECCION_ENTRADA_RADIUS');
        $connection->sendSync($protRadius);
        return "Reglas de protección creadas. Ahora puedes apagar la regla *9D sin miedo.";
    }

    public function checkActiveSessions($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);
        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp/active/print'));

        $count = 0;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) $count++;
        }
        return "Clientes conectados actualmente: " . $count;
    }

    public function verifyRadiusService($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);
        $request = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $responses = $connection->sendSync($request);

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                return [
                    'services' => $r->getProperty('service'), // Debe decir "ppp"
                    'address' => $r->getProperty('address'),
                    'disabled' => $r->getProperty('disabled'),
                ];
            }
        }
    }

    public function refreshRadiusProcess($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. Buscamos el ID del radius
        $print = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/radius/print'));
        $id = $print->getProperty('.id');

        // 2. Apagar y encender
        $connection->sendSync((new \PEAR2\Net\RouterOS\Request('/radius/disable'))->setArgument('numbers', $id));
        sleep(1);
        $connection->sendSync((new \PEAR2\Net\RouterOS\Request('/radius/enable'))->setArgument('numbers', $id));

        return "Proceso RADIUS reiniciado. Monitorea los números ahora.";
    }

    public function checkSessionsRadiusFlag($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp/active/print'));

        $withRadius = 0;
        $withoutRadius = 0;
        //10.2.1.200

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                // La propiedad 'radius' es true si la sesión está siendo gestionada por RADIUS
                if ($r->getProperty('radius') == 'true') {
                    $withRadius++;
                } else {
                    $withoutRadius++;
                }
            }
        }
        return [
            'Con_Radius' => $withRadius,
            'Sin_Radius' => $withoutRadius,
            'Total' => $withRadius + $withoutRadius
        ];
    }

    public function checkSpecificUserRadius($routerId, $targetIp = '10.2.1.200')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // --- 1. BUSCAR EN SESIONES ACTIVAS (Físicamente conectado ahora) ---
        $requestActive = new \PEAR2\Net\RouterOS\Request('/ppp/active/print');
        $requestActive->setQuery(\PEAR2\Net\RouterOS\Query::where('address', $targetIp));
        $resActive = $connection->sendSync($requestActive);

        $activeData = null;
        foreach ($resActive as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $activeData = [
                    'usuario'    => $r->getProperty('name'),
                    'uptime'     => $r->getProperty('uptime'),
                    'con_radius' => $r->getProperty('radius'), // bandera R
                    'caller_id'  => $r->getProperty('calling-station-id'), // MAC
                ];
                break;
            }
        }

        // --- 2. BUSCAR EN SECRETS (Base de datos local del MikroTik) ---
        // En Secrets, la IP se guarda en el campo 'remote-address'
        $requestSecret = new \PEAR2\Net\RouterOS\Request('/ppp/secret/print');
        $requestSecret->setQuery(\PEAR2\Net\RouterOS\Query::where('remote-address', $targetIp));
        $resSecret = $connection->sendSync($requestSecret);

        $secretData = null;
        foreach ($resSecret as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $secretData = [
                    'name'     => $r->getProperty('name'),
                    'service'  => $r->getProperty('service'),
                    'profile'  => $r->getProperty('profile'),
                    'last_out' => $r->getProperty('last-logged-out'),
                    'comment'  => $r->getProperty('comment') ?? 'Sin comentario'
                ];
                break;
            }
        }

        // --- 3. ANALIZAR ESTADO ---
        $mensaje = "";
        if ($activeData && $secretData) {
            $mensaje = "El usuario existe localmente y está CONECTADO.";
        } elseif (!$activeData && $secretData) {
            $mensaje = "El usuario existe localmente pero está DESCONECTADO (Offline).";
        } elseif ($activeData && !$secretData) {
            $mensaje = "El usuario está conectado por RADIUS pero NO existe en Secrets locales.";
        } else {
            $mensaje = "No existe rastro de esa IP en el router.";
        }

        return [
            'resultado' => $mensaje,
            'ip'        => $targetIp,
            'en_active' => $activeData,
            'en_secret' => $secretData
        ];
    }

    public function traceRouteToRadius($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/route/check');
        $request->setArgument('address', '192.168.105.108');

        $response = $connection->sendSync($request);
        return [
            'interface' => $response->getProperty('interface'),
            'gateway' => $response->getProperty('gateway'),
            'reachable' => $response->getProperty('reachable')
        ];
    }

    public function checkMangleOutputDetailed($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/mangle/print');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('chain', 'output'));

        $responses = $connection->sendSync($request);
        $rules = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $rules[] = [
                    'action' => $r->getProperty('action'),
                    'new-routing-mark' => $r->getProperty('new-routing-mark'),
                    'dst-address' => $r->getProperty('dst-address') ?? 'todas',
                    'comment' => $r->getProperty('comment') ?? ''
                ];
            }
        }
        return $rules;
    }

    public function seeActualRoute($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // En lugar de "check", vamos a "print" las rutas que coincidan con la red del servidor
        $request = new \PEAR2\Net\RouterOS\Request('/ip/route/print');
        // Buscamos rutas que contengan la red 192.168.105
        $responses = $connection->sendSync($request);

        $routes = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $dst = $r->getProperty('dst-address');
                if (strpos($dst, '192.168.105') !== false) {
                    $routes[] = [
                        'dest' => $dst,
                        'gateway' => $r->getProperty('gateway'),
                        'distance' => $r->getProperty('distance'),
                        'routing-table' => $r->getProperty('routing-table') ?? 'main',
                        'active' => $r->getProperty('active'),
                    ];
                }
            }
        }
        return $routes;
    }

    public function whereIsMyIp($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/address/print'));

        foreach ($responses as $r) {
            if (strpos($r->getProperty('address'), '192.168.105.1') !== false) {
                return "La IP 192.168.105.1 está en la interfaz: " . $r->getProperty('interface');
            }
        }
        return "IP 192.168.105.1 NO ENCONTRADA EN EL ROUTER";
    }

    public function checkPppoeProtocols($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Miramos la configuración de los servidores PPPoE
        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/interface/pppoe-server/server/print'));

        $servers = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $servers[] = [
                    'service-name' => $r->getProperty('service-name'),
                    'authentication' => $r->getProperty('authentication'), // Ej: pap,chap,mschap2
                ];
            }
        }
        return $servers;
    }

    public function verifyBridgeIp($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/address/print'));

        foreach ($responses as $r) {
            if (str_contains($r->getProperty('address'), '192.168.105.1')) {
                return [
                    'IP' => $r->getProperty('address'),
                    'Interfaz_Actual' => $r->getProperty('interface'),
                    'Estado' => ($r->getProperty('disabled') == 'false') ? 'Activa' : 'Desactivada'
                ];
            }
        }
        return "La IP 192.168.105.1 no existe en el router.";
    }

    public function checkPPPAuthMethods($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Revisamos el servidor PPPoE
        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/interface/pppoe-server/server/print'));

        foreach ($responses as $r) {
            return [
                'Metodos_Permitidos' => $r->getProperty('authentication') // Debe incluir pap, chap, mschap2
            ];
        }
    }

    public function clearRadiusIncoming($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Desactivamos el "Accept" de solicitudes entrantes para que no interfiera en la salida
        $request = new \PEAR2\Net\RouterOS\Request('/radius/incoming/set');
        $request->setArgument('accept', 'no');
        $connection->sendSync($request);

        return "Incoming RADIUS desactivado para limpieza.";
    }

    public function allowRadiusReturn($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);
        $serverIp = '192.168.105.108';

        // 1. REGLA EN FILTER INPUT: Aceptar el tráfico del servidor al MikroTik
        // Esto es vital para que el MikroTik escuche la respuesta del RADIUS
        $filterReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/filter/add');
        $filterReq->setArgument('chain', 'input');
        $filterReq->setArgument('src-address', $serverIp);
        $filterReq->setArgument('protocol', 'udp');
        $filterReq->setArgument('action', 'accept');
        $filterReq->setArgument('place-before', '0');
        $filterReq->setArgument('comment', 'ACEPTAR_RESPUESTA_RADIUS');
        $connection->sendSync($filterReq);

        // 2. REGLA EN NAT DST-NAT: Bypass de entrada
        // Asegura que ninguna regla de redirección toque la respuesta del RADIUS
        $natIn = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/add');
        $natIn->setArgument('chain', 'dstnat');
        $natIn->setArgument('src-address', $serverIp);
        $natIn->setArgument('protocol', 'udp');
        $natIn->setArgument('action', 'accept');
        $natIn->setArgument('place-before', '0');
        $natIn->setArgument('comment', 'BYPASS_NAT_ENTRADA_RADIUS');
        $connection->sendSync($natIn);

        return "Reglas de retorno creadas. El MikroTik ahora debería 'oír' al servidor.";
    }

    public function checkReturnTraffic($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. Verificamos la regla de Firewall que creamos para el retorno
        $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/filter/print');
        $request->setArgument('stats', '');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('comment', 'ACEPTAR_RESPUESTA_RADIUS'));

        $responses = $connection->sendSync($request);

        $stats = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $stats['firewall_rule'] = [
                    'packets' => $r->getProperty('packets'),
                    'bytes' => $r->getProperty('bytes')
                ];
            }
        }

        // 2. Verificamos el Secreto (Solo para estar seguros de qué tiene el Mikrotik)
        $radiusPrint = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/radius/print'));
        foreach ($radiusPrint as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $stats['radius_config'] = [
                    'secret' => $r->getProperty('secret'),
                    'address' => $r->getProperty('address')
                ];
            }
        }

        return $stats;
    }

    public function syncRadiusSettings($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $requestPrint = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $responses = $connection->sendSync($requestPrint);

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $id = $r->getProperty('.id');

                $requestSet = new \PEAR2\Net\RouterOS\Request('/radius/set');
                $requestSet->setArgument('numbers', $id);
                // 1. Forzamos el secreto una vez más por si acaso
                $requestSet->setArgument('secret', 'meganet123');
                // 2. Sincronizamos con el "no" de tu archivo clients.conf
                $requestSet->setArgument('require-message-auth', 'no');
                $connection->sendSync($requestSet);
            }
        }

        return "MikroTik sincronizado: Secret y Message-Auth configurados.";
    }


    public function forceRadiusMatch($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. Buscamos el ID del servidor RADIUS
        $requestPrint = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $responses = $connection->sendSync($requestPrint);

        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $id = $r->getProperty('.id');

                $requestSet = new \PEAR2\Net\RouterOS\Request('/radius/set');
                $requestSet->setArgument('numbers', $id);

                // CAMBIO CLAVE 1: Ponemos el src-address en 0.0.0.0 (Auto)
                $requestSet->setArgument('src-address', '0.0.0.0');

                // CAMBIO CLAVE 2: Re-escribimos el secret (Asegúrate que sea igual en Linux)
                $requestSet->setArgument('secret', 'meganet123');

                $connection->sendSync($requestSet);
            }
        }

        return "MikroTik actualizado a modo flexible. Re-escribe el secret en Linux ahora.";
    }


    public function checkProfileRadius($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $responses = $connection->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp/profile/print'));

        $profiles = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $profiles[] = [
                    'name' => $r->getProperty('name'),
                    'use-radius' => $r->getProperty('use-radius') ?? 'default (yes)'
                ];
            }
        }
        return $profiles;
    }

    public function checkProtectionStats($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. NAT Bypass de Salida
        $natReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/nat/print');
        $natReq->setArgument('stats', '');
        $natReq->setQuery(\PEAR2\Net\RouterOS\Query::where('comment', 'BYPASS_SALIDA_RADIUS'));
        $natResponses = $connection->sendSync($natReq);

        $outPackets = 0;
        foreach ($natResponses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $outPackets = $r->getProperty('packets');
            }
        }

        // 2. Filter Aceptar Respuesta
        $filterReq = new \PEAR2\Net\RouterOS\Request('/ip/firewall/filter/print');
        $filterReq->setArgument('stats', '');
        $filterReq->setQuery(\PEAR2\Net\RouterOS\Query::where('comment', 'ACEPTAR_RESPUESTA_RADIUS'));
        $filterResponses = $connection->sendSync($filterReq);

        $inPackets = 0;
        foreach ($filterResponses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $inPackets = $r->getProperty('packets');
            }
        }

        return [
            'Salida_Hacia_Server' => $outPackets,
            'Respuesta_Desde_Server' => $inPackets
        ];
    }

    public function readMikrotikLogs($routerId = 2)
    {
        $page = 0;
        $perPage = 100;
        $offset = $page * $perPage;

        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // OPTIMIZACIÓN: Pedimos únicamente la propiedad 'message' al MikroTik
        $requestLog = new \PEAR2\Net\RouterOS\Request('/log/print');
        $requestLog->setArgument('.proplist', 'message');

        $responses = $connection->sendSync($requestLog);

        $logs = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                // Guardamos solo el string del mensaje directamente
                $logs[] = $r->getProperty('message');
            }
        }

        // Invertir para ver los más recientes arriba
        $reversedLogs = array_reverse($logs);

        // Cortar los 100 mensajes según la página
        $paginatedMessages = array_slice($reversedLogs, $offset, $perPage);

        return [
            'messages'     => $paginatedMessages, // Ahora es un array simple de strings
            'current_page' => $page,
            'total_count'  => count($logs)
        ];
    }

    public function lockRadiusIp($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $requestPrint = new \PEAR2\Net\RouterOS\Request('/radius/print');
        $id = $connection->sendSync($requestPrint)->getProperty('.id');

        $requestSet = new \PEAR2\Net\RouterOS\Request('/radius/set');
        $requestSet->setArgument('numbers', $id);
        // FORZAMOS LA IP PRIVADA
        $requestSet->setArgument('src-address', '192.168.105.1');
        $requestSet->setArgument('secret', 'meganet123');
        $connection->sendSync($requestSet);

        return "IP de origen fijada en 192.168.105.1. Ahora limpiaremos el servidor.";
    }

    public function unlockRadiusIp($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $requestSet = new \PEAR2\Net\RouterOS\Request('/radius/set');
        $requestSet->setArgument('numbers', '0');
        $requestSet->setArgument('src-address', '0.0.0.0'); // Volver a automático
        $connection->sendSync($requestSet);

        return "IP de origen liberada (0.0.0.0). Revisa el tcpdump ahora.";
    }

    public function kickUserByIp($routerId, $targetIp = '10.2.1.200')
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. Buscamos el .id de la sesión activa para esa IP
        $request = new \PEAR2\Net\RouterOS\Request('/ppp/active/print');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('address', $targetIp));
        $responses = $connection->sendSync($request);

        $id = null;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $id = $r->getProperty('.id');
                break;
            }
        }

        // 2. Si encontramos el ID, lo eliminamos
        if ($id) {
            $removeRequest = new \PEAR2\Net\RouterOS\Request('/ppp/active/remove');
            $removeRequest->setArgument('numbers', $id);
            $connection->sendSync($removeRequest);

            return "Usuario con IP $targetIp desconectado. Espera 5 segundos a que reconecte.";
        }

        return "No se encontró una sesión activa para la IP $targetIp.";
    }

    public function checkPppAaaStatus($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/ppp/aaa/print');
        $response = $connection->sendSync($request);

        foreach ($response as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                return [
                    'use-radius'     => $r->getProperty('use-radius'),
                    'accounting'     => $r->getProperty('accounting'), // Debe decir "true"
                    'interim-update' => $r->getProperty('interim-update')
                ];
            }
        }
    }

    public function forceRadiusOnAllProfiles($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // 1. Obtenemos todos los perfiles del MikroTik
        $request = new \PEAR2\Net\RouterOS\Request('/ppp/profile/print');
        $responses = $connection->sendSync($request);

        $actualizados = 0;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $id = $r->getProperty('.id');
                $name = $r->getProperty('name');

                // 2. Le decimos a cada perfil que USE RADIUS obligatoriamente
                $update = new \PEAR2\Net\RouterOS\Request('/ppp/profile/set');
                $update->setArgument('numbers', $id);
                $update->setArgument('use-radius', 'yes'); // <--- ESTO ES LO QUE TE FALTA
                $connection->sendSync($update);
                $actualizados++;
            }
        }

        return "Se actualizaron $actualizados perfiles. Ahora el MikroTik reportará el consumo de los Secrets locales.";
    }

    public function getMikrotikScripts($routerId = 2)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        if (!$connection) {
            return "Error: No se pudo conectar al router.";
        }

        // 1. Creamos la petición a system script
        $request = new \PEAR2\Net\RouterOS\Request('/system/script/print');

        // 2. Definimos qué propiedades queremos traer para no sobrecargar la memoria
        // .id = ID interno de MikroTik
        // name = Nombre del script
        // owner = Usuario que lo creó
        // run-count = Cuántas veces se ha ejecutado
        // source = El código contenido en el script
        $request->setArgument('.proplist', '.id,name,owner,run-count,last-started,source');

        $responses = $connection->sendSync($request);

        $scripts = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $scripts[] = [
                    'id'           => $r->getProperty('.id'),
                    'name'         => $r->getProperty('name'),
                    'owner'        => $r->getProperty('owner'),
                    'ejecuciones'  => $r->getProperty('run-count'),
                    'ultima_vez'   => $r->getProperty('last-started'),
                    'codigo'       => $r->getProperty('source'),
                ];
            }
        }

        return $scripts;
    }

    public function deleteScriptsByPrefix($routerId = 2)
    {
        $prefix = 'rectify_clients_script_secret';

        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        if (!$connection) {
            return "Error de conexión.";
        }

        // 1. Buscamos todos los scripts (solo pedimos id y nombre para ir rápido)
        $request = new \PEAR2\Net\RouterOS\Request('/system/script/print');
        $request->setArgument('.proplist', '.id,name');
        $responses = $connection->sendSync($request);

        $idsToDelete = [];
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $name = $r->getProperty('name');

                // 2. Verificamos si el nombre empieza con el prefijo
                if (strpos($name, $prefix) === 0) {
                    $idsToDelete[] = $r->getProperty('.id');
                }
            }
        }

        // 3. Si encontramos coincidencias, las eliminamos
        if (!empty($idsToDelete)) {
            $removeRequest = new \PEAR2\Net\RouterOS\Request('/system/script/remove');
            // MikroTik permite pasar varios IDs separados por coma
            $removeRequest->setArgument('numbers', implode(',', $idsToDelete));
            $connection->sendSync($removeRequest);

            return "Éxito: Se eliminaron " . count($idsToDelete) . " scripts que empezaban con '$prefix'.";
        }

        return "No se encontró ningún script con ese prefijo.";
    }

    public function debugTraffic($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        // Pedimos las conexiones activas pero SIN filtro proplist
        // para que el MikroTik nos devuelva TODO lo que sabe de la sesión
        $request = new \PEAR2\Net\RouterOS\Request('/ppp/active/print');
        $responses = $connection->sendSync($request);

        $samples = [];
        $count = 0;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                // Guardamos los datos puros de los primeros 3 usuarios
                $samples[] = iterator_to_array($r);
                $count++;
                if ($count >= 3) break;
            }
        }

        return [
            'total_active' => $count,
            'raw_samples' => $samples
        ];
    }

    public function testInterfaceStats($routerId)
    {
        $router = Router::find($routerId);
        $connection = $this->getConnectionByRouter($router);

        $request = new \PEAR2\Net\RouterOS\Request('/interface/print');
        $request->setArgument('.proplist', 'name,rx-byte,tx-byte');
        $request->setQuery(\PEAR2\Net\RouterOS\Query::where('type', 'pppoe-in'));

        $responses = $connection->sendSync($request);

        $samples = [];
        $i = 0;
        foreach ($responses as $r) {
            if ($r->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                $samples[] = [
                    'interfaz' => $r->getProperty('name'),
                    'subida_bytes' => $r->getProperty('rx-byte'),
                    'bajada_bytes' => $r->getProperty('tx-byte'),
                ];
                if (++$i >= 5) break; // Ver solo los primeros 5
            }
        }
        return $samples;
    }


    public function script(Request $request)
    {
        $date = [
            'laravel_now' => now()->toDateTimeString(),
            'system_time' => date('Y-m-d H:i:s'),
            'timezone' => config('app.timezone')
        ];
        dd($date);


        //         $router = Router::find(2);
        //         $client = $this->getConnectionByRouter($router);
        //         $id = $this->getIdByIpSecrets($client,'/ppp/secret/','10.10.2.198');
        //        $password = $this->getPasswordByIp($client,'/ppp/secret/','10.10.2.198');

        // dd($id,$password);
        DB::unprepared('
            DROP TRIGGER IF EXISTS update_column_is_first_payment;
            CREATE TRIGGER update_column_is_first_payment
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                IF (SELECT COUNT(*) FROM payments WHERE paymentable_id = NEW.paymentable_id) = 0 THEN
                    SET NEW.is_first_payment = 1;
                END IF;
            END
        ');


        // if ($request->querystring) {
        //     return DB::select($request->querystring);
        // }

        // if ($request->periodo_gracia) {
        //     $client = Client::find($request->periodo_gracia);
        //     $clientRepository = new ClientRepository();
        //     $clientRepository->addPeriodoGracia($client);
        //     return;
        // }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function getInformationDevelopment(InformationService $service)
    {
        $data = $service->getInformation();

        echo "<h1>Diagnóstico completo de clientes</h1>";

        // 🔹 Menú de enlaces al inicio
        echo "<h2>Menú de secciones</h2>";
        echo "<ul>";
        foreach ($data as $label => $clientes) {
            // Generamos un ID seguro para la sección usando md5
            $anchor = 'section_' . md5($label);
            echo "<li><a href='#{$anchor}'>{$label}</a></li>";
        }
        echo "</ul>";

        echo "<hr>";

        // 🔹 Secciones de datos
        foreach ($data as $label => $clientes) {
            $anchor = 'section_' . md5($label);
            echo "<h2 id='{$anchor}'>{$label}</h2>";

            $isNotEmpty = is_array($clientes) ? count($clientes) > 0 : $clientes->isNotEmpty();

            if ($isNotEmpty) {
                echo "<table border='1' cellpadding='5' cellspacing='0'>";
                echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Fecha Corte</th><th>Fecha Pago</th><th>Balance</th><th>Tipo Billing</th></tr>";

                foreach ($clientes as $c) {
                    // Para arrays normales
                    if (is_array($c)) {
                        $id = $c['client_id'] ?? '';
                        $name = $c['name'] ?? '';
                        $email = $c['email'] ?? '';
                        $balance = '-';
                        $billing = '-';
                        $fecha_corte = '-';
                        $fecha_pago = '-';
                    } else {
                        // Para colecciones de Eloquent
                        $id = $c->id;
                        $name = $c->client_main_information->name ?? '';
                        $email = $c->client_main_information->email ?? '';
                        $billing = $c->client_main_information->type_of_billing_id ?? '';
                        $balance = $c->balance->amount ?? '';
                        $fecha_corte = $c->fecha_corte;
                        $fecha_pago = $c->fecha_pago;
                    }

                    $enlaceId = "<a href='/cliente/editar/{$id}' target='_blank'>{$id}</a>";

                    echo "<tr>
                    <td>{$enlaceId}</td>
                    <td>{$name}</td>
                    <td>{$email}</td>
                    <td>{$fecha_corte}</td>
                    <td>{$fecha_pago}</td>
                    <td>{$balance}</td>
                    <td>{$billing}</td>
                  </tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No hay clientes para este caso.</p>";
            }

            echo "<p><a href='#top'>Volver al menú</a></p>";
        }
    }


    public function getDataToMikrotikByClientId($id)
    {
        $data = [
            'client_id' => $id,
            'client_ips' => []
        ];

        $router = Router::find(2);
        $clientConnection = $this->getConnectionByRouter($router);
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clientInternetServices = $clientInternetServiceRepository->getServiceFilterByClientId($id);

        foreach ($clientInternetServices as $service) {
            $ipInMegaisp = $service->network_ip_used_by->ip;
            if ($ipInMegaisp) {
                // Creamos un nuevo array para esta IP
                $ipData = [
                    'ip_inMegaIsp' => $ipInMegaisp,
                    'ip_inMikrotik' => null,
                    'password' => null,
                    'ip_inAddress_list' => null,
                    'ip_pool' => null
                ];

                $idByIpAndName = $this->getIdByIpAndNameSecretInMikrotik($service);
                if ($idByIpAndName) {
                    $ipData['ip_inMikrotik'] = $this->getIpByIdSecrets($clientConnection, '/ppp/secret/', $idByIpAndName);
                    $ipData['password'] = $this->getPasswordById($clientConnection, '/ppp/secret/', $idByIpAndName);
                }

                $constIpFirewallAddressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
                $idByIpInAddressList = $this->getIdByIp($clientConnection, $constIpFirewallAddressList, $ipInMegaisp);
                if ($idByIpInAddressList) {
                    $ipData['ip_inAddress_list'] = $this->getIpById($clientConnection, $constIpFirewallAddressList, $idByIpInAddressList);
                }

                $idByIpInPool = $this->getIdByIp($clientConnection, '/ip/pool/', $ipInMegaisp);
                if ($idByIpInPool) {
                    $ipData['ip_pool'] = $this->getIpById($clientConnection, '/ip/pool/', $idByIpInPool);
                }

                // Añadimos el array completo al resultado
                $data['client_ips'][] = $ipData;
            }
        }

        return $data;
    }

    public function getDataToMikrotikByIp(Request $request)
    {
        $ip = $request->ip;
        if (!$ip) {
            return [
                'client_id' => null,
                'client_ips' => []
            ];
        }
        $data = [
            'client_ips' => []
        ];

        $router = Router::find(2);
        $clientConnection = $this->getConnectionByRouter($router);
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByIp($ip);
        $client = null;
        if ($networkIp) {
            $client = $networkIp->client;
        }

        if ($client) {
            $data['client_id'] = $client->id;
            $data['client_name'] = $client->client_main_information->name;
        }

        // Creamos un nuevo array para esta IP
        $ipData = [
            'ip_inMikrotik' => null,
            'password' => null,
            'ip_inAddress_list' => null,
            'ip_pool' => null
        ];

        $idByIpAndName = $this->getIdByIpSecrets($clientConnection, '/ppp/secret/', $ip);
        if ($idByIpAndName) {
            $ipData['ip_inMikrotik'] = $this->getIpByIdSecrets($clientConnection, '/ppp/secret/', $idByIpAndName);
            $ipData['password'] = $this->getPasswordById($clientConnection, '/ppp/secret/', $idByIpAndName);
        }

        $constIpFirewallAddressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
        $idByIpInAddressList = $this->getIdByIp($clientConnection, $constIpFirewallAddressList, $ip);
        if ($idByIpInAddressList) {
            $ipData['ip_inAddress_list'] = $this->getIpById($clientConnection, $constIpFirewallAddressList, $idByIpInAddressList);
        }
        // Añadimos el array completo al resultado
        $data['client_ips'][] = $ipData;


        return $data;
    }


    public function liberaIp($id)
    {
        try {
            DB::beginTransaction();
            $networkIp = NetworkIp::find($id);
            $networkIp->update(['used' => ComunConstantsController::IS_NUMERICAL_FALSE, 'used_by' => '--', 'client_id' => null, 'host_category' => 'Ninguno']);
            DB::commit();
            Log::info('Se ha liberado la ip ' . $networkIp->ip);
            return $networkIp;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function checkIndex()
    {
    }
}
