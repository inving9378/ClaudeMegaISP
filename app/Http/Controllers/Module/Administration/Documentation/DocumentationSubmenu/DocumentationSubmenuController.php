<?php

namespace App\Http\Controllers\Module\Administration\Documentation\DocumentationSubmenu;

use App\Http\Controllers\Base\CrudModalController;
use App\Http\Controllers\Controller;
use App\Models\DocumentationSubmenu;
use App\Models\DocumentationContent;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\administration\documentation\DocumentationSubmenuDatatableHelper;
use App\Http\Requests\module\administration\Documentation\DocumentationSubmenuCreateRequest;

class DocumentationSubmenuController extends CrudModalController
{
    public function __construct(DocumentationSubmenuDatatableHelper $helper)
    {
        parent::__construct($helper, new DocumentationSubmenuCreateRequest());
        $this->data['model'] = 'App\Models\DocumentationSubmenu';
        $this->data['url'] = 'meganet.module.administration.documentation.submenu';
        $this->data['module'] = 'DocumentationSubmenu';
        
    }

    /**
     * Mostrar contenidos de un submenú específico
     */
    public function show($id)
    {
        $submenu = DocumentationSubmenu::with('menu')->findOrFail($id);
        $contents = DocumentationContent::where('documentation_submenu_id', $id)
            ->orderByDesc('id')
            ->get();

        // return view('modules.documentation.submenu.show', compact('submenu', 'contents'));
        return view('meganet.module.administration.documentation.content.show', compact('submenu', 'contents'));
    }
}