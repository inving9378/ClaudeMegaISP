<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlFilePath = database_path('/sql/Dump_package.sql');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('packages')->truncate();
        DB::table('crud_packages')->truncate();


        // Verifica si el archivo existe
        if (File::exists($sqlFilePath)) {
            // Lee el contenido del archivo SQL
            $sql = File::get($sqlFilePath);
            if (!empty($sql)) {
                DB::unprepared($sql);
            } else {
                throw new \Exception("El archivo SQL está vacío: $sqlFilePath");
            }
        } else {
            throw new \Exception("El archivo SQL no se encontró: $sqlFilePath");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
