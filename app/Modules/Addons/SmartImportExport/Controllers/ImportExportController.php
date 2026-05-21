<?php

namespace App\Modules\Addons\SmartImportExport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\SmartImportExport\Jobs\SmartImportJob;
use App\Modules\Addons\SmartImportExport\Models\ImportExportLog;
use App\Modules\Addons\SmartImportExport\Services\SmartExportService;
use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlador unificado del addon Smart Import/Export.
 *
 * URLs:
 *   /configuracion/smart-import/*          → flujo de importación
 *   /configuracion/smart-export/*          → flujo de exportación
 *   /configuracion/smart-import-export/*   → bitácora unificada + descargas persistentes
 */
class ImportExportController extends Controller
{
    public function __construct(
        protected SmartImportService $importService,
        protected SmartExportService $exportService,
    ) {
        $this->data['url'] = 'meganet.module.setting';
        $this->data['module'] = 'Configuracion';
    }

    /* ============================================================
     |  IMPORT
     * ============================================================ */

    public function importIndex()
    {
        $this->data['notifications'] = $this->userNotification();
        return view('addon-smart-import-export::smart-import', $this->data);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:sql,json,xlsx,xls,csv,zip', 'max:102400'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        $log = ImportExportLog::create([
            'type'       => 'import',
            'filename'   => $file->getClientOriginalName(),
            'format'     => strtolower($file->getClientOriginalExtension()),
            'status'     => 'pending',
            'admin_user' => $this->resolveAdminUser(),
        ]);

        try {
            $analysis = $this->importService->analyzeFile($file);
        } catch (Throwable $e) {
            $log->markFailed('Error al analizar archivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'log_id'  => $log->id,
            ], 422);
        }

        Cache::put($this->cacheKey($analysis['token']), $analysis, now()->addHours(6));

        $log->update([
            'ai_analysis' => [
                'token'      => $analysis['token'],
                'format'     => $analysis['format'],
                'report'     => $analysis['report'],
                'total_rows' => $analysis['total_rows'],
            ],
        ]);

        return response()->json([
            'success'    => true,
            'log_id'     => $log->id,
            'token'      => $analysis['token'],
            'format'     => $analysis['format'],
            'report'     => $analysis['report'],
            'total_rows' => $analysis['total_rows'],
        ]);
    }

    public function preview(Request $request)
    {
        $token = (string) $request->input('token', '');
        $analysis = Cache::get($this->cacheKey($token));
        if (!$analysis) {
            return response()->json(['success' => false, 'message' => 'Token inválido o expirado.'], 404);
        }

        $conflicts = $this->importService->detectConflicts($analysis['datasets']);
        $aiRecommendations = null;
        if ($request->boolean('with_ai') && !empty($conflicts)) {
            $aiRecommendations = $this->importService->resolveWithAI($conflicts);
        }

        return response()->json([
            'success'            => true,
            'token'              => $token,
            'report'             => $analysis['report'],
            'conflicts'          => $conflicts,
            'ai_recommendations' => $aiRecommendations,
        ]);
    }

    public function execute(Request $request)
    {
        $token = (string) $request->input('token', '');
        $analysis = Cache::get($this->cacheKey($token));
        if (!$analysis) {
            return response()->json(['success' => false, 'message' => 'Token inválido o expirado.'], 404);
        }

        $options = $request->input('options', []);
        if (!is_array($options)) {
            $options = [];
        }

        $log = ImportExportLog::where('type', 'import')
            ->where('status', 'pending')
            ->whereJsonContains('ai_analysis->token', $token)
            ->latest('id')
            ->first();

        if (!$log) {
            $log = ImportExportLog::create([
                'type'       => 'import',
                'filename'   => $analysis['file'] ?? 'desconocido',
                'format'     => $analysis['format'] ?? null,
                'status'     => 'pending',
                'admin_user' => $this->resolveAdminUser(),
            ]);
        }

        $jobId = (string) Str::uuid();
        SmartImportJob::setStatus($jobId, [
            'state'    => 'queued',
            'progress' => 0,
            'log'      => ['Importación encolada.'],
            'tables'   => array_keys($analysis['datasets']),
        ]);

        $log->markRunning($jobId);

        SmartImportJob::dispatch(
            $jobId,
            $analysis['datasets'],
            $options,
            auth()->id(),
            $log->id,
        );

        $this->importService->cleanup($analysis['file']);
        Cache::forget($this->cacheKey($token));

        return response()->json([
            'success' => true,
            'job_id'  => $jobId,
            'log_id'  => $log->id,
        ]);
    }

    public function status(string $jobId)
    {
        return response()->json([
            'success' => true,
            'job_id'  => $jobId,
            'status'  => SmartImportJob::getStatus($jobId),
        ]);
    }

    /* ============================================================
     |  EXPORT
     * ============================================================ */

    public function exportIndex()
    {
        $this->data['notifications'] = $this->userNotification();
        return view('addon-smart-import-export::smart-export', $this->data);
    }

    public function modules()
    {
        return response()->json([
            'success' => true,
            'modules' => $this->exportService->getModulesWithCount(),
        ]);
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modules'         => ['required', 'array', 'min:1'],
            'modules.*'       => ['string'],
            'format'          => ['required', 'in:sql,json,xlsx'],
            'strip_sensitive' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $log = ImportExportLog::create([
            'type'             => 'export',
            'filename'         => 'pending',
            'format'           => $request->input('format'),
            'status'           => 'running',
            'modules_selected' => $request->input('modules'),
            'admin_user'       => $this->resolveAdminUser(),
        ]);

        try {
            $result = $this->exportService->generate(
                $request->input('modules'),
                $request->input('format'),
                $request->boolean('strip_sensitive', true),
            );
        } catch (Throwable $e) {
            $log->markFailed($e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $absolutePath = storage_path(SmartExportService::STORAGE_DIR . '/' . $result['filename']);
        $log->markCompleted([
            'filename'    => $result['filename'],
            'output_path' => $absolutePath,
        ]);

        return response()->json([
            'success'      => true,
            'log_id'       => $log->id,
            'token'        => $result['token'],
            'filename'     => $result['filename'],
            'format'       => $result['format'],
            'size'         => $result['size'],
            'modules'      => $result['modules'],
            'download_url' => url('/configuracion/smart-export/download/' . $result['token']),
        ]);
    }

    public function download(string $token)
    {
        $filename = $this->exportService->resolveToken($token);
        if (!$filename) {
            abort(404, 'Token inválido o expirado.');
        }
        $path = storage_path(SmartExportService::STORAGE_DIR . '/' . $filename);
        if (!file_exists($path)) {
            abort(404, 'Archivo no disponible.');
        }
        $this->exportService->consumeToken($token);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    /* ============================================================
     |  HISTORIAL UNIFICADO
     * ============================================================ */

    public function historyIndex()
    {
        $this->data['notifications'] = $this->userNotification();
        return view('addon-smart-import-export::import-export-history', $this->data);
    }

    public function history(Request $request)
    {
        $type  = $request->input('type');
        $limit = min(200, max(1, (int) $request->input('limit', 50)));

        $query = ImportExportLog::query()->latest('id');
        if (in_array($type, ['import', 'export'], true)) {
            $query->where('type', $type);
        }

        return response()->json([
            'success' => true,
            'logs'    => $query->take($limit)->get(),
        ]);
    }

    public function downloadFromLog(int $id)
    {
        $log = ImportExportLog::where('type', 'export')
            ->where('status', 'completed')
            ->findOrFail($id);

        if (!$log->output_path || !file_exists($log->output_path)) {
            abort(410, 'El archivo ya no está disponible. Genere el export de nuevo.');
        }

        return response()->download($log->output_path, $log->filename ?? basename($log->output_path));
    }

    public function destroyLog(int $id)
    {
        $log = ImportExportLog::findOrFail($id);

        if ($log->output_path && file_exists($log->output_path)) {
            @unlink($log->output_path);
        }

        $log->delete();
        return response()->json(['success' => true]);
    }

    /* ============================================================
     |  Helpers
     * ============================================================ */

    private function cacheKey(string $token): string
    {
        return 'smart_import:analysis:' . $token;
    }

    private function resolveAdminUser(): string
    {
        return auth()->user()->login_user
            ?? auth()->user()->email
            ?? 'admin';
    }
}
