<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\FieldTypeRepository;
use App\Models\FieldType;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateColumnInDataBaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'field_module_create_field_migration:process {tabla} {nombre_columna} {tipo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un campo con los datos que se le pasa en la tabla correspondiente';

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
        $fieldTypeRepository = new FieldTypeRepository();
        $nameType = $fieldTypeRepository->getNameById($tipo);
        $typeField = $this->getTypeField($nameType);
        if ($typeField) {
            if (!Schema::hasColumn($tabla, $nombreColumna)) {
                Schema::table($tabla, function (Blueprint $table) use ($nombreColumna, $typeField) {
                    $table->$typeField($nombreColumna)->nullable();
                });
            } else {
                throw new \Exception('La columna ya existe en la tabla.');
            }
        } else {
            throw new \Exception('Este campo no esta definido para crear la columna en la tabla.');
        }
    }

    public function getTypeField($nameType)
    {
        $type = null;
        $arrayTypes = FieldType::TYPE_FIELDS_FOR_CREATE_COLUMN;
        if (array_key_exists($nameType, $arrayTypes)) {
            $type = $arrayTypes[$nameType];
        }
        return $type;
    }
}
