<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateFilesClientChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-files-client-change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clients = Client::get();
        foreach ($clients as $client) {
            $activities = $client->activities()->orderBy('id', 'desc')->get();

            foreach ($activities as $activity) {
                $data = json_decode($activity->properties, true);
                if (isset($data['attributes']['id']) && isset($data['old']['id']) && $data['old']['id'] !=  $data['attributes']['id']) {
                    Log::info('Fecha del Activity: ' . $activity->created_at . 'Anterior: ' . $data['old']['id'] . '/// Actual:' . $data['attributes']['id']);
                }
            }
        }
    }
}
