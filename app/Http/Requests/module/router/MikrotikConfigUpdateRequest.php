<?php

namespace App\Http\Requests\module\router;

use App\Rules\ValidateIpRedirect;
use App\Rules\ValidateIpSeparatedByComma;
use App\Rules\ValidateUrlRedirect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class MikrotikConfigUpdateRequest extends FormRequest
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
            'meganet_config_ip_address' => 'required',
            'custom_config_name_parent_router' => 'required',
            'custom_config_comment_parent_router' => 'required',
            'custom_config_comment_sun_router' => 'required',
            'mikrotik_config_server_pppoe_name' => 'required',
            'mikrotik_config_server_pppoe_interface' => 'required',
            'mikrotik_config_server_pppoe_mtu' => 'required',
            'mikrotik_config_server_pppoe_mru' => 'required',
            'mikrotik_config_server_pppoe_profile' => 'required',
            'mikrotik_config_server_ppp_profile' => 'required',
            'mikrotik_config_server_ppp_local_address' => 'required',
            'mikrotik_config_server_ppp_remote_address' => 'required',
            'mikrotik_config_server_ppp_bridge' => 'required'
        ];
    }
}

