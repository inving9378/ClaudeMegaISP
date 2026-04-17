<?php

namespace App\Http\Controllers\Module\Administration;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AdministracionController extends Controller
{
    public function __construct()
    {
        $this->data['url'] = 'meganet.module.administration';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.index', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function clearAllClientServices()
    {
        Artisan::call('cleanclientservices:process');
    }

    public function addClientsImportedToMikrotik(Request $request)
    {

        Artisan::call('addclientsimportedtomikrotik:process');

        // Obtener la salida del comando
        $output = Artisan::output();


        return response()->json(['output' => $output], 200);
    }

    public function setStateMunicipalitiesAndColonies()
    {
        Artisan::call('populate_data_base_colony_state_municipality_command:process');
    }

    public function suspendProcess()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        Artisan::call('suspends_services_early_in_the_day:process');

        // Obtener la salida del comando
        $output = Artisan::output();

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        return response()->json(['output' => $output], 200);
    }

    public function billigProcess()
    {

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        Artisan::call('billing_service_command:process');

        // Obtener la salida del comando
        $output = Artisan::output();

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        return response()->json(['output' => $output], 200);
    }

    public function checkProcess()
    {
        exec('ps aux | grep "php artisan"', $output, $return_var);
        $res = array_filter($output, function ($item) {
            return strpos($item, 'schedule:work') !== false;
        });
        $status = count($res) > 0 ?
            'En Ejecución' :
            'Detenido';
        return response()->json(['status' => $status], 200);
    }


    public function activeCommands()
    {
        Artisan::call('start:schedule-process');
        $result = Artisan::output();
        return response()->json(['status' => trim($result)], 200);
    }


    public function showScripts()
    {
        $this->includeLibraryDinamic('App\Models\Client');
        return view('meganet.module.administration.show_scripts.index', $this->data);
    }


    public function rectifyAddressList()
    {
        Artisan::call('rectify_address_list:process');
        $output = Artisan::output();
        return response()->json(['output' => $output], 200);
    }

    public function billingServiceToClientActivePromise()
    {
        Artisan::call('billing_service_client_with_promise_payment_command:process');
        $output = Artisan::output();
        return response()->json(['output' => $output], 200);
    }
}
