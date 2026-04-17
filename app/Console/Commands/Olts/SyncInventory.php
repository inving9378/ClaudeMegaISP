<?php

namespace App\Console\Commands\Olts;

use App\Models\GeneralNotification;
use App\Models\Olt;
use App\Models\OltBilling;
use App\Models\User;
use App\Notifications\StandardNotification;
use App\Services\OLTsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

class SyncInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smartolt:sync-inventory 
                        {--only= : Especifica qué parte sincronizar (olts|cards|vlans|zones|profiles|type-onus|onus|odbs|pon-ports|uplink-ports|billings)} 
                        {olt? : ID OLT}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para sincronizar las tarjetas, puertos PON, perfiles de velocidad, ...';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $only = $this->option('only') ?? null;
        $olt = $this->argument('olt') ?? null;
        $validOptions = ['olts', 'cards', 'vlans', 'zones', 'profiles', 'type-onus', 'onus', 'odbs', 'pon-ports', 'uplink-ports', 'billings'];
        if ($only && !in_array($only, $validOptions)) {
            $this->error("La opción '{$only}' no es válida. Usa: " . implode(', ', $validOptions));
            return 1;
        }
        $service =  new OLTsService();
        if ($only) {
            $this->info("Iniciando sincronización parcial: {$only}");
        } else {
            $this->info("Iniciando sincronización completa del inventario...");
        }

        if (!$only || $only === 'olts') {
            $this->syncOlts($service);
        }

        if (!$only || $only === 'vlans') {
            $this->syncVlans($olt);
        }

        if (!$only || $only === 'cards') {
            $this->syncCards($olt);
        }

        if (!$only || $only === 'zones') {
            $this->syncZones($service);
        }

        if (!$only || $only === 'profiles') {
            $this->syncProfiles($service);
        }

        if (!$only || $only === 'type-onus') {
            $this->syncTypeOnus($service);
        }

        if (!$only || $only === 'odbs') {
            $this->syncOdbs($service);
        }

        if (!$only || $only === 'onus') {
            $this->syncOnus();
        }

        if (!$only || $only === 'pon-ports') {
            $this->syncPonPorts($olt);
        }

        if (!$only || $only === 'uplink-ports') {
            $this->syncUplinkPorts($olt);
        }

        if (!$only || $only === 'billings') {
            $this->syncBillings($service);
        }
        $this->info('Sincronización de inventario completada correctamente');
    }

    public function syncOlts($service)
    {
        $this->info('Iniciando sincronización de olts');
        $response = $service->syncOlts();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
        } else {
            $this->error($response['message']);
        }
    }

    public function syncBillings($service)
    {
        $this->info('Iniciando sincronización de facturación');
        $response = $service->syncBillings();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
        } else {
            $this->error($response['message']);
        }
        $billings = OltBilling::all();
        foreach ($billings as $b) {
            if ($b->end_subscription_days <= 10) {
                $msg = '';
                if ($b->end_subscription_days === 0) {
                    $msg = sprintf('Hoy se vence la subscripción de la OLT %d-%s', $b->id, $b->name);
                } else if ($b->end_subscription_days < 0) {
                    $msg = sprintf('Subscripción de la OLT %d-%s vencida', $b->id, $b->name);
                } else {
                    $msg = sprintf('Vencimiento de subscripción de la OLT %d-%s en %d días', $b->id, $b->name, $b->end_subscription_days);
                }
                $notification = new GeneralNotification();
                $notification->priority = 'Alta';
                $notification->title = $msg;
                $notification->code = 'end-olt-subscription';
                $notification->save();
                $users = User::admin()->get();
                Notification::send($users, new StandardNotification($notification));
            }
        }
    }

    public function syncVlans($olt = null)
    {
        $this->info('Iniciando sincronización de vlans');
        $olts = $olt ? [Olt::find($olt)] : Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando olt ' . $olt->name);
            $olt->syncVLANs();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
        $this->info('Sincronización de vlans completada correctamente');
    }

    public function syncCards($olt = null)
    {
        $this->info('Iniciando sincronización de tarjetas');
        $olts = $olt ? [Olt::find($olt)] : Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando olt ' . $olt->name);
            $olt->syncCards();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
        $this->info('Sincronización de tarjetas completada correctamente');
    }

    public function syncOnus()
    {
        $this->info('Iniciando sincronización de onus');
        $olts = Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando onus ' . $olt->name);
            $olt->syncOnus();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
        try {
            Artisan::call('smartolt:sync-clients-with-ont');
        } catch (\Throwable) {
        }
        $this->info('Sincronización de onus completada correctamente');
    }

    public function syncZones($service)
    {
        $this->info('Sincronizando zonas...');
        $response = $service->syncZones();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente.');
        } else {
            $this->error('Error al sincronizar las zonas. Mensage de error: ' . $response['message']);
        }
    }

    public function syncProfiles($service)
    {
        $this->info('Sincronizando perfiles de velocidad...');
        $response = $service->syncSpeedProfiles();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente.');
        } else {
            $this->error('Error al sincronizar los perfiles de velocidad. Mensage de error: ' . $response['message']);
        }
    }

    public function syncTypeOnus($service)
    {
        $this->info('Sincronizando tipo de onus...');
        $response = $service->syncTypeOnus();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente.');
        } else {
            $this->error('Error al sincronizar lostipos de onus. Mensage de error: ' . $response['message']);
        }
    }

    public function syncOdbs($service)
    {
        $this->info('Sincronizando ODBs...');
        $response = $service->syncODBs();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente.');
        } else {
            $this->error('Error al sincronizar los perfiles de velocidad. Mensage de error: ' . $response['message']);
        }
    }

    public function syncPonPorts($olt = null)
    {
        $this->info('Iniciando sincronización de puertos pon');
        $olts = $olt ? [Olt::find($olt)] : Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando olt ' . $olt->name);
            $olt->syncPonPorts();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
        $this->info('Sincronización completada correctamente');
    }

    public function syncUplinkPorts($olt = null)
    {
        $this->info('Iniciando sincronización de puertos de enlace ascendente');
        $olts = $olt ? [Olt::find($olt)] : Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando olt ' . $olt->name);
            $olt->syncUplinkPorts();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
        $this->info('Sincronización completada correctamente');
    }
}
