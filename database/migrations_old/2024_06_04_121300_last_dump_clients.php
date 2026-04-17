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
        ini_set('memory_limit', '1024M');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('receipts')->truncate();
        DB::table('transactions')->truncate();
        DB::table('payments')->truncate();
        DB::table('client_invoices')->truncate();
        DB::table('billing_configurations')->truncate();
        DB::table('balances')->truncate();
        // Ruta del archivo SQL
        $sqlFilePath = database_path('/sql/finanzas.sql');
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

        DB::table('client_custom_services')->truncate();
        DB::table('clients')->truncate();
        DB::table('client_main_information')->truncate();
        DB::table('client_additional_information')->truncate();
        DB::table('users')->truncate();
        DB::table('client_users')->truncate();
        DB::table('network_ips')->truncate();
        DB::table('client_bundle_services')->truncate();
        DB::table('client_voz_services')->truncate();
        DB::table('networks')->truncate();
        DB::table('client_internet_services')->truncate();

        // Ruta del archivo SQL
        $sqlFilePath = database_path('/sql/clientes.sql');
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
