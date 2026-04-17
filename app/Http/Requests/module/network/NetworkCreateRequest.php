<?php

namespace App\Http\Requests\module\network;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class NetworkCreateRequest extends FormRequest
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
            'bm' => 'required',
            'comment' => 'required',
            'network' => 'ipv4',
            'type_of_use' => 'required',
            'router_id' => 'required',
        ];
    }
}
