<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Models\ClientFiscalData;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Item #556 (CRM-E 02): pasa los datos fiscales del expediente del prospecto a
 * client_fiscal_data (fuente única) al convertir. Solo escribe lo que el expediente tiene;
 * nunca inventa datos ni pisa con vacíos lo ya capturado en la ficha del cliente.
 */
class ExpedienteFiscalService
{
    /** Valida RFC (formato SAT) y CP del expediente. Devuelve lista de errores por campo. */
    public function validar(CrmExpediente $e, array $extra = []): array
    {
        $errores = [];
        $rfc = $e->rfc ? strtoupper(trim($e->rfc)) : null;

        if ($rfc !== null && ! ClientFiscalData::validarRfc($rfc)) {
            $errores['rfc'] = 'El RFC no tiene un formato válido.';
        } elseif ($rfc !== null && $e->tipo_persona) {
            $largo = strlen($rfc);
            if (($e->tipo_persona === 'moral' && $largo !== 12) || ($e->tipo_persona === 'fisica' && $largo !== 13)) {
                $errores['rfc'] = 'El RFC no corresponde al tipo de persona.';
            }
        }
        if ($e->domicilio_fiscal_cp && ! ClientFiscalData::validarCodigoPostal($e->domicilio_fiscal_cp)) {
            $errores['codigo_postal_fiscal'] = 'El código postal debe ser de 5 dígitos.';
        }
        if ($e->regimen_fiscal && ! isset(ClientFiscalData::catalogoRegimenFiscal()[$e->regimen_fiscal])) {
            $errores['regimen_fiscal'] = 'Régimen fiscal fuera del catálogo.';
        }
        if (! empty($extra['forma_pago_cfdi']) && ! isset(ClientFiscalData::catalogoFormaPago()[$extra['forma_pago_cfdi']])) {
            $errores['forma_pago_cfdi'] = 'Forma de pago fuera del catálogo.';
        }
        if (! empty($extra['metodo_pago_cfdi']) && ! isset(ClientFiscalData::catalogoMetodoPago()[$extra['metodo_pago_cfdi']])) {
            $errores['metodo_pago_cfdi'] = 'Método de pago debe ser PUE o PPD.';
        }

        return $errores;
    }

    /**
     * Escribe en client_fiscal_data del cliente. $extra = campos empresariales opcionales
     * (forma_pago_cfdi, dias_credito, contacto_facturacion_*, etc.).
     */
    public function aplicarAlConvertir(CrmExpediente $e, int $clientId, array $extra = [], ?int $userId = null): ClientFiscalData
    {
        if ($errores = $this->validar($e, $extra)) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $permitidos = array_diff(
            (new ClientFiscalData())->getFillable(),
            ['client_id', 'constancia_path', 'created_by', 'updated_by']
        );

        $nuevos = [
            'razon_social' => $e->razon_social,
            'rfc' => $e->rfc ? strtoupper(trim($e->rfc)) : null,
            'codigo_postal_fiscal' => $e->domicilio_fiscal_cp,
            'regimen_fiscal' => $e->regimen_fiscal,
            'uso_cfdi' => $e->uso_cfdi,
            'calle' => $e->domicilio_fiscal_calle,
            'num_exterior' => $e->domicilio_fiscal_num_ext,
            'num_interior' => $e->domicilio_fiscal_num_int,
            'colonia' => $this->nombre('colonies', $e->domicilio_fiscal_colony_id),
            'municipio' => $this->nombre('municipalities', $e->domicilio_fiscal_municipality_id),
            'estado' => $this->nombre('states', $e->domicilio_fiscal_state_id),
            'correo_fiscal' => $e->email,
        ] + array_intersect_key($extra, array_flip($permitidos));

        return DB::transaction(function () use ($clientId, $nuevos, $userId) {
            $f = ClientFiscalData::firstOrNew(['client_id' => $clientId]);
            foreach ($nuevos as $campo => $valor) {
                if ($valor === null || $valor === '') {
                    continue; // no pisar lo ya capturado con vacíos
                }
                $f->{$campo} = $valor;
            }
            $f->client_id = $clientId;
            if (! $f->exists) {
                $f->facturacion_fiscal = true;
                $f->created_by = $userId ?? 0;
            }
            $f->updated_by = $userId ?? 0;
            $f->save();

            return $f;
        });
    }

    private function nombre(string $tabla, $id): ?string
    {
        return $id ? DB::table($tabla)->where('id', $id)->value('name') : null;
    }
}
