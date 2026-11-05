<?php

namespace App\Modules\Addons\Talento\Services;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\CutBox;
use App\Models\CutInstallation;
use App\Models\Payment;

/**
 * UNI-T I1 (#893): único lugar de la lógica «antes de listar instalaciones de un corte, auto-crear
 * las filas de altas activadas ese día por el vendedor». Antes estaba copiada en
 * InstallationController::index (Vendedores) y TalentoCajaVendedorController::installationsIndex.
 */
class CajaInstalacionesService
{
    public function sincronizar(int $boxId): void
    {
        $box = CutBox::find($boxId);
        if (!isset($box)) {
            return;
        }
        $installations = $box->installations->pluck('client_id');
        $news = ClientMainInformation::where('seller_id', $box->user_id)
            ->whereDate('activation_date', $box->created_at->format('Y-m-d'))
            ->whereNotIn('id', $installations)
            ->get();
        $data = [];
        $userId = auth()->user()?->id;
        $now = now();
        $branchId = $box->user->sucursal_id;
        $clientRepository = new ClientRepository();
        foreach ($news as $c) {
            $payment = Payment::where('add_by', $box->user_id)
                ->where('is_first_payment', true)
                ->where('paymentable_id', $c->client_id)
                ->where('paymentable_type', Client::class)
                ->whereDate('date', $box->created_at->format('Y-m-d'))
                ->first();
            $data[] = [
                'service_amount' => $clientRepository->getCostAllService($c->client_id),
                'installation_cost' => $clientRepository->getPriceInstalationCost($c->client_id),
                'warranty_cost' => null,
                'constance' => null,
                'activated' => $payment !== null,
                'box_id' => $boxId,
                'client_id' => $c->id,
                'technical_id' => null,
                'branch_id' => $branchId,
                'comments' => null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($data)) {
            CutInstallation::insert($data);
        }
    }
}
