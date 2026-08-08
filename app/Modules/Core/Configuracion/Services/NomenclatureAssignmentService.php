<?php

namespace App\Modules\Core\Configuracion\Services;

use App\Models\Client;
use App\Models\Nomenclature;
use App\Services\LogService;

class NomenclatureAssignmentService
{
    public function assignClient($nomenclatureId, $clientId)
    {
        $nomenclature = Nomenclature::find($nomenclatureId);

        if ($nomenclature->client_id != null) {
            return [
                'success' => false,
                'errors' => [
                    'nomenclature_id' => ["Esta Nomenclatura esta siendo Usada"]
                ]
            ];
        }

        $logService = new LogService();
        $client = Client::find($clientId);
        $oldNomenclature = Nomenclature::where('client_id', $clientId)->first();
        if ($oldNomenclature) {
            Nomenclature::where('client_id', $client->id)->update(['client_id' => null]);
            $logService->log($client, 'Nomenclatura cambiada de ' . $oldNomenclature->name . ' a ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureAssignmentService::assignClient');
        } else {
            $logService->log($client, 'Nomenclatura Asignada ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureAssignmentService::assignClient');
        }

        $nomenclature->update([
            'client_id' => $clientId,
        ]);

        return ['success' => true];
    }
}
