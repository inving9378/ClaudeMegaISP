<?php

namespace App\Console\Commands\Scripts;

use App\Models\ActivityLog;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScriptFixedFechaCorte extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:script-fixed-fecha-corte';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cuando el cliente tiene fecha de corte null le ponga la ultima disponible en el activity log';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $clients = Client::whereHas('client_main_information', function ($query) {
            $query->stateActive();
        })
            ->whereNull('fecha_corte')
            ->orderBy('id', 'desc')
            ->get();

        dump($clients->count());

        foreach ($clients as $client) {
            $log = ActivityLog::where('subject_type', "App\\Models\\Client")
                ->where('subject_id', $client->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($log && $log->properties) {
                if (isset(json_decode($log->properties, true)['attributes']['fecha_corte'])) {
                    $fechaCorte = json_decode($log->properties, true)['attributes']['fecha_corte'];
                    $client->fecha_corte = $fechaCorte;
                    $client->save();

                    activity()->log('ScriptFixedFechaCorte se restablece la fecha de corte del cliente #' . $client->id . ' a '. $fechaCorte .' que es la ultima disponible en el activity log');
                }
            }
        }

        dump('ok');

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
