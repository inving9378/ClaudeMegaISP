<?php

namespace App\Http\Controllers\Module\Administration\Documentation\DocumentationMenu;

use App\Http\Controllers\Base\CrudModalController;
use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\documentation\DocumentationMenuDatatableHelper;
use App\Http\Requests\module\administration\Documentation\DocumentationMenuCreateRequest;
use Illuminate\Http\Request;

class DocumentationMenuController extends CrudModalController
{
    public function __construct(DocumentationMenuDatatableHelper $helper)
    {
        parent::__construct($helper, new DocumentationMenuCreateRequest());
        $this->data['model'] = 'App\Models\DocumentationMenu';
        $this->data['url'] = 'meganet.module.administration.documentation.menu';
        $this->data['module'] = 'DocumentationMenu';
    }

    public function getById($id)
    {
        try {
            $menu = $this->data['model']::find($id);
            
            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro el menú de documentación',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha obtenido con éxito.',
                'model' => $menu
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    /**
     * Obtener título de un menú por ID (para mostrar en filtros)
     */
    public function getTitle($id)
    {
        $menu = $this->data['model']::find($id);
        if (!$menu) {
            return response()->json(['error' => 'Menú no encontrado'], 404);
        }
        
        return response()->json([
            'title' => $menu->title
        ]);
    }

    /**
     * Obtener árbol completo: Menús con sus Submenús para el dropdown
     */
    public function getTree()
    {
        try {
            $menus = \App\Models\DocumentationMenu::with(['submenus' => function($query) {
                    $query->select('id', 'documentation_menu_id', 'title', 'description')
                            ->orderBy('title');
                }])
                ->select('id', 'title')
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la documentación'
            ], 500);
        }
    }
}
