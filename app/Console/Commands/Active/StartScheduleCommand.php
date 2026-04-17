<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;

class StartScheduleCommand extends Command
{
    protected $signature = 'start:schedule-process';
    protected $description = 'Start the schedule:work process if it is not running';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $output = [];
        $return_var = null;
        // Verifica si el proceso ya está corriendo
        exec('ps aux | grep "php artisan schedule:work" | grep -v grep', $output, $return_var);

        if (count($output) == 0) {
            // Inicia el proceso si no está corriendo
            exec('cd /var/www/MEGANET && nohup php artisan schedule:work > /dev/null 2>&1 &', $output, $return_var);
            $this->info('ok');
        } else {
            $this->info('fail');
        }
    }
}
