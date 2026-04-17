<?php
Route::get('/reload_db', function () {

    set_time_limit(-1);
    ini_set("memory_limit", -1);
    \Illuminate\Support\Facades\Artisan::call("optimize:clear");
    \Illuminate\Support\Facades\Artisan::call("migrate:fresh");

    $seeder = new \Database\Seeders\TypeBillingSeeder();
    $seeder->run();
    $seeder = new \Database\Seeders\TaxSeeder();
    $seeder->run();
    $seeder = new \Database\Seeders\PackageSeeder();
    $seeder->run();
    $seeder = new \Database\Seeders\ModuleSeeder();
    $seeder->run();


    $util = new \App\Http\Controllers\Utils\UtilController;
    $populate = new \App\Http\Controllers\Utils\MigrateSeedController;

    //Socios
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Socios.csv');
    $array = config('seed.constants.partners');
    $populate->partners($array, $result['records']);

    //Ubicacion
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Ubicaciones.csv');
    $array = config('seed.constants.locations');
    $populate->locations($array, $result['records']);

    //Roles
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Roles.csv');
    $array = config('seed.constants.roles');
    $populate->roles($array, $result['records']);

    //System User
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Administradores.csv');
    $array = config('seed.constants.system_users');
    $populate->system_users($array, $result['records']);

    $populate->permissions();
    $populate->setPermissionTosuperadministratorRol();

    //Networks
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx IPv4 lista de redes.csv');
    $array = config('seed.constants.networks');
    $totals = $populate->networks($array, $result['records']);

    //NetworksIp
    foreach ($totals as $key => $total){
        $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Ver red IPv4 ('.$key.').csv');
        $array = config('seed.constants.networks_ip');
        $populate->networksIp($array, $result['records']);
    }

    //Planes Internet
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Tarifas Internet.csv');
    $array = config('seed.constants.planes_internet');
    $populate->plan_internet($array, $result['records']);

    //Planes Voz
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Tarifas Voz.csv');
    $array = config('seed.constants.planes_voz');
    $populate->plan_voz($array, $result['records']);

    //Planes Custom
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Tarifas Custom.csv');
    $array = config('seed.constants.planes_custom');
    $populate->plan_custom($array, $result['records']);

    //Planes Paquete
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Tarifas Bundles.csv');
    $array = config('seed.constants.planes_bundles');
    $populate->plan_bundle($array, $result['records']);

    //Leads
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Leads list.csv');
    $array = config('seed.constants.crm');
    $populate->crms($array, $result['records']);

    //Customers
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Lista de clientes.csv');
    $array = config('seed.constants.clients');
    $populate->customers($array, $result['records']);

    //Customers Balance y Vista Facturacion
    $result = $util->getRecordsCsv(public_path() . '/csv/Splynx Lista de clientes vista facturacion.csv');
    $array = config('seed.constants.clients_view_facturacion');
    $populate->customers_view_facturation($array, $result['records']);

    die('ok');
});
