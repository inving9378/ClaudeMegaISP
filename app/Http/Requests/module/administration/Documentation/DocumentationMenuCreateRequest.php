<?php

namespace App\Http\Requests\module\administration\Documentation;

use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Foundation\Http\FormRequest;

class DocumentationMenuCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        return [
            'title' => 'required|string|max:255',
        ];
    }
    public function storeMessageRules()
    {
        return [
            'title.required' => 'El campo Título es obligatorio.',
            'title.string' => 'El campo Título debe ser una cadena de texto.',
            'title.max' => 'El campo Título no debe exceder los 255 caracteres.',
            'title.unique' => 'Ya existe el Título que trata de insertar',
        ];
    }
    public function updateRules()
    {
        return [
            'title' => 'required|string|max:255',
        ];
    }
    public function updateMessageRules()
    {
        return [
            'title.required' => 'El campo Título es obligatorio.',
            'title.string' => 'El campo Título debe ser una cadena de texto.',
            'title.max' => 'El campo Título no debe exceder los 255 caracteres.',
            'title.unique' => 'Ya existe el Título que trata de insertar',
        ];
    }
}
