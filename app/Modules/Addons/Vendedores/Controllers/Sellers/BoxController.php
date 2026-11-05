<?php

namespace App\Modules\Addons\Vendedores\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Repositories\CompanyInformationRepository;
use App\Models\CutBox;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoxController extends Controller
{
    /**
     * #700: la caja es de un usuario. Quien no es dueño solo la ve/opera si tiene el alcance de
     * cortes (`seller_cuts`, o es admin). Con solo el permiso de ruta de otra pantalla
     * (p. ej. `seller_view_panel`) únicamente puede tocar la propia.
     */
    private function autorizarCaja(int $ownerId): void
    {
        $actor = auth()->user();
        abort_if(!$actor, 401);
        if ((int) $actor->id === $ownerId || $actor->isAdmin() || $actor->can('seller_cuts')) {
            return;
        }
        abort(403, 'No tienes autorización para consultar la caja de otro usuario.');
    }

    private function cajaOrFail($id): CutBox
    {
        $box = CutBox::find($id);
        abort_if(!$box, 404, 'Corte no encontrado.');
        $this->autorizarCaja((int) $box->user_id);
        return $box;
    }


    public function getCurrentBox($id)
    {
        $user = User::find($id);
        abort_if(!$user, 404, 'Usuario no encontrado.');
        $this->autorizarCaja((int) $user->id);
        $box = $user->getCurrentBox();
        return response()->json($box);
    }

    public function findBox($id)
    {
        return response()->json($this->cajaOrFail($id));
    }

    public function getReceivedPaymentsByBox($id)
    {
        $box = $this->cajaOrFail($id);
        return response()->json($box->getReceivedPayments());
    }

    public function close($id)
    {
        $box = $this->cajaOrFail($id);
        // B5b: una caja cerrada no se recalcula.
        abort_if(config('unificacion.interruptores.comisiones_acceso_b5b') && $box->end_at !== null, 409, 'La caja ya está cerrada.');
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

    public function technicals()
    {
        $objects = User::technicalRole()->activo()->get();
        return response()->json($objects);
    }

    public function cuts(Request $request, $id)
    {
        $this->autorizarCaja((int) $id);
        $data = CutBox::query();
        $data->where('user_id', $id);
        if (isset($request->search)) {
            $data->where('id', 'like', '%' . $request->search . '%');
        }
        if (isset($request->date)) {
            $from = $request->date[0];
            $to = $request->date[1];
            if ($from && $to) {
                $data->whereDate('created_at', '>=',  Carbon::parse($from)->format('Y-m-d'))->whereDate('created_at', '<=',  Carbon::parse($to)->format('Y-m-d'));
            } else {
                $data->whereDate('created_at', '=', Carbon::parse($from)->format('Y-m-d'));
            }
            $data->where('id', 'like', '%' . $request->search . '%');
        }
        if (isset($request->search)) {
            $data->where('id', 'like', '%' . $request->search . '%');
        }
        $total = clone($data);
        if ($request->sortBy) {
            $data->orderBy($request->sortBy, $request->descending ? 'DESC' : 'ASC');
        } else {
            $data->orderBy('id', 'DESC');
        }
        $data = $data->paginate($request->rowsPerPage ?? 20, ['*'], 'page', $request->page)->items();
        return response()->json([
            'data' => $data,
            'total' => $total->count()
        ]);
    }

    public function pdf($id)
    {
        $box = $this->cajaOrFail($id);
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
            'company' => (new CompanyInformationRepository())->getDataCompany()
        ];
        //return view('meganet.module.sellers.pdf.box', $data);
        $pdf = Pdf::loadView('meganet.module.sellers.pdf.box', $data)->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Cierre de la caja %d.pdf', $box->id));
    }
}
