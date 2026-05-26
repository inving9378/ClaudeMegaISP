<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;
use App\Models\OltOnu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAdditionalInformation extends BaseModel
{
    use HasFactory,SoftDeletes;
    protected $guarded = [];

    public function externalFields()
    {
        $onu = OltOnu::firstWhere('client_id', $this->client_id);
        if ($onu) {
            return [
                [
                    'name' => 'olt_power_dbm',
                    'field' => 'olt_power_dbm',
                    'label' => 'Potencia OLT',
                    'type' => 'input-string',
                    'value' => $onu->signal_1490
                ],
                [
                    'name' => 'status_smart',
                    'field' => 'status_smart',
                    'label' => 'Estado Smart',
                    'type' => 'input-string',
                    'value' => $onu->status
                ]
            ];
        }
        return [];
    }
}
