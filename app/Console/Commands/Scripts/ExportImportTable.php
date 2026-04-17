<?php

namespace App\Console\Commands\Scripts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportImportTable extends Command
{
    protected $signature = 'table:export-import 
                            {table : Nombre de la tabla}
                            {--export : Exportar la tabla}
                            {--import : Importar la tabla}
                            {--file= : Nombre del archivo}
                            {--connection=mysql : Conexión de origen}
                            {--target-connection= : Conexión de destino}';
    
    protected $description = 'Exportar/Importar una tabla entre bases de datos';

    public function handle()
    {
        $table = $this->argument('table');
        $fileName = $this->option('file') ?: "{$table}_dump.sql";
        
        if ($this->option('export')) {
            $this->exportTable($table, $fileName);
        }
        
        if ($this->option('import')) {
            $this->importTable($table, $fileName);
        }
    }
    
    private function exportTable($table, $fileName)
    {
        $connection = $this->option('connection');
        
        // Obtener estructura de la tabla
        $createTable = DB::connection($connection)->select("SHOW CREATE TABLE `{$table}`")[0];
        
        // Obtener datos
        $data = DB::connection($connection)->table($table)->get();
        
        $sql = [];
        $sql[] = "-- Dump de la tabla {$table}";
        $sql[] = "DROP TABLE IF EXISTS `{$table}`;";
        $sql[] = $createTable->{'Create Table'} . ";";
        
        if ($data->isNotEmpty()) {
            $sql[] = "\n-- Datos de la tabla {$table}";
            
            $chunks = $data->chunk(500);
            foreach ($chunks as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $rowValues = [];
                    foreach ((array)$row as $value) {
                        $rowValues[] = is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                $sql[] = "INSERT INTO `{$table}` VALUES " . implode(', ', $values) . ";";
            }
        }
        
        $content = implode("\n", $sql);
        Storage::disk('local')->put("dumps/{$fileName}", $content);
        
        $this->info("Tabla {$table} exportada a storage/app/dumps/{$fileName}");
    }
    
    private function importTable($table, $fileName)
    {
        $targetConnection = $this->option('target-connection') ?: config('database.default');
        
        $path = storage_path("app/dumps/{$fileName}");
        
        if (!file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return;
        }
        
        $sql = file_get_contents($path);
        
        // Ejecutar el SQL
        DB::connection($targetConnection)->unprepared($sql);
        
        $this->info("Tabla {$table} importada a la conexión: {$targetConnection}");
    }
}