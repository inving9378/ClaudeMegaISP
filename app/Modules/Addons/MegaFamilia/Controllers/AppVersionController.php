<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\AppVersion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AppVersionController extends Controller
{
    /**
     * Endpoint público para que la app móvil verifique si hay actualización.
     * Recibe platform + current_version_code; responde con la versión activa
     * más reciente y si el cliente debería actualizarse.
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform'             => 'required|in:android,ios',
            'current_version_code' => 'sometimes|integer|min:0',
        ]);

        $latest = AppVersion::query()
            ->where('platform', $data['platform'])
            ->where('active', true)
            ->orderByDesc('version_code')
            ->first();

        if (!$latest) {
            return response()->json(['has_update' => false]);
        }

        $current  = (int) ($data['current_version_code'] ?? 0);
        $hasUpdate = $latest->version_code > $current;

        return response()->json([
            'has_update'   => $hasUpdate,
            'version_name' => $latest->version_name,
            'version_code' => $latest->version_code,
            'is_mandatory' => (bool) $latest->is_mandatory,
            'changelog'    => $latest->changelog,
            'download_url' => url('/api/megafamilia/mobile/download/' . $latest->id),
            'file_size'    => (int) $latest->file_size,
            'released_at'  => optional($latest->released_at)->toDateString(),
        ]);
    }

    /**
     * Descarga del APK/IPA. Incrementa download_count y entrega el archivo
     * con los headers que reconoce Android para iniciar la instalación.
     */
    public function download(int $id): BinaryFileResponse|Response
    {
        $v = AppVersion::findOrFail($id);
        if (!$v->active) {
            return response('Esta versión ya no está disponible.', 410);
        }

        $full = public_path($v->file_path);
        if (!file_exists($full)) {
            return response('Archivo no encontrado en el servidor.', 404);
        }

        $v->increment('download_count');

        $ext      = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime     = $ext === 'ipa'
            ? 'application/octet-stream'
            : 'application/vnd.android.package-archive';
        $filename = 'megafamilia-v' . str_replace(['.', ' '], ['_', '_'], $v->version_name) . '.' . $ext;

        return response()->download($full, $filename, [
            'Content-Type'        => $mime,
            'Content-Length'      => (string) filesize($full),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // ---------- Admin ----------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $list = AppVersion::query()
            ->when($request->platform, fn ($q, $v) => $q->where('platform', $v))
            ->orderByDesc('platform')
            ->orderByDesc('version_code')
            ->get();

        $current = AppVersion::query()
            ->where('platform', 'android')->where('active', true)
            ->orderByDesc('version_code')->first();

        return response()->json(['list' => $list, 'current' => $current]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform'      => 'required|in:android,ios',
            'version_name'  => 'required|string|max:32',
            'version_code'  => 'required|integer|min:1',
            'is_mandatory'  => 'sometimes|boolean',
            'min_version_code' => 'sometimes|nullable|integer|min:0',
            'changelog'     => 'sometimes|nullable|string|max:5000',
            'file'          => 'required|file|max:204800', // 200 MB
        ]);

        $file = $request->file('file');
        $ext  = $data['platform'] === 'ios' ? 'ipa' : 'apk';

        $safeName = 'megafamilia-v' . str_replace(['.', ' '], ['_', '_'], $data['version_name']) . '.' . $ext;
        $targetDir = public_path('downloads');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file->move($targetDir, $safeName);
        $relPath = 'downloads/' . $safeName;

        // Garantizar unicidad por (platform, version_code) — si existe, reemplaza
        $existing = AppVersion::where('platform', $data['platform'])
            ->where('version_code', $data['version_code'])
            ->first();

        if ($existing) {
            $existing->update([
                'version_name'    => $data['version_name'],
                'file_path'       => $relPath,
                'file_size'       => filesize($targetDir . '/' . $safeName),
                'changelog'       => $data['changelog'] ?? $existing->changelog,
                'is_mandatory'    => (bool) ($data['is_mandatory'] ?? false),
                'min_version_code'=> $data['min_version_code'] ?? null,
                'active'          => true,
                'released_at'     => Carbon::now(),
            ]);
            $version = $existing->fresh();
        } else {
            $version = AppVersion::create([
                'platform'        => $data['platform'],
                'version_name'    => $data['version_name'],
                'version_code'    => $data['version_code'],
                'file_path'       => $relPath,
                'file_size'       => filesize($targetDir . '/' . $safeName),
                'changelog'       => $data['changelog'] ?? null,
                'is_mandatory'    => (bool) ($data['is_mandatory'] ?? false),
                'min_version_code'=> $data['min_version_code'] ?? null,
                'active'          => true,
                'released_at'     => Carbon::now(),
            ]);
        }

        return response()->json(['success' => true, 'version' => $version]);
    }

    /**
     * Marca esta versión como activa y desactiva las otras de su plataforma.
     */
    public function activate(int $id): JsonResponse
    {
        $v = AppVersion::findOrFail($id);
        AppVersion::where('platform', $v->platform)->where('id', '!=', $v->id)->update(['active' => false]);
        $v->update(['active' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $v = AppVersion::findOrFail($id);
        $full = public_path($v->file_path);
        if (file_exists($full)) @unlink($full);
        $v->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Página pública de descarga (Blade standalone). No requiere auth.
     */
    public function downloadPage()
    {
        $version = AppVersion::query()
            ->where('platform', 'android')
            ->where('active', true)
            ->orderByDesc('version_code')
            ->first();

        $downloadUrl = $version
            ? url('/api/megafamilia/mobile/download/' . $version->id)
            : null;

        return view('addon-megafamilia::descargar.index', compact('version', 'downloadUrl'));
    }
}
