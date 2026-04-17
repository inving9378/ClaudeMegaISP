<?php


namespace App\Http\HelpersModule\module\inventory\inventoryItemcustom;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\InventoryItemCustomModel;
use Illuminate\Support\Arr;

class InventoryItemCustomModelDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(InventoryItemCustomModel::class, 'InventoryItemCustomModel');
    }


    public function transform($request)
    {
        // 1. Pre-cálculo de constantes y rutas (Fuera del bucle)
        $isModal = $this->includeButtonEditTypeModalIfIsRequested($request);
        $type_modal_edit = $isModal ? '_type_modal' : '';
        $actionView = 'meganet.shared.table.actions' . $type_modal_edit;

        $moduleName = $this->moduleName;
        $moduleLower = strtolower($moduleName);
        $modalData = $isModal ? ($request['request']->modal['modal'] ?? null) : null;
        $baseUrl = url('/inventory/inventory_item_custom/items') . '/';

        $data = [];
        $idStore = null;

        $referer = $request['request']->header('referer');

        // Usamos una expresión regular para sacar el último número de esa URL
        $idStore = null;
        if (preg_match('/my-store\/(\d+)/', $referer, $matches)) {
            $idStore = $matches[1]; // Esto capturará el "2"
        }

        if (!empty($request['array'])) {
            foreach ($request['array'] as $value) {
                $id = $value->id;
                $rowUrl = $baseUrl . $id . ($idStore ? '?store-id=' . $idStore : '');
                $nestedData = [];
                // 2. Procesar columnas sin llamar a Blade
                foreach ($this->columns as $column) {
                    // Lógica específica de datos
                    if ($column === 'inventory_item_type_id') {
                        $cellValue = $value->inventory_item_type->name ?? '';
                    } else {
                        $cellValue = $value->{$column};
                    }
                    $nestedData[$column] = '<a href="' . $rowUrl . '">' . e($cellValue) . '</a>';
                }
                // 3. Solo usamos Blade para las acciones (que suelen ser más complejas)
                $nestedData['action'] = view($actionView, [
                    'id' => $id,
                    'module' => $moduleName,
                    'group' => $moduleLower,
                    'submodule' => $moduleLower,
                    'modal' => $modalData
                ])->render();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }
}
