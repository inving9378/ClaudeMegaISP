<?php

namespace App\Http\Requests\module\administration\document_template;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class DocumentTemplateCreateRequest extends FormRequest
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
            'name' => ['required', 'unique:document_templates,name,except,id'],
            'type' => 'required',

        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Nombre'
        ];
    }
}
