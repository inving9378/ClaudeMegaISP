<?php

namespace App\Http\Requests\module\scheduling\project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class TaskCreateRequest extends FormRequest
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
            'template_verification' => 'required',
            'project_id' => 'required',
            'title' => 'required',
            'status' => 'required',
            'assigned_to' => 'required',
            'priority'=>'required',
            'description' => 'required',
        ];
    }

    public function attributes()
    {
        return [
            'title' => 'Título',
            'project_lead' => 'Project Lead',
            'project_id' => 'Proyecto',
            'status' => 'Estado',
            'assigned_to' => 'Asignado a'
        ];
    }
}
