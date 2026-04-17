<?php

namespace App\Console\Commands\Scripts;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DeleteColumnInDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete_column__in_data_base:process {tabla} {nombre_columna} {tipo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea una migracion con los datos que se le pasa y la ejecuta para crear un nuevo campo en la tabla correspondiente';

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
        $tabla = $this->argument('tabla');
        $nombreColumna = $this->argument('nombre_columna');
        $tipo = $this->argument('tipo');

        if (Schema::hasColumn($tabla, $nombreColumna)) {
            Schema::table($tabla, function (Blueprint $table) use ($nombreColumna, $tipo) {
                $table->dropColumn($nombreColumna);
            });
        }
    }
}
