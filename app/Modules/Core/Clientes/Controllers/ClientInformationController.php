<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Jobs\Mikrotik\CheckMikrotikConection;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Repositories\ClientMainInformationRepository;
use App\Services\Client\ClientIdMigrator;
use App\Services\LogService;
use Illuminate\Http\Request;
use App\Http\Requests\module\client\ClientInformationRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Models\Nomenclature;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ClientInformationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function show(ClientInformation $clientMainInformation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientInformation $clientMainInformation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\ClientInformation $clientInformation
     * @return \Illuminate\Http\Response
     */
    public function update(ClientInformationRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules('ClientMainInformation', $request);
        try {
            DB::beginTransaction();
            $client = \App\Models\Client::with('internet_service.router.mikrotik')->findOrFail($id);
            $input = $request->all();
            // Eliminando la fecha de alta del request para que en el actualizar no cambie el campo
            unset($input['discharge_date']);

            if ($this->ifEstadoChangeToLocked($request)) {
                foreach ($client->internet_service as $clientService) {
                    try {
                        Bus::chain([
                            new CheckMikrotikConection($clientService),
                            new MikrotikCreateAddressList($clientService)
                        ])->dispatch();
                        $logService = new LogService();
                        $logService->log($client, 'Su servicio ' . $clientService->service_name . ' fue colocado en address_list desde el ClientInformationController::update porque fue bloqueado');
                    } catch (\Exception $exception) {
                        Log::info($exception);
                    }
                }
            }

            if ($this->ifEstadoChangeToActive($request)) {
                foreach ($client->internet_service as $clientService) {
                    try {
                        Bus::chain([
                            new CheckMikrotikConection($clientService),
                            new MikrotikRemoveClientServiceFromAddressList($clientService)
                        ])->dispatch();
                        $logService = new LogService();
                        $logService->log($client, 'Su servicio ' . $clientService->service_name . ' fue removido de address_list desde el ClientInformationController::update porque fue Activado');
                    } catch (\Exception $exception) {
                        Log::info($exception);
                    }
                }
            }

            $client->billing_configuration->type_billing_id = $input['type_of_billing_id'];

            //TODO Refactorizar
            $client->billing_configuration->save();
            if (!empty($input['box_nomenclator'])) {
                $nomenclature = Nomenclature::find($input['box_nomenclator']);
                if ($nomenclature->client_id != null && $nomenclature->client_id != $client->id) {
                    // Preparamos el mensaje de error con los duplicados
                    $errors = [
                        'box_nomenclator' => ["Esta Nomenclatura esta siendo Usada"]
                    ];
                    return response()->json([
                        'errors' => $errors
                    ], 422);
                }

                $logService = new LogService();
                $oldNomenclature = Nomenclature::where('client_id', $client->id)->first();
                if ($oldNomenclature) {
                    Nomenclature::where('client_id', $client->id)->update(['client_id' => null]);
                    $logService->log($client, 'Nomenclatura cambiada de ' . $oldNomenclature->name . ' a ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el ClientInformationController::update');
                } else {
                    $logService->log($client, 'Nomenclatura Asignada ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::assignClient');
                }

                $nomenclature->update([
                    'client_id' => $id,
                ]);
            }
            // No sobrescribir password si viene vacío del form
            $newPassword = $input['password'] ?? null;
            if (empty($newPassword)) {
                unset($input['password']);
            }

            $this->saveSingleRelationIfExist('App\Models\Client', $client, collect($input));

            // Sincronizar users.password al cambiar la password del cliente
            if (! empty($newPassword)) {
                User::where('login_user', optional($client->client_main_information)->user)
                    ->update(['password' => \App\Services\Security\PasswordService::make($newPassword)]);
            }

            DB::commit();
            return redirect()->back()->with('message', 'Información Actualizada Correctamente');
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClientInformation $clientMainInformation)
    {
        //
    }

    public function getClientWithBalance($id)
    {
        $client = Client::where('id', $id)->with('balance')->first();

        // Referencia MEG de pago del cliente. ensureFor es idempotente y la crea si por
        // algún motivo no existiera → la ficha nunca la muestra vacía (auto-repara).
        $paymentReference = PaymentReferenceService::ensureFor((int) $id)->reference;

        if ($client->balance == null) {
            return [
                'name' => $client->clientFullName(),
                'balance' => 0,
                'payment_reference' => $paymentReference
            ];
        }
        return [
            'name' => $client->clientFullName(),
            'balance' => $client->balance->amount,
            'payment_reference' => $paymentReference
        ];
    }

    public function getClientTicketsOpen($id)
    {
        $client = Client::where('id', $id)->withCount('tickets_open', 'tickets_closed')->first();
        return [
            'open' => $client->tickets_open_count,
            'closed' => $client->tickets_closed_count
        ];
    }

    public function ifEstadoChangeToLocked($request)
    {
        $input = $request->all();
        return ($input['estado'] == 'Bloqueado');
    }

    public function ifEstadoChangeToActive($request)
    {
        $input = $request->all();
        return ($input['estado'] == 'Activo');
    }

    public function getClientStatus($id)
    {
        $client = \App\Models\Client::findOrFail($id);
        return $client->client_main_information->estado;
    }

    public function editId(Request $request)
    {
        if (!$this->userAutenticated()->can('client_edit_id')) {
            throw new Exception('No tiene permisos para realizar esta acción');
        }

        $request->validate([
            'id_actual' => 'required|numeric|exists:clients,id',
            'id_new'    => 'required|numeric|unique:clients,id',
        ]);

        // La reasignación de TODAS las referencias del cliente (directas y
        // polimórficas, descubiertas desde el esquema) + el reajuste del
        // AUTO_INCREMENT viven en ClientIdMigrator. Así no hay que mantener a
        // mano una lista de tablas que siempre se queda corta cuando se agregan
        // módulos. Auditar cobertura sin tocar datos: `php artisan client:references {id}`.
        $idActual = (int) $request->id_actual;
        $idNew = (int) $request->id_new;

        $summary = app(ClientIdMigrator::class)->migrate($idActual, $idNew);

        activity()->tap(function (Activity $activity) use ($idNew) {
            $activity->client_id = $idNew;
        })->log(
            'Cliente #' . $idActual . ' actualizado a ' . $idNew
            . ' desde el edit_id por el usuario ' . Auth::user()->id
            . '. Tablas afectadas: '
            . collect($summary)->map(fn ($n, $t) => "$t=$n")->implode(', ')
        );

        return response()->json(['success' => true, 'affected' => $summary]);
    }

    public function getDataClientToSelectComponent($id)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformation = $clientMainInformationRepository->getModelById($id);
        if ($clientMainInformation) {
            return response()->json([
                'address' => $clientMainInformation->address,
                'geo_data' => $clientMainInformation->geo_data,
                'location_id' => $clientMainInformation->location_id,
            ]);
        }
        return response()->json([
            'address' => '',
            'geo_data' => '',
            'location_id' => '',
        ]);
    }
}
