<?php

namespace App\Http\Controllers\Module\Sellers\Cuts;

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
        $box = CutBox::find($id);
        if (isset($box)) {
            $installations = $box->installations->pluck('client_id');
            $news = ClientMainInformation::where('seller_id', $box->user_id)->whereDate('activation_date', $box->created_at->format('Y-m-d'))->whereNotIn('id', $installations)->get();
            $data = [];
            $user_id = auth()->user()->id;
            $now = now();
            $branch_id = $box->user->sucursal_id;
            $client_repository = new ClientRepository();
            foreach ($news as $c) {
                $installation_cost = $client_repository->getPriceInstalationCost($c->client_id);
                $service = $client_repository->getCostAllService($c->client_id);
                $payment = Payment::where('add_by', $box->user_id)->where('is_first_payment', true)->where('paymentable_id', $c->client_id)->where('paymentable_type', Client::class)->whereDate('date', $box->created_at->format('Y-m-d'))->first();
                $data[] = [
                    'service_amount' => $service,
                    'installation_cost' => $installation_cost,
                    'warranty_cost' => null,
                    'constance' => null,
                    'activated' => $payment !== null,
                    'box_id' => $id,
                    'client_id' => $c->id,
                    'technical_id' => null,
                    'branch_id' => $branch_id,
                    'comments' => null,
                    'created_by' => $user_id,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            };
            if (!empty($data)) {
                CutInstallation::insert($data);
            }
        }
        $object = $this->repository->getByColumns(['box_id' => $id]);
        return response()->json($object);
    }

    public function store(Request $request)
    {
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
