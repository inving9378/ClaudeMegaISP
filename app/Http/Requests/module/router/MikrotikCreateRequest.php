<?php

namespace App\Http\Requests\module\router;

use App\Rules\ValidateIpSeparatedByComma;
use App\Rules\ValidateUrlSeparatedByComma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class MikrotikCreateRequest extends FormRequest
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
            'active' => 'required',
            'login_api' => 'required',
            'port_api' => 'required',
            'url_redirect' => Rule::requiredIf($request->rule_wireless_access_list == true) . '|url' ,
            'ip_redirect' => Rule::requiredIf($request->rule_wireless_access_list == true). '|ipv4',
            'ips_with_comma_permited' => [Rule::requiredIf($request->rule_wireless_access_list == true), new ValidateIpSeparatedByComma],
        ];
    }
}
