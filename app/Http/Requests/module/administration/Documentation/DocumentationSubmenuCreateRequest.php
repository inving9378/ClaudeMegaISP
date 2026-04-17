<?php

namespace App\Http\Requests\module\administration\Documentation;

use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Foundation\Http\FormRequest;

class DocumentationSubmenuCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        return [
            'title' => 'required|string|max:255',
            'documentation_menu_id' => 'required|exists:documentation_menus,id',
        ];
    }
    public function storeMessageRules()
    {
        return [
            'title.required' => 'El campo Título es obligatorio.',
            'title.string' => 'El campo Título debe ser una cadena de texto.',
            'title.max' => 'El campo Título no debe exceder los 255 caracteres.',
            'documentation_menu_id.required' => 'El campo Menú es obligatorio.',
            'documentation_menu_id.exists' => 'El Menú seleccionado no es válido.',
        ];
    }
    public function updateRules()
    {
        return [
            'title' => 'required|string|max:255',
            'documentation_menu_id' => 'required|exists:documentation_menus,id',
        ];
    }
    public function updateMessageRules()
    {
        return [
            'title.required' => 'El campo Título es obligatorio.',
            'title.string' => 'El campo Título debe ser una cadena de texto.',
            'title.max' => 'El campo Título no debe exceder los 255 caracteres.',
            'documentation_menu_id.required' => 'El campo Menú es obligatorio.',
            'documentation_menu_id.exists' => 'El Menú seleccionado no es válido.',
        ];
    }
}
