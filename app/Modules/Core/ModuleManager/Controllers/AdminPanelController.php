<?php

namespace App\Modules\Core\ModuleManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;

class AdminPanelController extends Controller
{
    public function index()
    {
        return view('core-module-manager::admin-panel');
    }

    public function cards(): JsonResponse
    {
        return response()->json([
            'cards' => ModuleRegistry::instance()->getAdminCards(),
        ]);
    }

    public function configSections(): JsonResponse
    {
        return response()->json([
            'sections'      => ModuleRegistry::instance()->getConfigSections(),
            'sections_flat' => ModuleRegistry::instance()->getConfigSectionsFlat(),
        ]);
    }
}
