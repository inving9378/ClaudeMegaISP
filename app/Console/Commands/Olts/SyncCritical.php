<?php

namespace App\Console\Commands\Olts;

use App\Models\Olt;
use App\Services\OLTsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncCritical extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smartolt:sync-critical {--only= : Especifica qué parte sincronizar (signals|status|temperatures|unconfigured|outages)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para sincronizar señales críticas (señales, estados, temperaturas,...)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $only = $this->option('only') ?? null;
        $validOptions = ['signals', 'status', 'temperatures', 'unconfigured', 'outages'];
        if ($only && !in_array($only, $validOptions)) {
            $this->error("La opción '{$only}' no es válida. Usa: " . implode(', ', $validOptions));
            return 1;
        }
        $service =  new OLTsService();
        if ($only) {
            $this->info("Iniciando sincronización parcial: {$only}");
        } else {
            $this->info("Iniciando sincronización completa de señales críticas...");
        }

        if (!$only || $only === 'temperatures') {
            $this->syncTemperatures($service);
        }

        if (!$only || $only === 'signals') {
            $this->syncSignals($service);
        }

        if (!$only || $only === 'status') {
            $this->syncStatus($service);
        }

        if (!$only || $only === 'unconfigured') {
            $this->syncUnconfigured($service);
        }

        if (!$only || $only === 'outages') {
            $this->syncOutages();
        }

        $this->info('Sincronización de señales críticas completada correctamente');
    }

    public function syncTemperatures($service)
    {
        $this->info('Iniciando sincronización de temperaturas');
        $response = $service->syncTemperatures();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
        } else {
            $this->error($response['message']);
        }
    }

    public function syncSignals($service)
    {
        $this->info('Iniciando sincronización de señales');
        $response = $service->syncOnusSignals();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
            try {
                $this->info('Sincronizando potencia olt con clientes');
                Artisan::call('smartolt:sync-clients-with-ont');
                $this->info('Sincronización completada correctamente');
            } catch (\Throwable) {
            }
        } else {
            $this->error($response['message']);
        }
    }

    public function syncStatus($service)
    {
        $this->info('Iniciando sincronización de estados');
        $response = $service->syncOnusStatus();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
        } else {
            $this->error($response['message']);
        }
    }

    public function syncUnconfigured($service)
    {
        $this->info('Iniciando sincronización de onus desconfiguradas');
        $response = $service->syncUnconfiguredOnus();
        if ($response['success']) {
            $this->info('Sincronización completada correctamente');
        } else {
            $this->error($response['message']);
        }
    }

    public function syncOutages()
    {
        $this->info('Iniciando sincronización de interrupciones');
        $olts = Olt::all();
        foreach ($olts as $olt) {
            $this->info('Sincronizando olt ' . $olt->name);
            $olt->syncInterruptionsPons();
            $this->info('Olt ' . $olt->name . ' sincronizada correctamente.');
        }
    }
}
