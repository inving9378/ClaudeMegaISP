<?php

namespace App\Http\Controllers\Module\Router;

use App\Models\Mikrotik;
use App\Models\ClientInternetService;
use App\Models\Router;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\router\RouterDatatableHelper;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Requests\module\router\RouterCreateRequest;
use App\Http\Requests\module\router\RouterUpdateRequest;
use App\Http\Traits\NotificationTrait;
use App\Http\Traits\RouterConnection;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\MikrotikClientHostpotUser;
use App\Models\MikrotikClientPpoe;
use App\Models\NetworkIp;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PEAR2\Net\RouterOS\Request as RouterOSRequest;
use PEAR2\Net\RouterOS\Response;

class RouterController extends Controller
{
    use RouterConnection, NotificationTrait;

    private $helper;
    protected $networkIpRepository;
    public function __construct(RouterDatatableHelper $helper, NetworkIpRepository $networkIpRepository)
    {
        $model = 'Router';
        $this->data['url'] = 'meganet.module.' . Str::lower($model);
        $this->data['module'] = $model;
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'router';
        $this->helper = $helper;
        $this->networkIpRepository = $networkIpRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function success($id)
    {
        return redirect('/red/router/editar/' . $id);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(RouterCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Router $router)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(RouterUpdateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $authorizationAccountingOld = $model->authorization_accounting;
        $this->changeClientAuthorizationAccountingWithServiceIfIsDiffernet($authorizationAccountingOld, $request->authorization_accounting, $model);

        if ($request->authorization_accounting == Router::RADIUS_USER) {
            $this->creoOActualizoElRadius($model);
        }

        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model->update($input);
    }

    public function changeClientAuthorizationAccountingWithServiceIfIsDiffernet($authorizationAccountingOld, $authorizationAccountingNew, $model)
    {
        if ($authorizationAccountingOld != $authorizationAccountingNew) {
            $this->verificaQuienEsElviejoYPasaEsosDatosAlNuevo($authorizationAccountingOld, $authorizationAccountingNew, $model);
        }
    }

    public function verificaQuienEsElviejoYPasaEsosDatosAlNuevo($authorizationAccountingOld, $authorizationAccountingNew, $model)
    {
        $mikrotikId = Mikrotik::where('router_id', $model->id)->first()->id;
        if ($authorizationAccountingOld == Router::HOSTPOT_USER) {
            $clients = MikrotikClientHostpotUser::where('mikrotik_id', $mikrotikId)->get();
            if ($clients->count() > 0) {
                $this->obtengoLosClientesDeHospotYLosPasoAPpoeSecrets($clients, $model);
            }
        } else if ($authorizationAccountingOld == Router::PPPOE_USER) {
            $clients = MikrotikClientPpoe::where('mikrotik_id', $mikrotikId)->get();
            if ($clients->count() > 0) {
                $this->obtengoLosClientesDePppoeYLosPasoAHostpot($clients, $model);
            }
        } else if ($authorizationAccountingOld == Router::RADIUS_USER && $authorizationAccountingNew == Router::HOSTPOT_USER) {
            $clients = MikrotikClientPpoe::where('mikrotik_id', $mikrotikId)->get();
            if ($clients->count() > 0) {
                $this->obtengoLosClientesDePppoeYLosPasoAHostpot($clients, $model);
            }
        }
    }

    public function obtengoLosClientesDeHospotYLosPasoAPpoeSecrets($clients, $model)
    {
        foreach ($clients as $client) {
            $newPP = new MikrotikClientPpoe();
            $newPP->mikrotik_id = $client->mikrotik_id;
            $newPP->client_id = $client->client_id;
            $newPP->save();
            $this->eliminoElClientDelHostpoEnElMikrotik($client->client_id, $model);
            $this->creoElClienteEnPPOESecret($client->client_id);
            $client->delete();
        }
    }

    public function eliminoElClientDelHostpoEnElMikrotik($clientId, $model)
    {
        $clientUser = ClientUser::where("client_id", $clientId)->first();
        $nameClient = $clientUser->user;
        $router = Router::find($model->id);
        $device_ip = $router->ip_host;
        $mikrotik = $router->mikrotik()->first();
        $connection = $this->initConnection($mikrotik, $device_ip);
        $this->deleteClienteHostpot($connection, $nameClient);
    }

    public function creoElClienteEnPPOESecret($clientId)
    {
        $client = Client::find($clientId);
        $clientServices = ClientInternetService::where('client_id', $client->id)->get();
        foreach ($clientServices as $clientService) {
            $router = Router::find($clientService->router_id);
            $device_ip = $router->ip_host;
            $mikrotik = $router->mikrotik()->first();
            $this->createPPPoEUser($client, $clientService, $router, $mikrotik, $device_ip);
        }
    }

    public function obtengoLosClientesDePppoeYLosPasoAHostpot($clients, $model)
    {
        foreach ($clients as $client) {
            $newPP = new MikrotikClientHostpotUser();
            $newPP->mikrotik_id = $client->mikrotik_id;
            $newPP->client_id = $client->client_id;
            $newPP->save();
            $this->eliminoElClientDelPppoeEnElMikrotik($client->client_id, $model);
            $this->creoElClienteEnHostpot($client->client_id);
            $client->delete();
        }
    }

    public function eliminoElClientDelPppoeEnElMikrotik($clientId, $model)
    {
        $clientUser = ClientUser::where("client_id", $clientId)->first();
        $nameClient = $clientUser->user;
        $router = Router::find($model->id);
        $device_ip = $router->ip_host;
        $mikrotik = $router->mikrotik()->first();
        $connection = $this->initConnection($mikrotik, $device_ip);
        $this->deleteClientePpoe($connection, $nameClient);
    }

    public function creoElClienteEnHostpot($clientId)
    {
        $client = Client::find($clientId);
        $clientServices = ClientInternetService::where('client_id', $client->id)->get();
        foreach ($clientServices as $clientService) {
            $router = Router::find($clientService->router_id);
            $device_ip = $router->ip_host;
            $mikrotik = $router->mikrotik()->first();
            $this->createHostpotUser($client, $clientService, $router, $mikrotik, $device_ip);
        }
    }


    public function createPPPoEUser($client, $clientService, $router, $mikrotik, $device_ip)
    {
        $type = class_basename(get_class($clientService));
        $clientIp = $this->networkIpRepository->getIpFilterUsedByPlan($clientService->id, $type);
        $password = $clientService->password;
        $connection = $this->initConnection($mikrotik, $device_ip);
        if ($connection) {
            $this->addItem(
                $connection,
                '/ppp secret ',
                ([
                    'name' => $client->clientGetUser(),
                    'password' => $password,
                    'service' => 'any',
                    'profile' => 'default',
                    'remote-address' => $clientIp,
                    'disabled' => 'no',
                    'comment' => 'MEGANET_' . $clientService->id
                ])
            );
            return 'Usuario creado' . $this->getIdByName($connection, '/ppp/secret/', $client->clientGetUser());
        } else {
            dd('no conectado');
        }
    }

    public function createHostpotUser($client, $clientService, $router, $mikrotik, $device_ip)
    {
        $type = class_basename(get_class($clientService));
        $clientIp = $this->networkIpRepository->getIpFilterUsedByPlan($clientService->id, $type);
        $connection = $this->initConnection($mikrotik, $device_ip);
        $password = $clientService->password;
        if ($connection) {
            $this->addItem(
                $connection,
                '/ip hotspot user ',
                ([
                    'name' => $client->clientGetUser(),
                    'password' => $password,
                    'disabled' => 'no',
                    'comment' => 'MEGANET_' . $clientService->id,
                    'address' => $clientIp,
                    'server' => 'all',
                    'profile' => 'default'
                ])
            );
            return 'Usuario creado' . $this->getIdByName($connection, '/ip/hotspot/user/', $client->clientGetUser());
        } else {
            dd('no conectado');
        }
    }

    public function addItem($client, $command, $arrayArgumentValue)
    {
        $addRequest = new RouterOSRequest($command . 'add');
        foreach ($arrayArgumentValue as $names => $value) {
            $addRequest->setArgument($names, $value);
        }

        $response = $client->sendSync($addRequest);
        $responseType = $response->getType();

        if ($responseType === Response::TYPE_FINAL) {
            return true;
        } elseif ($responseType === Response::TYPE_ERROR) {
            $errorMessage = $response->getProperty('message');
            throw new \Exception("Error al ejecutar el comando en el enrutador MikroTik: $errorMessage");
        } else {
            throw new \Exception("Respuesta inesperada del enrutador MikroTik: $responseType");
        }
    }

    public function creoOActualizoElRadius($model)
    {

        $clients = MikrotikClientHostpotUser::where('mikrotik_id', $model->id)->get();
        $this->obtengoLosClientesDeHospotYLosPasoAPpoeSecrets($clients, $model);

        $router = Router::find($model->id);
        $device_ip = $router->ip_host;
        $mikrotik = $router->mikrotik()->first();
        $connection = $this->initConnection($mikrotik, $device_ip);
        $id =  $this->getIdByComment(
            $connection,
            '/radius ',
            'MgNet_Radius_Service' . $router->id
        );

        if ($id) {
            $this->updateServerRadius($connection, $router);
        } else {
            $this->addServerRadius($connection, $router);
        }
    }






    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $mikrotik = Mikrotik::where('router_id', '=', $id)->first();
        if ($mikrotik) {
            $mikrotik->delete();
        }
        $clientInternetService = ClientInternetService::where('router_id', '=', $id)->first();
        if ($clientInternetService) {
            $clientInternetService->delete();
        }
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function readNotification($id)
    {
        $user = auth()->user();
        $notification = $user->notifications->firstWhere('id', $id);
        if ($notification) {
            $notification->markAsRead();
            $data = $this->getNotificationAttributes($notification);
            if ($data && $data['router_id']) {
                $router = Router::find($data['router_id']);
                if ($router) {
                    return $this->success($data['router_id']);
                }
            }
        }
        return redirect()->back()->with('error', 'El Router al que hace referencia esta notificación ya no existe');
    }
}
