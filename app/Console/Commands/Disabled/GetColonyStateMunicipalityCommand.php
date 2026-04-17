<?php

namespace App\Console\Commands\Disabled;

use App\Http\Traits\RouterConnection;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetColonyStateMunicipalityCommand extends Command
{
    use RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_colony_municipality_and_state:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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

        /*  DB::table('states')->truncate();
        $response = Http::get('https://api.copomex.com/query/get_estados', [
            'token' => 'a9a78387-28cb-4544-bebc-055d49e91ccb'
        ]);
        $data = $response->json();
        // create colony
        foreach ($data['response']['estado'] as $name) {
            State::create([
                'name' => $name,
            ]);
        }
        dd(); */

        /* DB::table('municipalities')->truncate();
        $states = State::get();
        foreach ($states as $state) {
            $response = Http::get('https://api.copomex.com/query/get_municipio_por_estado/' . $state->name, [
                'token' => 'a9a78387-28cb-4544-bebc-055d49e91ccb'
            ]);
            $data = $response->json();
            // create colony
            foreach ($data['response']['municipios'] as $name) {
                Municipality::create([
                    'name' => $name,
                    'state_id' => $state->id
                ]);
            }
        }
        dd(); */

        //las colonias donde el estado es el #19 se fue la corriente Revisar despues
        //32
        $municipalities  = Municipality::with('state')->where('state_id',32)->get();
        dd($municipalities->count());
        $colonies = [];
        foreach ($municipalities as $municipality) {
            $response = Http::get('https://api.copomex.com/query/get_colonia_por_estado_municipio', [
                'token' => 'a9a78387-28cb-4544-bebc-055d49e91ccb',
                'estado'=>$municipality->state->name,
                'municipio'=>$municipality->name
            ]);
            $data = $response->json();
            // create colony
            foreach ($data['response'] as $name => $postalCode) {
                $colonies[] = [
                    'name' => $name,
                    'municipality_id' => $municipality->id,
                    'data' => $postalCode
                ];
            }
        }
      /*   // Insertar las colonias en lotes de 1000
        $chunks = array_chunk($colonies, 1000);
        foreach ($chunks as $chunk) {
            DB::table('colonies')->insert($chunk);
            sleep(2); // Esperar 2 segundo antes de insertar el siguiente lote
        } */
    }
}
