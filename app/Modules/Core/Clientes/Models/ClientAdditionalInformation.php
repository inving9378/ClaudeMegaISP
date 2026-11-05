<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;
use App\Models\OltOnu;
use App\Services\Clientes\TipoCliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAdditionalInformation extends BaseModel
{
    use HasFactory,SoftDeletes;
    protected $guarded = [];

    /**
     * #988: tipo_cliente es la única fuente de verdad y nunca queda vacío. is_dedicated se deriva de él.
     * La ficha todavía manda de vuelta los campos retirados: en un cliente existente, category y
     * segmento_id no cambian; un alta sin tipo que traiga la bandera vieja (importación) queda Dedicado.
     */
    protected static function booted(): void
    {
        static::saving(function (self $info) {
            if (! $info->exists && empty($info->tipo_cliente) && $info->is_dedicated) {
                $info->tipo_cliente = TipoCliente::DEDICADO;
            }
            $info->tipo_cliente = TipoCliente::normalizar($info->tipo_cliente);
            $info->is_dedicated = TipoCliente::esDedicado($info->tipo_cliente);

            if (! $info->exists) {
                $info->category = TipoCliente::categoriaDerivada($info->tipo_cliente);
            } else {
                foreach (['category', 'segmento_id'] as $retirado) {
                    if ($info->isDirty($retirado)) {
                        $info->setAttribute($retirado, $info->getOriginal($retirado));
                    }
                }
            }
        });
    }

    /** La categoría vieja, derivada del tipo, para plantillas o reportes que aún la pidan. */
    public function getCategoriaDerivadaAttribute(): string
    {
        return TipoCliente::categoriaDerivada($this->tipo_cliente);
    }

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
