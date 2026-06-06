<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Services\ModuleSidebarConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModuleVisibilityController extends Controller
{
    public function __construct(private ModuleSidebarConfigService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->svc->listAll();
        return response()->json([
            'data'  => $items->values(),
            'total' => $items->count(),
        ]);
    }

    public function show(string $moduleKey): JsonResponse
    {
        $item = $this->svc->get($moduleKey);
        if (!$item) {
            return response()->json(['message' => 'Módulo no encontrado.'], 404);
        }
        return response()->json($item);
    }

    public function update(Request $request, string $moduleKey): JsonResponse
    {
        $validated = $request->validate([
            'show_in_sidebar'           => ['sometimes', 'boolean'],
            'sidebar_location'          => ['sometimes', Rule::in(['direct', 'sub_item'])],
            'sidebar_parent'            => [
                'sometimes', 'nullable',
                Rule::requiredIf(fn () => $request->input('sidebar_location') === 'sub_item'),
                Rule::exists('module_sidebar_config', 'module_key'),
            ],
            'sidebar_section'           => ['sometimes', Rule::in(['menu', 'modulos'])],
            'sidebar_position'          => ['sometimes', 'integer', 'min:1'],
            'sidebar_icon'              => ['sometimes', 'nullable', 'string', 'max:80'],
            'sidebar_label'             => ['sometimes', 'required', 'string', 'max:100'],
            'sidebar_url'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'config_moved'              => ['sometimes', 'boolean'],
            'admin_section'             => [
                'sometimes', 'nullable', 'string', 'max:80',
                Rule::requiredIf(fn () => $request->boolean('show_in_sidebar') === false),
            ],
            'configuracion_subsection'  => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::requiredIf(fn () => $request->boolean('config_moved') || $request->boolean('show_in_sidebar') === false),
            ],
        ]);

        try {
            $item = $this->svc->save($moduleKey, $validated);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json($item);
    }
}
