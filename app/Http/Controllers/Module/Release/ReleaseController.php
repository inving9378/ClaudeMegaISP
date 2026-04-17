<?php

namespace App\Http\Controllers\Module\Release;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\ReleaseDescription;
use App\Services\BackupDb\BackupDbTestService;
use App\Services\Git\GitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReleaseController extends Controller
{

    public function __construct()
    {
        $model = 'Release';
        $this->data['url'] = 'meganet.module.releases';
        $this->data['module'] = 'Release';
        $this->data['model'] = 'App\Models\\' . $model;
    }

    public function index(Request $request)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);

        // Carga solo la primera página
        $perPage = 10;
        $releases = Release::orderByDesc('release_date')->paginate($perPage);

        // Si la solicitud es AJAX, devuelve solo JSON
        if ($request->ajax()) {
            return response()->json($releases);
        }

        $this->data['releases'] = $releases->items(); // solo los datos
        $this->data['next_page_url'] = $releases->nextPageUrl();

        return view('meganet.module.releases.index', $this->data);
    }

    public function show(string $version)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $release = Release::where('version', $version)->firstOrFail();
        $descriptions = ReleaseDescription::where('release_id', $release->id)->get();
        $this->data['release'] = $release;
        $this->data['descriptions'] = $descriptions;
        return view('meganet.module.releases.show', $this->data);
    }

    public function store(Request $request)
    {
        if (!app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden crear versiones en entorno de desarrollo.',
            ], 422);
        }

        try {
            $validator = Validator::make($request->all(), [
                'version' => ['required', 'string', 'max:50', 'unique:releases,version'],
                'title' => ['nullable', 'string', 'max:255'],
                'summary' => ['nullable', 'string'],
                'release_date' => ['required', 'date'],
            ], [
                'version.required' => 'La version es obligatoria.',
                'version.unique' => 'Ya existe una version con ese nombre.',
                'title.required' => 'El titulo es obligatorio.',
                'release_date.required' => 'La fecha de lanzamiento es obligatoria.',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['created_by'] = auth()->user()->id;
            $backupDbTestService = new BackupDbTestService();
            $ok = $backupDbTestService->backup($data['version']);
            if ($ok) {
                Log::info("Backup creado exitosamente para versión {$data['version']}");
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrio un error al crear el backup de la base de datos.',
                ], 500);
                Log::warning("No se pudo crear el backup para versión {$data['version']}");
            }
            DB::beginTransaction();
            $release = Release::create($data);

            $gitService = new GitService();
            $okGit = $gitService->createGitTag($data['version']);
            if ($okGit) {
                Log::info("Tag creado exitosamente para versión {$data['version']}");
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrio un error al crear el tag de git.',
                ], 500);
                Log::warning("No se pudo crear el tag para versión {$data['version']}");
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'La version se ha creado con exito.',
                'model' => $release
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al crear la version.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $release = Release::find($id);
        if (!$release) {
            return response()->json([
                'success' => false,
                'message' => 'La version no existe.',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'version' => [
                'required',
                'string',
                'max:50',
                Rule::unique('releases', 'version')->ignore($release->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'release_date' => ['required', 'date'],
        ], [
            'version.required' => 'La versión es obligatoria.',
            'version.unique' => 'Ya existe una versión con ese nombre.',
            'release_date.required' => 'La fecha de lanzamiento es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();
            $data = $validator->validated();
            $data['updated_by'] = auth()->user()->id;
            $release->update($data);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'La versión se ha actualizado correctamente.',
                'model' => $release,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al actualizar la version.',
            ], 500);
        }
    }
}
