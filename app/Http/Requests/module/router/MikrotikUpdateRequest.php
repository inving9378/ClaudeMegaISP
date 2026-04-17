<?php

namespace App\Http\Requests\module\router;

use App\Rules\ValidateIpRedirect;
use App\Rules\ValidateIpSeparatedByComma;
use App\Rules\ValidateUrlRedirect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class MikrotikUpdateRequest extends FormRequest
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
            'login_api' => Rule::requiredIf($request->active == true),
            'port_api' => Rule::requiredIf($request->active == true),
            'url_redirect' => [Rule::requiredIf($request->rule_wireless_access_list == true) , new ValidateUrlRedirect ],
            'ip_redirect' => [Rule::requiredIf($request->rule_wireless_access_list == true), new ValidateIpRedirect],
            'ips_with_comma_permited' => [Rule::requiredIf($request->rule_wireless_access_list == true), new ValidateIpSeparatedByComma],
        ];
    }
}

