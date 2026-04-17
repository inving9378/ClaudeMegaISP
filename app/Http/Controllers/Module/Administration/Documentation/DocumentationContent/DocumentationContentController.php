<?php

namespace App\Http\Controllers\Module\Administration\Documentation\DocumentationContent;

use App\Http\Controllers\Controller;
use App\Models\DocumentationContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentationContentController extends Controller
{
    /**
     * Listar contenidos por submenú (para API)
     */
    // public function index($submenuId)
    // {
    //     $contents = DocumentationContent::where('documentation_submenu_id', $submenuId)
    //         ->orderByDesc('id')
    //         ->get();

    //     return response()->json($contents);
    // }

    /**
     * Store - Crear nuevo contenido
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'documentation_submenu_id' => ['required', 'exists:documentation_submenus,id'],
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $data = $validator->validated();
            $data['created_by'] = auth()->user()->id;
            $model = DocumentationContent::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenido agregado correctamente.',
                'model' => $model
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.'
            ], 500);
        }
    }

    /**
     * Update - Actualizar contenido existente
     */
    public function update(Request $request, $id)
    {
        $model = DocumentationContent::find($id);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Contenido no encontrado.'
            ], 404);
        }

        try {
            $validator = Validator::make($request->all(), [
                'documentation_submenu_id' => ['required', 'exists:documentation_submenus,id'],
                'content' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();
            $data = $validator->validated();
            $data['updated_by'] = auth()->user()->id;
            $model->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenido actualizado correctamente.',
                'model' => $model
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.'
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar contenido
     */
    public function destroy($id)
    {
        try {
            $model = DocumentationContent::findOrFail($id);
            $model->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contenido eliminado correctamente.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el contenido.'
            ], 500);
        }
    }

    /**
     * Obtener contenidos de un submenú con orden configurable
     *
     * @param Request $request
     * @param int $submenuId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContents(Request $request, $submenuId)
    {
        // Validar parámetro de orden (asc o desc)
        $orderDirection = strtolower($request->get('order', 'asc'));

        // Asegurar que solo se acepten valores válidos
        if (!in_array($orderDirection, ['asc', 'desc'])) {
            $orderDirection = 'asc';
        }

        $contents = DocumentationContent::where('documentation_submenu_id', $submenuId)
            ->orderBy('created_at', $orderDirection)
            ->get();

        return response()->json($contents);
    }

    /**
     * Alternativa: Ordenar por ID
     *
     * @param Request $request
     * @param int $submenuId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContentsBySubmenuId(Request $request, $submenuId)
    {
        $orderDirection = strtolower($request->get('order', 'asc'));

        if (!in_array($orderDirection, ['asc', 'desc'])) {
            $orderDirection = 'asc';
        }

        $contents = DocumentationContent::where('documentation_submenu_id', $submenuId)
            ->orderBy('id', $orderDirection) // Orden por ID (más eficiente)
            ->get();

        return response()->json($contents);
    }
}
