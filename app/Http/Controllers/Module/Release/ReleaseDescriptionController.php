<?php

namespace App\Http\Controllers\Module\Release;

use App\Http\Controllers\Controller;
use App\Models\ReleaseDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReleaseDescriptionController extends Controller
{

    public function index($releaseId)
    {
        $descriptions = ReleaseDescription::where('release_id', $releaseId)
            ->orderByDesc('id')
            ->get();

        return response()->json($descriptions);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'release_id' => ['required', 'exists:releases,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $data = $validator->validated();
            $data['created_by'] = auth()->user()->id;
            $model = ReleaseDescription::create($data);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Descripción agregada correctamente.',
                'model' => $model
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Ocurrio un error al procesar la solicitud.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $model = ReleaseDescription::find($id);

        if (!$model) {
            return response()->json(['success' => false, 'message' => 'Descripción no encontrada.'], 404);
        }
        try {
            $validator = Validator::make($request->all(), [
                'title' => ['nullable', 'string', 'max:255'],
                'description' => ['required', 'string'],
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
                'message' => 'Descripción actualizada correctamente.',
                'model' => $model
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Ocurrio un error al procesar la solicitud.'], 500);
        }
    }

    public function destroy($id)
    {
        $model = ReleaseDescription::findOrFail($id);
        $model->delete();

        return response()->json([
            'success' => true,
            'message' => 'Descripción eliminada correctamente.'
        ]);
    }
}
