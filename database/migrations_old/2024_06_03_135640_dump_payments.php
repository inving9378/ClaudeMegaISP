<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('payments')->truncate();
        DB::table('receipts')->truncate();
      
        // Ruta del archivo SQL
        $sqlFilePath = database_path('/sql/payments.sql');
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
