<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $this->dropIndex();

        // Índices para la tabla clients
        Schema::table('clients', function (Blueprint $table) {
            $table->index('id');
            $table->index('fecha_corte');
        });

        // Índices para la tabla client_main_information
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->index('name');
            $table->index('estado');
            $table->index('father_last_name');
            $table->index('mother_last_name');
            $table->index('phone');
            $table->index('phone2');
            $table->index('type_of_billing_id');
            $table->index('email');
            $table->index('street');
            $table->index('zip');
            $table->index('external_number');
            $table->index('internal_number');
        });

        // Índices para la tabla networks
        Schema::table('networks', function (Blueprint $table) {
            $table->index('title');
        });

        // Índices para la tabla network_ips
        Schema::table('network_ips', function (Blueprint $table) {
            $table->index('ip');
        });

        // Índices para la tabla billing_configurations
        Schema::table('billing_configurations', function (Blueprint $table) {
            $table->index('billing_date');
        });
    }

    public function down()
    {
       $this->dropIndex();
    }

    protected function dropIndex()
    {
        // Función para verificar y eliminar un índice si existe
        function dropIndexIfExists($tableName, $indexName)
        {
            $indexExists = DB::select("SHOW INDEX FROM $tableName WHERE Key_name='$indexName'");
            if ($indexExists) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        // Eliminar índices para la tabla clients
        dropIndexIfExists('clients', 'clients_id_index');
        dropIndexIfExists('clients', 'clients_fecha_corte_index');

        // Eliminar índices para la tabla client_main_information
        dropIndexIfExists('client_main_information', 'client_main_information_name_index');
        dropIndexIfExists('client_main_information', 'client_main_information_estado_index');
        dropIndexIfExists('client_main_information', 'client_main_information_father_last_name_index');
        dropIndexIfExists('client_main_information', 'client_main_information_mother_last_name_index');
        dropIndexIfExists('client_main_information', 'client_main_information_phone_index');
        dropIndexIfExists('client_main_information', 'client_main_information_phone2_index');
        dropIndexIfExists('client_main_information', 'client_main_information_type_of_billing_id_index');
        dropIndexIfExists('client_main_information', 'client_main_information_email_index');
        dropIndexIfExists('client_main_information', 'client_main_information_street_index');
        dropIndexIfExists('client_main_information', 'client_main_information_zip_index');
        dropIndexIfExists('client_main_information', 'client_main_information_external_number_index');
        dropIndexIfExists('client_main_information', 'client_main_information_internal_number_index');

        // Eliminar índices para la tabla networks
        dropIndexIfExists('networks', 'networks_title_index');

        // Eliminar índices para la tabla network_ips
        dropIndexIfExists('network_ips', 'network_ips_ip_index');

        // Eliminar índices para la tabla billing_configurations
        dropIndexIfExists('billing_configurations', 'billing_configurations_billing_date_index');
    }
};
