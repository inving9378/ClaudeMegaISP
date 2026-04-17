<?php

namespace App\Http\Controllers\Module\Router;

use App\Http\Controllers\Controller;
use App\Jobs\Mikrotik\MicrotikDeleteRulesJob;
use App\Jobs\Mikrotik\MikrotikRulesJob;
use App\Models\Router;
use App\Models\MikrotikItemToExcecuteAction;
use App\Http\Requests\module\router\MikrotikUpdateRequest;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use App\Http\Traits\RouterConnection;
use App\Jobs\RectifyClientsInRouterJob;
use Illuminate\Support\Facades\DB;


class MikrotikController extends Controller
{
    use RouterConnection;

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(MikrotikUpdateRequest $request, $idRouter, MikrotikService $mikrotikService)
    {
        $mikrotikService->resetConnection();

        $router = Router::find($idRouter);
        $action = $router->mikrotik ? 'update' : 'create';

        MikrotikItemToExcecuteAction::create([
            'Model' => 'MikrotikArray',
            'place' => $idRouter,
            'flag' => 'idRouter',
            'value' => collect($request)->toJson(),
            'action' => 'update',
        ]);
        return $this->saveSingleRelationIfExist('App\Models\Router', $router, $request, $action);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function getMikrotikStatus($id)
    {
        $router = Router::find($id);
        $mikrotik = $router->mikrotik()->first();
        if ($mikrotik) {
            $device_ip = $router->ip_host;
            $connected = $this->initConnection($mikrotik, $device_ip);
            $status = 'API (con errores)';
            $versionMikrotik = [];
            if ($connected != null) {
                $versionMikrotik = $this->getVersion($connected);
                $status = isset($versionMikrotik['version']) ? 'API OK' : 'API (con errores)';
                $router->mikrotik()->update([
                    'status' => (($versionMikrotik) ? true : false),
                    'plataform' => $versionMikrotik['platform'],
                    'board_name' => $versionMikrotik['board-name'],
                    'ros_version' => $versionMikrotik['version'],
                    'cpu_load' => $versionMikrotik['cpu-load'],
                    'ipv6' => '-',
                    'last_status' => $versionMikrotik['uptime'],
                ]);
                $pingResult = $this->ping($connected, $router->mikrotikconfig()->first()->meganet_config_ip_address);
                $versionMikrotik['ping'] = collect(collect($pingResult)->first())['time'] ?? '';
            }

            $router->update([
                'status' => $status
            ]);


            return $versionMikrotik;
        }
        return [];
    }

    public function getMikrotikRemoveRules($id)
    {
        $router = Router::find($id);
        $mikrotik = $router->mikrotik;

        if ($mikrotik) {
            MicrotikDeleteRulesJob::dispatchAfterResponse($mikrotik);
            $mikrotik->rule_address_list_mobility_client = false;
            $mikrotik->bloking_rules = false;
            $mikrotik->update();

            $device_ip = $router->ip_host;
            $connected = $this->initConnection($mikrotik, $device_ip);
            return $this->getVersion($connected);
        }
    }

    public function getMikrotikCreateRules($id)
    {
        $router = Router::find($id);
        $mikrotik = $router->mikrotik;

        if ($mikrotik) {
            MikrotikRulesJob::dispatchAfterResponse($mikrotik);
            $device_ip = $router->ip_host;
            $connected = $this->initConnection($mikrotik, $device_ip);
            return $this->getVersion($connected);
        }
    }

    public function clearMikrotikTails()
    {
        DB::delete("delete from mikrotik_client_ppoes");
        DB::delete("delete from mikrotik_tariff_main_tails");
        DB::delete("delete from mikrotik_tariff_target_tails");
        DB::delete("delete from service_in_address_lists");
        return 'ok';
    }

    public function cloneClientToMikrotik($id)
    {
        $router = Router::find($id);
        if ($router) {
            RectifyClientsInRouterJob::dispatchAfterResponse($router);
        }
    }
}
