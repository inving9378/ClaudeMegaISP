<?php

namespace App\Modules\Addons\Vendedores\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\CutBox;
use App\Models\CutInstallation;
use App\Models\Payment;
use App\Repositories\Sellers\Cuts\InstallationRepository;
use Illuminate\Http\Request;

class InstallationController extends Controller
{
    protected $repository;

    public function __construct()
    {
        $this->repository = new InstallationRepository();
    }

    public function index($id)
    {
        // I3: con el interruptor, listar no escribe; la sincronización es la acción explícita sync().
        if (!config('unificacion.interruptores.vendedores_i3')) {
            app(\App\Modules\Addons\Talento\Services\CajaInstalacionesService::class)->sincronizar((int) $id);
        }
        $object = $this->repository->getByColumns(['box_id' => $id]);
        return response()->json($object);
    }

    /** Acción explícita e idempotente: crea las filas del corte que falten (mismo criterio que antes). */
    public function sync($id)
    {
        app(\App\Modules\Addons\Talento\Services\CajaInstalacionesService::class)->sincronizar((int) $id);
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        // B3b (#951): el patrón '/sellers/cuts/{id}' deja pasar este POST sin el permiso de alta.
        if (config('unificacion.interruptores.cortes_vendedor_b3b')) {
            abort_unless(auth()->user()?->can('seller_cuts_add_installation'), 403);
        }
        $data = $request->all();
        $data['payment_date'] = substr($request->payment_date, 0, 10);
        $object = $this->repository->create($data);
        return response()->json($object);
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $object = $this->repository->update($object, $request->all());
        return response()->json($object);
    }

    public function destroy($id)
    {
        $object = $this->repository->find($id);
        $object->delete();
        return response()->json($object);
    }
}
