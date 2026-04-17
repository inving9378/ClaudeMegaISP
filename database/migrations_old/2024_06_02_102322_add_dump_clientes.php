<?php

use App\Models\User;
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
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('clients')->truncate();
        DB::table('client_main_information')->truncate();
        DB::table('client_additional_information')->truncate();       
        DB::table('billing_configurations')->truncate();
        $users = User::where('id', '!=', 1)->client()->get();
        foreach ($users as $user) {
            $user->delete();
        }   
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 2');
          // Ruta del archivo SQL
       $sqlFilePath = database_path('/sql/clients_db_2-6-24.sql');

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
