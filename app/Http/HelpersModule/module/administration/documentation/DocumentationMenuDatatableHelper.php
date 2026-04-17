<?php

namespace App\Http\HelpersModule\module\administration\documentation;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\DocumentationMenu;
use Illuminate\Support\Arr;

class DocumentationMenuDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(DocumentationMenu::class, 'DocumentationMenu');
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'administration_documentation',
                    'submodule' => 'documentation_menu',
                ];

                if ($type_modal_edit) {
                    $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                }

                $baseActions = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
                
                // Lógica para mostrar el botón de submenús.
                // Crear botón de submenús
                $submenuButton = sprintf(
                    '<a href="/administracion/documentation/documentation_submenu?filter_menu_id=%s" class="btn btn-info btn-sm me-1" title="Ver contenidos"><i class="fas fa-file-alt"></i> Submenús</a>',
                    $id
                );
                
                // Combinar: Botón Submenús + Acciones base
                $nestedData['action'] = $submenuButton . ' ' . $baseActions;
                
                $nestedData['created_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'created_at',
                ])->toHtml();

                $nestedData['updated_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'updated_at',
                ])->toHtml();
                
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