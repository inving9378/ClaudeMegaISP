<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Support\AccesoSellerPropio;
use App\Http\Controllers\Controller;
use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\CutBox;
use App\Models\CutInstallation;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Core\Configuracion\Repositories\CompanyInformationRepository;
use App\Repositories\Sellers\Cuts\ExtraIncomeRepository;
use App\Repositories\Sellers\Cuts\InstallationRepository;
use App\Repositories\Sellers\Cuts\ObservationRepository;
use App\Repositories\Sellers\Cuts\SuppliersExpenseRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Options;
use Illuminate\Http\Request;

/**
 * Fase C del plan Vendedores→Talento: UI de caja diaria de efectivo replicada
 * dentro de Talento, SIN motor de dinero paralelo — lee/escribe las MISMAS
 * tablas/modelos/repositorios que ya usa `sellers/cuts/*`
 * (CutBox, CutExtraIncome, CutObservation, CutSupplierExpense, CutInstallation
 * vía los repositorios genéricos de app/Repositories/Sellers/Cuts/, que ya
 * incluyen su propio audit trail en TransactionLog). Ningún cálculo de
 * totales se reescribe: los métodos de CutBox (getReceivedPaymentsAmount,
 * getInstallationsAmount, getExtrasAmount, getSuppliersAmount) y el cuerpo de
 * close() son copia literal de BoxController::close() (Vendedores).
 *
 * Nombre "caja-vendedor" (no "caja") a propósito: `talento.caja.*` ya es la
 * caja de HERRAMIENTAS (custodia, bono de salud) — concepto distinto.
 */
class TalentoCajaVendedorController extends Controller
{
    protected ExtraIncomeRepository $extraRepo;
    protected ObservationRepository $observationRepo;
    protected SuppliersExpenseRepository $supplierRepo;
    protected InstallationRepository $installationRepo;

    public function __construct()
    {
        $this->extraRepo = new ExtraIncomeRepository();
        $this->observationRepo = new ObservationRepository();
        $this->supplierRepo = new SuppliersExpenseRepository();
        $this->installationRepo = new InstallationRepository();
    }

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.caja_vendedor');
    }

    // ── Cajas ──────────────────────────────────────────────────────────────

    /**
     * Lista de cajas (cortes) del colaborador. La caja cuelga de `users.id`
     * directo (cut_boxs.user_id) — no requiere que el colaborador tenga fila
     * en `sellers`.
     */
    public function cuts(Request $request, int $colaboradorId)
    {
        $userId = TalentoColaborador::findOrFail($colaboradorId)->user_id;
        AccesoSellerPropio::usuario($userId, AccesoSellerPropio::ALCANCE_CAJA);

        $data = CutBox::query()->where('user_id', $userId);
        if ($request->filled('search')) {
            $data->where('id', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('date')) {
            $from = $request->date[0] ?? null;
            $to = $request->date[1] ?? null;
            if ($from && $to) {
                $data->whereDate('created_at', '>=', Carbon::parse($from)->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::parse($to)->format('Y-m-d'));
            } elseif ($from) {
                $data->whereDate('created_at', '=', Carbon::parse($from)->format('Y-m-d'));
            }
        }

        $total = (clone $data)->count();
        $data->orderBy('id', 'DESC');
        $items = $data->paginate($request->integer('per_page', 20))->items();

        return response()->json(['data' => $items, 'total' => $total]);
    }

    public function box(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        return response()->json(CutBox::findOrFail($boxId));
    }

    public function receivedPayments(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        $box = CutBox::findOrFail($boxId);
        return response()->json($box->getReceivedPayments());
    }

    /**
     * Copia literal de BoxController::close() (Vendedores) — mismo cálculo,
     * mismas columnas. No se reescribe el motor de totales.
     */
    public function close(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        $box = CutBox::findOrFail($boxId);
        abort_if(AccesoSellerPropio::activo() && $box->end_at !== null, 409, 'La caja ya está cerrada.');
        $received = $box->getReceivedPaymentsAmount();
        $installations = $box->getInstallationsAmount();
        $extras = $box->getExtrasAmount();
        $suppliers = $box->getSuppliersAmount();
        $box->end_at = now();
        $box->total_received = $received;
        $box->total_extras = $extras;
        $box->total_technicals = $installations;
        $box->total_proveedores = $suppliers;
        $box->total_net = $received + $extras + $installations - $suppliers;
        $box->save();
        return response()->json($box);
    }

    /**
     * Reusa la MISMA plantilla Blade que Vendedores (meganet.module.sellers.pdf.box)
     * — no se duplica.
     */
    public function pdf(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        $box = CutBox::findOrFail($boxId);
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $data = [
            'box' => $box,
            'received' => $box->getReceivedPayments(true),
            'installations' => $box->installations()->where('activated', true)->get(),
            'extras_incomes' => $box->extras_incomes()->where('payment_method_id', 1),
            'suppliers_expenses' => $box->suppliers_expenses()->where('payment_method_id', 1),
            'observations' => $box->observations,
            'company' => (new CompanyInformationRepository())->getDataCompany(),
        ];
        $pdf = Pdf::loadView('meganet.module.sellers.pdf.box', $data)->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Cierre de la caja %d.pdf', $box->id));
    }

    public function technicals()
    {
        return response()->json(User::technicalRole()->activo()->get());
    }

    // ── Ingresos extra ─────────────────────────────────────────────────────

    public function extrasIndex(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        return response()->json($this->extraRepo->getByColumns(['box_id' => $boxId]));
    }

    public function extrasStore(Request $request)
    {
        $data = $request->all();
        $data['payment_date'] = substr($request->payment_date, 0, 10);
        return response()->json($this->extraRepo->create($data));
    }

    public function extrasUpdate(Request $request, int $id)
    {
        $object = $this->extraRepo->find($id);
        return response()->json($this->extraRepo->update($object, $request->all()));
    }

    public function extrasDestroy(int $id)
    {
        $object = $this->extraRepo->find($id);
        $object->delete();
        return response()->json($object);
    }

    // ── Observaciones ──────────────────────────────────────────────────────

    public function observationsIndex(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        return response()->json($this->observationRepo->getByColumns(['box_id' => $boxId]));
    }

    public function observationsStore(Request $request)
    {
        return response()->json($this->observationRepo->create($request->all()));
    }

    public function observationsUpdate(Request $request, int $id)
    {
        $object = $this->observationRepo->find($id);
        return response()->json($this->observationRepo->update($object, $request->all()));
    }

    public function observationsDestroy(int $id)
    {
        $object = $this->observationRepo->find($id);
        $object->delete();
        return response()->json($object);
    }

    // ── Gastos a proveedores ───────────────────────────────────────────────

    public function suppliersIndex(int $boxId)
    {
        AccesoSellerPropio::usuario((int) CutBox::whereKey($boxId)->value('user_id') ?: null, AccesoSellerPropio::ALCANCE_CAJA);
        return response()->json($this->supplierRepo->getByColumns(['box_id' => $boxId]));
    }

    public function suppliersStore(Request $request)
    {
        $data = $request->all();
        $data['payment_date'] = substr($request->payment_date, 0, 10);
        return response()->json($this->supplierRepo->create($data));
    }

    public function suppliersUpdate(Request $request, int $id)
    {
        $object = $this->supplierRepo->find($id);
        return response()->json($this->supplierRepo->update($object, $request->all()));
    }

    public function suppliersDestroy(int $id)
    {
        $object = $this->supplierRepo->find($id);
        $object->delete();
        return response()->json($object);
    }

    // ── Instalaciones / servicios técnicos ─────────────────────────────────

    /**
     * Comparte con InstallationController::index() (Vendedores) CajaInstalacionesService: antes de
     * listar, auto-crea filas CutInstallation para altas activadas ese mismo
     * día por este colaborador que todavía no tengan fila — mismo criterio de
     * negocio, no se toca.
     */
    public function installationsIndex(int $boxId)
    {
        // I3: con el interruptor, listar no escribe; la sincronización es installationsSync().
        if (!config('unificacion.interruptores.vendedores_i3')) {
            app(\App\Modules\Addons\Talento\Services\CajaInstalacionesService::class)->sincronizar($boxId);
        }

        return response()->json($this->installationRepo->getByColumns(['box_id' => $boxId]));
    }

    /** Acción explícita e idempotente: crea las filas del corte que falten. */
    public function installationsSync(int $boxId)
    {
        app(\App\Modules\Addons\Talento\Services\CajaInstalacionesService::class)->sincronizar($boxId);

        return response()->json(['ok' => true]);
    }

    public function installationsStore(Request $request)
    {
        // Nota: `payment_date` NO es fillable en CutInstallation (a diferencia
        // de extras/proveedores) — el original de Vendedores lo asigna igual
        // pero Eloquent lo descarta en silencio; no se replica aquí.
        return response()->json($this->installationRepo->create($request->all()));
    }

    public function installationsUpdate(Request $request, int $id)
    {
        $object = $this->installationRepo->find($id);
        return response()->json($this->installationRepo->update($object, $request->all()));
    }

    public function installationsDestroy(int $id)
    {
        $object = $this->installationRepo->find($id);
        $object->delete();
        return response()->json($object);
    }
}
