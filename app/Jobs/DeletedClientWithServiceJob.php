<?php

namespace App\Jobs;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\RouterRepository;
use App\Models\ClientInternetService;
use App\Models\MikrotikTariffMainTail;
use App\Models\Router;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use App\Jobs\DeletedClientInRouterJob;


class DeletedClientWithServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientInternetService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientInternetService)
    {
        $this->clientInternetService = $clientInternetService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($this->clientInternetService->router_id);
        $device_ip = $router->ip_host;
        $tariff_target = $this->clientInternetService->mikrotik_tariff_target_tail()->first();

        if ($router->IsMikrotik()) {
            $mikrotik = $router->mikrotik()->first();
            $connection = $this->initConnection($mikrotik, $device_ip);
            if ($connection) {
                try {
                    Bus::chain([
                        new DeletedClientInRouterJob($this->clientInternetService),
                    ])->dispatch();
                } catch (\Exception $exception) {
                    Log::info($exception);
                }

                $serviceInAddressList = $this->clientInternetService->service_in_address_list()->first();
                if ($serviceInAddressList) {
                    $serviceInAddressList->delete();
                }

                if ($tariff_target) {
                    $json = json_decode($tariff_target->json);
                    $name = collect($json)->get('name');
                    $parentName = collect($json)->get('parent');
                    $ip_client = collect($json)->get('target');

                    $this->deleteServiceClient($connection, $name, $tariff_target);
                    $this->deleteClientInParent($connection, $parentName, $ip_client, $tariff_target);
                }
            }
        }
    }

    public function deleteClientInParent($connection, $parentName, $ip_client, $tariff_target)
    {
        $oldTarget = $this->getTargetByName($connection, '/queue/simple/', $parentName);
        if ($this->isLastOne($oldTarget)) {
            $this->deleteParent($connection, $parentName);
            $padreEliminar = MikrotikTariffMainTail::find($tariff_target->mikrotik_tariff_main_tail_id)->first();
            $padreEliminar->delete();
        } else {
            $this->setvalueArrayById(
                $connection,
                '/queue/simple/',
                $this->getIdByName($connection, '/queue/simple/', $parentName),
                (
                    [
                        'target' => Str::remove($ip_client . '/32', $oldTarget)
                    ])
            );

            $parent = MikrotikTariffMainTail::find($tariff_target->mikrotik_tariff_main_tail_id);
            $parentJson = $parent->json;
            $arrayJson = collect($parentJson)->toArray();
            Arr::set($arrayJson, 'target', Str::remove($ip_client . '/32', $oldTarget));
            $newJson = json_encode($arrayJson);
            $parent->update(['json' => $newJson]);
        }
    }

    public function deleteServiceClient($connection, $name, $tariff_target)
    {
        $this->removeById(
            $connection,
            '/queue/simple/',
            $this->getIdByName($connection, '/queue/simple/', $name)
        );
        //  quitar de la base de datos
        $tariff_target->delete();
    }

    public function isLastOne($oldTarget)
    {
        return count(explode(",", $oldTarget)) <= 1;
    }

    public function deleteParent($connection, $parentName)
    {
        $this->removeById(
            $connection,
            '/queue/simple/',
            $this->getIdByName($connection, '/queue/simple/', $parentName)
        );
    }
}
