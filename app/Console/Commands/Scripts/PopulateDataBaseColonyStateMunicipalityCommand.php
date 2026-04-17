<?php

namespace App\Console\Commands\Scripts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateDataBaseColonyStateMunicipalityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'populate_data_base_colony_state_municipality_command:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::table('states')->truncate();
        $rutaArchivo = realpath(config_path('state_municipalities_and_colonies/states.sql'));
        $sql = file_get_contents($rutaArchivo);
        DB::unprepared($sql);
        dump("Estados Actualizados");

        DB::table('municipalities')->truncate();
        $rutaArchivo = realpath(config_path('state_municipalities_and_colonies/municipalities.sql'));
        $sql = file_get_contents($rutaArchivo);
        DB::unprepared($sql);
        dump("Municipios Actualizados");

        DB::table('colonies')->truncate();
        $rutaArchivo = realpath(config_path('state_municipalities_and_colonies/colonies.sql'));
        $sql = file_get_contents($rutaArchivo);
        DB::unprepared($sql);
        dump("Colonias Actualizados");

    }
}
