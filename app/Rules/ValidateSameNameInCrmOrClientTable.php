<?php

namespace App\Rules;

use App\Models\Client;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Contracts\Validation\Rule;

class ValidateSameNameInCrmOrClientTable implements Rule
{
    protected $request;
    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request->all();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $request = $this->request;
        if (($request['tipo_persona'] ?? null) === 'moral') {
            return $this->passesMoral($request);
        }
        // Sin nombre/apellidos no hay duplicado que comparar: lo rechazan las reglas `required`
        // del formulario; aquí no debe tronar por llave inexistente.
        foreach (['name', 'father_last_name', 'mother_last_name'] as $campo) {
            if (!isset($request[$campo])) {
                return true;
            }
        }
        $crm = Crm::with('crm_main_information')->whereHas('crm_main_information', function ($query) use ($request) {
            $query->where('name', $request['name'])
                ->where('father_last_name', $request['father_last_name'])
                ->where('mother_last_name', $request['mother_last_name']);
        })->first();
        if ($crm) {
            $this->message = 'Ya existe un usuario crm en el sistema con los datos proporcionados. <a href="/crm/editar/' . $crm->id . '">' . $crm->crm_main_information->name . '</a>';
        }

        $client = Client::with('client_main_information')->whereHas('client_main_information', function ($query) use ($request) {
            $query->where('name', $request['name'])
                ->where('father_last_name', $request['father_last_name'])
                ->where('mother_last_name', $request['mother_last_name']);
        })->first();
        if ($client) {
            $this->message = 'Ya existe un usuario client en el sistema con los datos proporcionados. <a href="/cliente/editar/' . $client->id . '">' . $client->client_main_information->name . '</a>';
        }
        if ($crm && isset($request['enable_same_name_or_rfc']) || $client && isset($request['enable_same_name_or_rfc'])) {
            return $request['enable_same_name_or_rfc'];
        }
        return true;
    }

    /**
     * CRM-E 03 (#557): persona moral — mismo criterio que la física (el duplicado solo bloquea si
     * «Permitir usuario duplicado» viene en falso) pero comparando razón social, o RFC normalizado
     * cuando se captura, en vez de apellidos.
     */
    protected function passesMoral(array $request): bool
    {
        $nombre = trim((string) ($request['name'] ?? ''));
        $rfc = \App\Support\PersonaMoralCrm::normalizarRfc($request['nif_pasaport'] ?? '');
        $coincide = function ($query) use ($nombre, $rfc) {
            $query->where(function ($q) use ($nombre, $rfc) {
                $q->where('name', $nombre);
                if ($rfc !== '') {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(nif_pasaport, ' ', ''), '-', ''), '.', '')) = ?", [$rfc]);
                }
            });
        };

        $crm = Crm::with('crm_main_information')->whereHas('crm_main_information', $coincide)->first();
        if ($crm) {
            $this->message = 'Ya existe un usuario crm en el sistema con los datos proporcionados. <a href="/crm/editar/' . $crm->id . '">' . $crm->crm_main_information->name . '</a>';
        }
        $client = Client::with('client_main_information')->whereHas('client_main_information', $coincide)->first();
        if ($client) {
            $this->message = 'Ya existe un usuario client en el sistema con los datos proporcionados. <a href="/cliente/editar/' . $client->id . '">' . $client->client_main_information->name . '</a>';
        }
        if (($crm || $client) && isset($request['enable_same_name_or_rfc'])) {
            return (bool) $request['enable_same_name_or_rfc'];
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
