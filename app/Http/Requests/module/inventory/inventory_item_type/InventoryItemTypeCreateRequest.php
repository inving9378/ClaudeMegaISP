<?php

namespace App\Http\Requests\module\inventory\inventory_item_type;

use App\Http\Repository\InventoryItemRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Models\InventoryItemType;
use Illuminate\Foundation\Http\FormRequest;


class InventoryItemTypeCreateRequest extends CrudModalValidationRequest
{
    /** Categoría opcional (se puede dejar Sin clasificar) pero, si viene, debe ser una de las 3. */
    private function categoriaRule(): string
    {
        return 'nullable|in:' . implode(',', array_keys(InventoryItemType::CATEGORIAS));
    }

    public function storeRules()
    {
        $request = request();
        return [
            'name' => 'required',
            'type' => 'required',
            'categoria' => $this->categoriaRule(),
        ];
    }
    public function storeMessageRules()
    {
        return [
            'name.required' => 'El campo Nombre de Tipo de Articulo es obligatorio.',
            'categoria.in'  => 'La categoría seleccionada no es válida.',
        ];
    }

    /**
     * El update no traía reglas (arreglo vacío). Se agrega SOLO la del campo nuevo — meter aquí
     * `name`/`type` como required cambiaría el comportamiento de ediciones que hoy pasan.
     */
    public function updateRules()
    {
        return [
            'categoria' => $this->categoriaRule(),
        ];
    }

    public function updateMessageRules()
    {
        return [
            'categoria.in' => 'La categoría seleccionada no es válida.',
        ];
    }
}
