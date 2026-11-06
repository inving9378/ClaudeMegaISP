<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Models\ClientFiscalData;
use App\Models\Module;
use App\Modules\Addons\CrmEmpresarial\Models\CrmConversion;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuesta;
use App\Modules\Core\Clientes\Controllers\ClientHelperController;
use App\Models\Client;
use App\Models\ClientAdditionalInformation;
use App\Models\ClientMainInformation;
use App\Modules\Core\CRM\Models\Crm;
use App\Services\Clientes\HistorialCliente;
use App\Services\Clientes\TipoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Item #563 (CRM-E 09): convierte un prospecto empresarial en cliente ADMINISTRATIVO reutilizando
 * el expediente. Nunca activa PPPoE, OLT, IP, servicios ni facturación: reutiliza el mismo alta de
 * cliente que el flujo residencial (ClientHelperController) y deja el estado comercial en el
 * expediente, sin tocar client_main_information.estado más allá de lo que mande el caller.
 */
class ConversionEmpresarialService
{
    public const ESTADOS_COMERCIALES = ['registrado', 'con_contrato', 'pendiente_instalacion', 'activo', 'suspendido', 'inactivo'];

    public function __construct(private ExpedienteValidador $validador = new ExpedienteValidador(), private ExpedienteFiscalService $fiscal = new ExpedienteFiscalService())
    {
    }

    /**
     * Qué falta para convertir (bloqueante) y para operar (pendiente). Lo que no tiene fuente de
     * datos todavía (contrato, factibilidad, autorización) se reporta pendiente; nunca se asume.
     *
     * @return array{puede_convertir:bool, items:array<int,array{clave:string,etiqueta:string,ok:bool,bloqueante:bool}>}
     */
    public function checklist(CrmExpediente $e): array
    {
        $fiscalErr = $this->fiscal->validar($e);
        $aceptada = $e->exists && CrmPropuesta::where('expediente_id', $e->id)->where('estado', 'aceptada')->exists();
        $reps = $e->exists ? $e->representantes()->get() : collect();
        $item = fn (string $clave, string $etiqueta, bool $ok, bool $bloq) => compact('clave', 'etiqueta', 'ok') + ['bloqueante' => $bloq];

        $items = [
            $item('identidad_fiscal', 'Identidad fiscal (tipo de persona y razón social)', filled($e->tipo_persona) && filled($e->razon_social), true),
            $item('rfc', 'RFC válido', filled($e->rfc) && ! isset($fiscalErr['rfc']), true),
            $item('domicilio_fiscal', 'Domicilio fiscal (calle y CP)', filled($e->domicilio_fiscal_calle) && filled($e->domicilio_fiscal_cp) && ! isset($fiscalErr['codigo_postal_fiscal']), true),
            $item('titular', 'Representante legal validado', $reps->where('estado_validacion', 'validado')->isNotEmpty(), false),
            $item('contactos', 'Al menos un contacto', $e->exists && $e->contactos()->count() > 0, false),
            $item('sitios', 'Al menos un sitio de servicio', $e->exists && $e->sitios()->count() > 0, false),
            $item('propuesta_aceptada', 'Propuesta aceptada', $aceptada, false),
            $item('contrato', 'Contrato firmado', false, false),
            $item('factibilidad', 'Factibilidad técnica', false, false),
            $item('autorizacion_instalacion', 'Autorización de instalación', false, false),
        ];

        return [
            'puede_convertir' => collect($items)->where('bloqueante', true)->every(fn ($i) => $i['ok']),
            'items' => $items,
        ];
    }

    /** Coincidencias por RFC normalizado y por nombre/teléfono. Cada una: tipo, id, motivo. */
    public function duplicados(CrmExpediente $e): array
    {
        $out = [];
        $rfc = $this->normalizarRfc($e->rfc);
        if ($rfc) {
            $sql = "UPPER(REPLACE(REPLACE(REPLACE(rfc,' ',''),'-',''),'.',''))";
            foreach (DB::table('client_fiscal_data')->whereRaw("$sql = ?", [$rfc])->get(['client_id']) as $f) {
                $out[] = ['tipo' => 'cliente', 'id' => $f->client_id, 'motivo' => 'rfc'];
            }
            $otros = DB::table('crm_expedientes')->whereNull('deleted_at')->whereRaw("$sql = ?", [$rfc])->where('id', '!=', $e->id ?? 0)->get(['id', 'client_id']);
            foreach ($otros as $o) {
                $out[] = ['tipo' => 'expediente', 'id' => $o->id, 'motivo' => 'rfc'];
            }
        }
        $tel = preg_replace('/\D+/', '', (string) $e->telefono);
        $nombre = trim((string) $e->razon_social);
        $q = DB::table('client_main_information')->whereNull('deleted_at')->where(function ($w) use ($nombre, $tel) {
            $any = false;
            if ($nombre !== '') {
                $w->where('name', $nombre);
                $any = true;
            }
            if (strlen($tel) >= 10) {
                $w->{$any ? 'orWhere' : 'where'}(fn ($x) => $x->where('phone', $tel)->orWhere('phone2', $tel)->orWhere('phone3', $tel));
                $any = true;
            }
            if (! $any) {
                $w->whereRaw('1 = 0');
            }
        });
        foreach ($q->get(['client_id']) as $c) {
            $out[] = ['tipo' => 'cliente', 'id' => $c->client_id, 'motivo' => 'nombre_telefono'];
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    /**
     * @param array $datos campos de client_main_information / client_additional_information
     *                     (colony_id, connection_type, estado, seller_id, medium_id, …). seller_id
     *                     cae al owner_id del prospecto si no viene.
     * @return array{client_id:int, conversion_id:int}
     */
    public function convertir(CrmExpediente $e, array $datos, ?int $userId = null, bool $confirmarDuplicados = false): array
    {
        if ($e->client_id) {
            throw ValidationException::withMessages(['expediente' => ['Este expediente ya fue convertido a cliente.']]);
        }
        $crm = $e->crm_id ? Crm::find($e->crm_id) : null;
        if (! $crm) {
            throw ValidationException::withMessages(['expediente' => ['El expediente no está ligado a un prospecto vigente.']]);
        }
        $cl = $this->checklist($e);
        if (! $cl['puede_convertir']) {
            $falta = collect($cl['items'])->where('bloqueante', true)->where('ok', false)->pluck('etiqueta')->implode(', ');
            throw ValidationException::withMessages(['checklist' => ["Falta para convertir: $falta."]]);
        }
        if (($dup = $this->duplicados($e)) && ! $confirmarDuplicados) {
            throw ValidationException::withMessages(['duplicados' => ['Hay coincidencias; confirma explícitamente para continuar.']]);
        }

        return DB::transaction(function () use ($e, $crm, $datos, $userId, $dup, $cl) {
            $datos += [
                'name' => $e->razon_social, 'email' => $e->email, 'phone' => $e->telefono,
                'seller_id' => $crm->crm_lead_information?->owner_id, // owner_id (prospecto) → seller_id (cliente)
            ];
            $client = Client::create();

            $input = $this->camposDe('ClientMainInformation', $datos) + ['client_id' => $client->id];
            HistorialCliente::conOrigen(
                \App\Models\ClientHistorialCambio::ORIGEN_USUARIO,
                fn () => ClientMainInformation::create($input),
                'Convertido del prospecto empresarial CRM #' . $crm->id
            );
            // Item #1165: tipo_cliente es la única fuente de verdad; el segmento del prospecto (RES/NEG/PYME/DED)
            // se traduce a él y ya no se escribe segmento_id en el cliente.
            $segmentoId = $e->segmento_id ?? $crm->crm_main_information?->segmento_id;
            $tipo = $datos['tipo_cliente'] ?? $this->tipoDeSegmento($segmentoId);
            ClientAdditionalInformation::create($this->camposDe('ClientAdditionalInformation', $datos) + ['client_id' => $client->id, 'tipo_cliente' => $tipo]);

            (new ClientHelperController($client))->stepNeededWhenNewClientIsCreated();
            $this->fiscal->aplicarAlConvertir($e, $client->id, [], $userId);

            $propuesta = CrmPropuesta::where('expediente_id', $e->id)->where('estado', 'aceptada')->latest('id')->first();
            $e->update(['client_id' => $client->id, 'estado_comercial' => 'registrado']);

            $conv = CrmConversion::create([
                'crm_id' => $crm->id, 'client_id' => $client->id, 'expediente_id' => $e->id,
                'propuesta_id' => $propuesta?->id, 'convertido_por' => $userId,
                'snapshot' => ['expediente' => $e->fresh()->toArray(), 'checklist' => $cl['items'], 'duplicados_confirmados' => $dup],
            ]);

            activity()->tap(fn ($a) => $a->client_id = $client->id)
                ->log('Cliente #' . $client->id . ' creado desde conversión empresarial del prospecto #' . $crm->id);
            $crm->delete();

            return ['client_id' => $client->id, 'conversion_id' => $conv->id];
        });
    }

    /** Código de segmento CRM → tipo de cliente. Sin segmento o desconocido: Residencial. */
    private function tipoDeSegmento(?int $segmentoId): string
    {
        $codigo = $segmentoId ? DB::table('crm_segmentos')->where('id', $segmentoId)->value('codigo') : null;

        return TipoCliente::normalizar(match ($codigo) {
            'NEG' => TipoCliente::NEGOCIO,
            'PYME' => TipoCliente::PYME,
            'DED' => TipoCliente::DEDICADO,
            default => TipoCliente::RESIDENCIAL,
        });
    }

    private function camposDe(string $modulo, array $datos): array
    {
        $m = Module::where('name', $modulo)->first();
        abort_if(! $m, 500, "Catálogo de módulo $modulo ausente");

        return array_intersect_key($datos, array_flip($m->fields->pluck('name')->toArray()));
    }

    private function normalizarRfc(?string $rfc): ?string
    {
        $n = strtoupper(preg_replace('/[\s\-.]+/', '', (string) $rfc));

        return $n === '' ? null : $n;
    }
}
