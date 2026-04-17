<?php

namespace App\Http\Controllers\Module\Setting\Nomenclature;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\nomenclature\NomenclatureDatatableHelper;
use App\Http\Repository\ClientAdditionalInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Models\BoxZone;
use App\Models\Client;
use App\Models\District;
use App\Models\Nomenclature;
use App\Models\Zone;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NomenclatureController extends CrudModalController
{
    public function __construct(NomenclatureDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\Nomenclature';
        $this->data['url'] = 'meganet.module.setting.nomenclature';
        $this->data['module'] = 'Nomenclature';
    }

    public function store(Request $request)
    {
        $input = $request->all();
        if (isset($input['multiple']) && !empty($input['multiple'])) {
            set_time_limit(0);
            ini_set('memory_limit', '8912M');

            $input['multiple'] = str_replace(" ", "", $input['multiple']);

            $arrayMultiple = explode(",", $input['multiple']);
            // Verificar duplicados
            $duplicates = array_diff_assoc($arrayMultiple, array_unique($arrayMultiple));

            if (!empty($duplicates)) {
                // Convertimos los duplicados a una cadena
                $duplicatesList = implode(", ", $duplicates);

                // Preparamos el mensaje de error con los duplicados
                $errors = [
                    'multiple' => ["Las siguientes nomenclaturas están duplicadas: $duplicatesList."]
                ];

                return response()->json([
                    'errors' => $errors
                ], 422);
            }

            if (!empty($input['district']) || !empty($input['zone']) || !empty($input['client'])) {
                // Retornar un error si hay combinación inválida de campos
                return response()->json([
                    'errors' => [
                        'multiple' => ["No es posible añadir una Nomenclatura simple con una multiple."]
                    ]
                ], 422);
            }

            DB::transaction(function () use ($arrayMultiple) {
                foreach ($arrayMultiple as $nomenclature) {
                    Nomenclature::create(['name' => $nomenclature]);
                    $this->verifyIfExistDistrictZoneBoxAndCreate($nomenclature);
                }
            });
        } else {
            $validator = Validator::make($input, [
                'district' => 'required',
                'zone' => 'required',    // Puedes añadir más validaciones si es necesario
                'box_zone' => 'required',
                'client' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $nomenclature = "D" . $input['district'] . "Z" . $input['zone'] . "C" . $input['box_zone'] . ':' . $input['client'];


            $nomenclatureExist = Nomenclature::where('name', $nomenclature)->first();
            if (!$nomenclatureExist) {
                Nomenclature::create(['name' => $nomenclature]);
            }
            $this->verifyIfExistDistrictZoneBoxAndCreate($nomenclature);
        }


        return response()->json(['status' => 'success'], 200);
    }


    public function verifyIfExistDistrictZoneBoxAndCreate($nomenclature)
    {
        // Extraer el distrito, zona, caja y cliente
        $district = $this->extractDistrict($nomenclature);
        $zone = $this->extractZone($nomenclature);
        $boxZone = $this->extractBoxZone($nomenclature);
        $client = $this->extractClient($nomenclature);

        // Crear o encontrar el distrito
        $districtModel = District::firstOrCreate(['name' => $district]);

        // Crear o encontrar la zona
        $zoneModel = Zone::firstOrCreate([
            'name' => $zone,
            'district_id' => $districtModel->id
        ]);

        // Crear o encontrar la caja y asociarla al cliente
        BoxZone::firstOrCreate([
            'name' => $boxZone,
            'zone_id' => $zoneModel->id,
            'client' => $client
        ]);
    }

    // Función para extraer el distrito de la nomenclatura
    public function extractDistrict($nomenclature)
    {
        // Utiliza una expresión regular para buscar el distrito que empieza con "D" seguido de números
        preg_match('/D(\d+)/', $nomenclature, $matches);

        if (!empty($matches)) {
            // El distrito será el valor en la primera captura (número después de la "D")
            return $matches[1];
        }

        // Si no se encuentra, retornamos null o un valor que consideres adecuado
        return null;
    }

    // Función para extraer la zona de la nomenclatura
    public function extractZone($nomenclature)
    {
        // Utiliza una expresión regular para buscar la zona que empieza con "Z" seguido de números
        preg_match('/Z(\d+)/', $nomenclature, $matches);

        if (!empty($matches)) {
            // La zona será el valor en la primera captura (número después de la "Z")
            return $matches[1];
        }

        return null;
    }

    // Función para extraer la caja de la nomenclatura
    public function extractBoxZone($nomenclature)
    {
        // Utiliza una expresión regular para buscar la caja que empieza con "C" seguido de números
        preg_match('/C(\d+)/', $nomenclature, $matches);

        if (!empty($matches)) {
            // La caja será el valor en la primera captura (número después de la "C")
            return $matches[1];
        }

        return null;
    }

    // Función para extraer el cliente de la nomenclatura
    public function extractClient($nomenclature)
    {
        // Utiliza una expresión regular para buscar el número después de los dos puntos ":"
        preg_match('/:(\d+)/', $nomenclature, $matches);

        if (!empty($matches)) {
            // El cliente será el valor en la primera captura (número después del ":")
            return $matches[1];
        }

        // Si no se encuentra, retornamos null o un valor que consideres adecuado
        return null;
    }


    public function assignClient(Request $request, $id)
    {
        //TODO PASAR PARA UN SERVICIO
        $nomenclature = $this->data['model']::find($request->nomenclature_id);

        if ($nomenclature->client_id != null) {
            // Preparamos el mensaje de error con los duplicados
            $errors = [
                'nomenclature_id' => ["Esta Nomenclatura esta siendo Usada"]
            ];

            return response()->json([
                'errors' => $errors
            ], 422);
        }

        $logService = new LogService();
        $client = Client::find($id);
        $oldNomenclature = $this->data['model']::where('client_id', $id)->first();
        if ($oldNomenclature) {
            Nomenclature::where('client_id', $client->id)->update(['client_id' => null]);
            $logService->log($client, 'Nomenclatura cambiada de ' . $oldNomenclature->name . ' a ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::assignClient');
        } else {
            $logService->log($client, 'Nomenclatura Asignada ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::assignClient');
        }

        $nomenclature->update([
            'client_id' => $id,
        ]);

        return response()->json(['status' => 'success'], 200);
    }


    public function getNomenclatureByClient($id)
    {
        $nomenclature = $this->data['model']::where('client_id', $id)->first();

        if ($nomenclature) {
            return response()->json(['id' => $nomenclature->id]);
        }
        return response()->json(['id' => null]);
    }


    public function addExcel(Request $request)
    {
        set_time_limit(0); // Sin límite de tiempo para grandes cantidades de datos
        ini_set('memory_limit', '8912M'); // Incrementa el límite de memoria si es necesario
        // Validamos que el archivo subido sea un Excel
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        // Obtenemos el archivo
        $file = $request->file('file')->getRealPath();

        // Cargar el archivo Excel usando PhpSpreadsheet
        $spreadsheet = IOFactory::load($file);
        $nonEmptyCells = [];

        // Recorremos todas las hojas del archivo Excel
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetData = $sheet->toArray(null, true, true, true); // Convertimos la hoja en un array

            // Recorremos cada fila de la hoja
            foreach ($sheetData as $row) {
                // Recorremos cada celda de la fila
                foreach ($row as $cell) {
                    // Si la celda no está vacía, la añadimos al array
                    if (!empty($cell)) {
                        $nonEmptyCells[] = $cell;
                    }
                }
            }
        }

        // Ordenamos los datos de la forma en que los necesitas
        $sortedCells = $this->sortCells($nonEmptyCells);

        // Insertamos los datos
        $this->addFomExcel($sortedCells);
    }

    public function sortCells($cells)
    {
        usort($cells, function ($a, $b) {
            // Definimos un patrón que busca coincidencias en formato D#, Z#, C#:# (ej. D1Z1C1:1)
            $pattern = '/D(\d+)Z(\d+)C(\d+):(\d+)/';

            // Aplicamos la expresión regular en $a y $b
            $matchA = preg_match($pattern, $a, $matchesA);
            $matchB = preg_match($pattern, $b, $matchesB);

            // Si alguno de los dos no coincide con el formato esperado, no los comparamos
            if (!$matchA || !$matchB) {
                return 0;
            }

            // Comparar Distrito (D)
            if ($matchesA[1] != $matchesB[1]) {
                return $matchesA[1] - $matchesB[1];
            }

            // Comparar Zona (Z)
            if ($matchesA[2] != $matchesB[2]) {
                return $matchesA[2] - $matchesB[2];
            }

            // Comparar Caja (C)
            if ($matchesA[3] != $matchesB[3]) {
                return $matchesA[3] - $matchesB[3];
            }

            // Comparar Cliente (número después de ':')
            return $matchesA[4] - $matchesB[4];
        });

        return $cells;
    }

    public function addFomExcel($sortedCells)
    {
        $arrayMultiple = $sortedCells;

        // Iniciamos una transacción para asegurar que todos los datos se inserten o se reviertan en caso de error
        DB::transaction(function () use ($arrayMultiple) {
            foreach ($arrayMultiple as $nomenclature) {
                DB::table('nomenclatures')->insert([
                    'name' => $nomenclature,
                    'client_id' => null
                ]);
            }
        });


        return response()->json(['success' => true]);
    }


    public function generarNomenclatura(Request $request)
    {
        set_time_limit(0); // Sin límite de tiempo
        ini_set('memory_limit', '8912M'); // Aumentar el límite de memoria si es necesario

        $Dist = $request->dist;
        $Zon = $request->zon;
        $nameDist = $request->nameDist ?? 'D';
        $nameZon = $request->nameZon ?? 'Z';

        $d = 1;
        $z = 1;
        $b = 1;
        $c = 1;

        $nomenclaturas = []; // Array para almacenar las nomenclaturas
        $duplicados = [];    // Array para almacenar los duplicados
        $contador = 0;

        DB::beginTransaction(); // Iniciar transacción para mayor rendimiento

        try {
            while (true) {
                $cadena = $nameDist . $d . $nameZon . $z . 'C' . $b . ':' . $c;  // Formato de la nomenclatura

                // Verificar si la nomenclatura ya existe
                $exists = DB::table('nomenclatures')->where('name', $cadena)->exists();

                if ($exists) {
                    // Almacenar los duplicados
                    $duplicados[] = $cadena;
                } else {
                    // Agregar nomenclatura al array
                    $nomenclaturas[] = ['name' => $cadena];

                    // Cada 1000 registros, hacer la inserción en la base de datos
                    if (count($nomenclaturas) == 1000) {
                        DB::table('nomenclatures')->insert($nomenclaturas);
                        $nomenclaturas = []; // Limpiar el array después de la inserción
                    }
                }

                // Incrementar los contadores según tu estructura
                if ($c == 16) {
                    $b++;
                    $c = 0;

                    if ($b == 17) {
                        $z++;
                        $b = 1;

                        if ($z == ($Zon + 1)) {
                            $d++;
                            $z = 1;

                            if ($d == ($Dist + 1)) {
                                // Ejecutar la última inserción si hay datos pendientes
                                if (!empty($nomenclaturas)) {
                                    DB::table('nomenclatures')->insert($nomenclaturas);
                                }
                                DB::commit(); // Confirmar transacción
                                break;
                            }
                        }
                    }
                }

                // Avanzar los contadores
                $c++;
                $contador++;
            }

            // Respuesta final, incluyendo duplicados
            if (!empty($duplicados)) {
                return response()->json([
                    'message' => 'Inserción completa, pero algunas nomenclaturas ya existían.',
                    'duplicados' => $duplicados,
                    'total_insertadas' => $contador
                ], 200);
            }

            return response()->json(['message' => 'Todas las nomenclaturas fueron insertadas correctamente', 'total_insertadas' => $contador], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir transacción en caso de error
            return response()->json(['error' => 'Ocurrió un error al insertar las nomenclaturas.'], 500);
        }
    }

    public function changeClient(Request $request)
    {
        $request->validate([
            'id_client_new' => 'required|numeric|unique:nomenclatures,client_id',
        ], [
            'id_client_new.required' => 'El campo ID de cliente es obligatorio.',
            'id_client_new.numeric' => 'El campo ID de cliente debe ser un número.',
            'id_client_new.unique' => 'El ID de cliente ya está en uso en otra nomenclatura.',
        ]);
        $nomenclature = $this->data['model']::find($request->id);

        if (!$nomenclature) {
            return response()->json(['error' => 'Nomenclatura no encontrada.'], 404);
        }


        $clientOld = (new ClientRepository())->getClientById($nomenclature->client_id);
        $logService = new LogService();
        if ($clientOld) {
            $logService->log($clientOld, 'Se le elimina la nomenclatura ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::changeClient');
            $clientAditionalInformationRepository = new ClientAdditionalInformationRepository();
            $clientAditionalInformationRepository->setNomenclatureByClient($clientOld->id, null);
        }
        $nomenclature->update([
            'client_id' => $request->id_client_new
        ]);

        $clientNew = (new ClientRepository())->getClientById($request->id_client_new);
        $nomenclature->refresh();
        $logService = new LogService();
        $logService->log($clientNew, 'Se le agrega la nomenclatura ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::changeClient');

        $clientAditionalInformationRepository = new ClientAdditionalInformationRepository();
        $clientAditionalInformationRepository->setNomenclatureByClient($clientNew->id, $nomenclature->id);

        return response()->json(['success' => true]);
    }
}
