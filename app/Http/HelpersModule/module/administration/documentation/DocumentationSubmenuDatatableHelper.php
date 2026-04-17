<?php

namespace App\Http\HelpersModule\module\administration\documentation;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\DocumentationSubmenu;
use Illuminate\Support\Arr;

class DocumentationSubmenuDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(DocumentationSubmenu::class, 'DocumentationSubmenu');
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
                    // Transformar documentation_menu_id para mostrar el título del menú
                    if ($val == 'documentation_menu_id') {
                        $nestedData[$val] = $value->documentation_menu_title;
                        
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => 'documentation_submenu',
                    'submodule' => 'documentation_submenu',
                ];

                if ($type_modal_edit) {
                    $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                }

                // Transformar el título en enlace a submenús
                // $nestedData['documentation_menu_title'] = view('meganet.shared.table.module.administration.documentation.menu.title', [
                //     'dir' => "/administracion/documentation/documentation_menu",
                //     'title' => $value->title
                // ])->toHtml();

                // Obtener acciones base del parent (editar/eliminar)
                $baseActions = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
                
                // NUEVO: Crear botón de contenidos
                $contentButton = sprintf(
                    '<a href="/administracion/documentation/documentation_submenu/%s" class="btn btn-info btn-sm me-1" title="Ver contenidos"><i class="fas fa-file-alt"></i> Contenidos</a>',
                    $id
                );
                
                // Combinar: Botón Contenidos + Acciones base
                $nestedData['action'] = $contentButton . ' ' . $baseActions;

                
                
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
            "data" => $data,
        ];
    }
}