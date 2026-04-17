<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use App\Services\FormatDateService;
use App\Services\LogService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RectifyActivationDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rectify-activation-date
                            {--chunk-size=500 : Number of records to process at a time}
                            {--dry-run : Run without making any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rectify client activation dates based on their first payment date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int)$this->option('chunk-size');
        $logService = new LogService();
        $changedClients = [];
        $skippedClients = [];
        $failedClients = [];

        $this->info('Starting activation date rectification process...');
        $this->info($dryRun ? 'Running in dry-run mode (no changes will be made)' : 'Applying changes');

        Model::withoutEvents(function () use ($dryRun, $chunkSize, $logService, &$changedClients, &$skippedClients, &$failedClients) {
            Client::with(['payments', 'client_main_information'])
                ->whereHas('payments',function($query){
                    $query->whereNotNull('date');
                })
                ->chunkById($chunkSize, function ($clients) use ($dryRun, $logService, &$changedClients, &$skippedClients, &$failedClients) {
                    foreach ($clients as $client) {
                        try {
                            if (!$client->payments->count() || !$client->client_main_information) {
                                $skippedClients[] = $client->id;
                                continue;
                            }

                            $paymentDate = (new FormatDateService($client->payments[0]->date))->formatDate();
                            $activationDate = (new FormatDateService($client->client_main_information->activation_date))->formatDate();

                            if ($activationDate != $paymentDate) {
                                if (!$dryRun) {
                                    DB::transaction(function () use ($client, $paymentDate, $logService,$activationDate) {
                                        $client->client_main_information->activation_date = $paymentDate;
                                        $client->client_main_information->save();

                                        $logService->log(
                                            $client,
                                            'Se rectificó la fecha de activación del cliente ' . $client->id .
                                            ' de ' . $activationDate . ' a ' . $paymentDate .
                                            ' desde el comando rectify-activation-date'
                                        );
                                    });
                                }

                                $changedClients[] = [
                                    'id' => $client->id,
                                    'old_date' => $activationDate,
                                    'new_date' => $paymentDate
                                ];

                                $this->info(sprintf(
                                    'Client %d: %s -> %s',
                                    $client->id,
                                    $activationDate,
                                    $paymentDate
                                ));
                            } else {
                                $skippedClients[] = $client->id;
                            }
                        } catch (\Exception $e) {
                            $failedClients[] = $client->id;
                            Log::error("Error processing client {$client->id}: " . $e->getMessage());
                            $this->error("Error processing client {$client->id}: " . $e->getMessage());
                        }
                    }
                });
        });

        // Generate report
        $this->info(PHP_EOL . 'Process completed. Results:');
        $this->info('Clients updated: ' . count($changedClients));
        $this->info('Clients skipped: ' . count($skippedClients));
        $this->info('Clients failed: ' . count($failedClients));

        if (!$dryRun) {
            Log::info('RectifyActivationDate - Clients updated', ['count' => count($changedClients)]);
            Log::info('RectifyActivationDate - Clients skipped', ['count' => count($skippedClients)]);
            Log::info('RectifyActivationDate - Clients failed', ['count' => count($failedClients)]);
        }

        if ($dryRun && count($changedClients) > 0) {
            $this->table(['ID', 'Old Date', 'New Date'], $changedClients);
        }

        return 0;
    }
}
