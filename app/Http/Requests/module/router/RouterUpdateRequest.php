<?php

namespace App\Http\Requests\module\router;

use App\Models\Router;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class RouterUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        return [
            'title' => 'required',
            'ip_host' => 'ipv4|required',          
           /*  'nas_ip' => 'required_if:authorization_accounting,' . Router::RADIUS_USER, */
        ];
    }

    public function attributes()
    {
        return [
            'secret_radius' => 'Radius Secreto',
            'nas_ip' => 'NAS IP',
            'authorization_accounting' => 'Autorización / Contabilidad',
        ];
    }
}
