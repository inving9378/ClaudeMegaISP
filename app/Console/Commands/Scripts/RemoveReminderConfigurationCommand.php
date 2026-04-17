<?php

namespace App\Console\Commands\Scripts;

use App\Models\RemindersConfiguration;
use Illuminate\Console\Command;

class RemoveReminderConfigurationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-reminder-configuration-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remueve RemindersConfiguration duplicados o que no tienen clientes asignados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        RemindersConfiguration::whereDoesntHave('client')->delete();

        $t = RemindersConfiguration::select('client_id')->groupBy('client_id')->havingRaw('count(*) > 1')->get();
        foreach ($t as $value) {
            RemindersConfiguration::where('client_id', $value->client_id)->delete();
        }
    }
}
