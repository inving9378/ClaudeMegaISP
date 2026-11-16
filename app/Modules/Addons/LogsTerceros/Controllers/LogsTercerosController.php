<?php

namespace App\Modules\Addons\LogsTerceros\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\LogsTerceros\Models\ArchivoLog;
use App\Modules\Addons\LogsTerceros\Models\GrupoLog;
use App\Modules\Addons\LogsTerceros\Services\ContrasenaRootIncorrecta;
use App\Modules\Addons\LogsTerceros\Services\LectorComoRoot;
use App\Modules\Addons\LogsTerceros\Services\LectorLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Administración → Logs de terceros. Ver LectorLogs (qué rutas se aceptan; solo lectura).
 */
class LogsTercerosController extends Controller
{
    /** Contraseñas equivocadas para leer como root: después de tantas, se espera. */
    private const INTENTOS_ROOT = 5;

    private const ESPERA_ROOT = 600;

    public function __construct(private LectorLogs $lector, private LectorComoRoot $root)
    {
    }

    public function index()
    {
        return view('addon-logs-terceros::index', [
            'puedeGestionar' => (bool) Auth::user()?->can('logs_terceros.gestionar'),
        ]);
    }

    public function datos(): JsonResponse
    {
        $grupos = GrupoLog::with('archivos')->orderBy('nombre')->get()->map(fn (GrupoLog $g) => [
            'id'          => $g->id,
            'nombre'      => $g->nombre,
            'descripcion' => $g->descripcion,
            'archivos'    => $g->archivos->map(fn (ArchivoLog $a) => $this->archivoJson($a))->values(),
        ]);

        return response()->json([
            'grupos'          => $grupos,
            'puede_gestionar' => (bool) Auth::user()?->can('logs_terceros.gestionar'),
            'sugerencias'     => $this->sugerencias(),
            // Para los que necesitan root: de quién se pide la contraseña (null = no hace falta).
            'root'            => $this->root->via(),
        ]);
    }

    public function contenido(Request $request, ArchivoLog $archivo): JsonResponse
    {
        $datos = $request->validate([
            'hasta'    => 'nullable|integer|min:0',
            'despues'  => 'nullable|integer|min:0',
            'completo' => 'nullable|boolean',
        ]);
        try {
            $r = $this->lector->leer(
                $archivo->ruta,
                hasta: isset($datos['hasta']) ? (int) $datos['hasta'] : null,
                despues: isset($datos['despues']) ? (int) $datos['despues'] : null,
                completo: (bool) ($datos['completo'] ?? false),
            );
        } catch (\LengthException $e) {
            return response()->json(['error' => $e->getMessage()], 413);
        } catch (\RuntimeException $e) {
            // Solo por permisos: la página ofrece leerlo como root.
            $permiso = $this->lector->info($archivo->ruta)['permiso'];

            return response()->json(['error' => $e->getMessage()] + ($permiso ? ['necesita_root' => true, 'root' => $this->root->via()] : []), 422);
        }

        return response()->json($r + ['tamano_txt' => $this->lector->legible($r['tamano'])]);
    }

    /** Como contenido(), pero como root con la contraseña que se escribió en la página. */
    public function contenidoRoot(Request $request, ArchivoLog $archivo): JsonResponse
    {
        $datos = $request->validate([
            'password' => 'nullable|string|max:512',
            'hasta'    => 'nullable|integer|min:0',
            'despues'  => 'nullable|integer|min:0',
            'completo' => 'nullable|boolean',
        ]);
        if ($espera = $this->esperaRoot()) {
            return $espera;
        }
        try {
            $r = $this->root->leer(
                $archivo->ruta,
                $datos['password'] ?? null,
                hasta: isset($datos['hasta']) ? (int) $datos['hasta'] : null,
                despues: isset($datos['despues']) ? (int) $datos['despues'] : null,
                completo: (bool) ($datos['completo'] ?? false),
            );
        } catch (ContrasenaRootIncorrecta $e) {
            return $this->contrasenaMala($e);
        } catch (\LengthException $e) {
            return response()->json(['error' => $e->getMessage()], 413);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        RateLimiter::clear($this->llaveRoot());
        // «Seguir» pide cada 3 s: solo se registra al abrir, recargar o traer más.
        if (! isset($datos['despues'])) {
            $this->auditar('Archivo leído como root', $archivo);
        }

        return response()->json($r + ['tamano_txt' => $this->lector->legible($r['tamano']), 'como_root' => true]);
    }

    public function descargarRoot(Request $request, ArchivoLog $archivo): BinaryFileResponse|JsonResponse
    {
        $datos = $request->validate(['password' => 'nullable|string|max:512']);
        if ($espera = $this->esperaRoot()) {
            return $espera;
        }
        try {
            $copia = $this->root->copiar($archivo->ruta, $datos['password'] ?? null);
        } catch (ContrasenaRootIncorrecta $e) {
            return $this->contrasenaMala($e);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], $e instanceof \LengthException ? 413 : 422);
        }
        RateLimiter::clear($this->llaveRoot());
        $this->auditar('Archivo descargado como root', $archivo);
        $nombre = basename($this->lector->info($archivo->ruta)['actual'] ?? $archivo->ruta);

        return response()->download($copia, str_contains($nombre, '*') ? 'archivo' : $nombre, [
            'Content-Type'  => 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend();
    }

    private function llaveRoot(): string
    {
        return 'logs-terceros-root:' . Auth::id();
    }

    private function esperaRoot(): ?JsonResponse
    {
        if (! RateLimiter::tooManyAttempts($this->llaveRoot(), self::INTENTOS_ROOT)) {
            return null;
        }
        $min = (int) ceil(RateLimiter::availableIn($this->llaveRoot()) / 60);

        return response()->json(['error' => "Demasiadas contraseñas equivocadas. Espera {$min} min y vuelve a intentar."], 429);
    }

    private function contrasenaMala(ContrasenaRootIncorrecta $e): JsonResponse
    {
        if (! $e->falta) {
            RateLimiter::hit($this->llaveRoot(), self::ESPERA_ROOT);
        }

        return response()->json(['error' => $e->getMessage(), 'password_incorrecta' => true, 'root' => $this->root->via()], 422);
    }

    public function descargar(ArchivoLog $archivo): BinaryFileResponse|JsonResponse
    {
        $info = $this->lector->info($archivo->ruta);
        if (! $info['legible']) {
            return response()->json(['error' => $info['motivo'] ?? 'No se puede leer.'], 422);
        }
        $this->auditar('Log de terceros descargado', $archivo);

        return response()->download($info['real'], basename($info['real']), [
            'Content-Type'  => 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function guardarGrupo(Request $request, ?GrupoLog $grupo = null): JsonResponse
    {
        $datos = $request->validate([
            'nombre'      => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:500',
        ]);
        $nuevo = ! $grupo;
        $grupo ??= new GrupoLog();
        $grupo->fill(['nombre' => trim($datos['nombre']), 'descripcion' => trim((string) ($datos['descripcion'] ?? '')) ?: null])->save();
        $this->auditar($nuevo ? 'Grupo de logs creado' : 'Grupo de logs cambiado', $grupo);

        return response()->json(['ok' => true, 'id' => $grupo->id]);
    }

    public function borrarGrupo(GrupoLog $grupo): JsonResponse
    {
        $this->auditar('Grupo de logs quitado (con sus ' . $grupo->archivos()->count() . ' archivos)', $grupo);
        $grupo->delete();

        return response()->json(['ok' => true]);
    }

    public function guardarArchivo(Request $request, ?ArchivoLog $archivo = null): JsonResponse
    {
        $datos = $request->validate([
            'grupo_id' => 'required|integer|exists:logs_terceros_grupos,id',
            'nombre'   => 'required|string|max:120',
            'ruta'     => 'required|string|max:1024',
        ]);
        $ruta = trim($datos['ruta']);
        if ($motivo = $this->lector->rechazo($ruta)) {
            return response()->json(['message' => $motivo, 'errors' => ['ruta' => [$motivo]]], 422);
        }
        $nuevo = ! $archivo;
        $archivo ??= new ArchivoLog();
        $archivo->fill(['grupo_id' => (int) $datos['grupo_id'], 'nombre' => trim($datos['nombre']), 'ruta' => $ruta])->save();
        $this->auditar($nuevo ? 'Log de terceros agregado' : 'Log de terceros cambiado', $archivo);

        return response()->json(['ok' => true, 'archivo' => $this->archivoJson($archivo)]);
    }

    public function borrarArchivo(ArchivoLog $archivo): JsonResponse
    {
        $this->auditar('Log de terceros quitado de la lista (el archivo no se toca)', $archivo);
        $archivo->delete();

        return response()->json(['ok' => true]);
    }

    private function archivoJson(ArchivoLog $a): array
    {
        $info = $this->lector->info($a->ruta);

        return [
            'id'         => $a->id,
            'grupo_id'   => $a->grupo_id,
            'nombre'     => $a->nombre,
            'ruta'       => $a->ruta,
            'actual'     => $info['actual'],
            'legible'    => $info['legible'],
            'permiso'    => $info['permiso'],
            'motivo'     => $info['motivo'],
            'tamano_txt' => $info['tamano'] !== null ? $this->lector->legible($info['tamano']) : null,
            'modificado' => $info['modificado'],
        ];
    }

    /** Logs que suelen interesar y que existen en este servidor (para no escribir la ruta de memoria). */
    private function sugerencias(): array
    {
        $candidatas = [
            '/var/log/asterisk/messages.log' => 'Central telefónica (Asterisk)',
            '/var/log/asterisk/full'         => 'Asterisk (todo)',
            '/var/log/asterisk/security.log' => 'Asterisk (seguridad)',
            '/var/log/asterisk/queue_log'    => 'Colas de Asterisk',
            '/var/log/nginx/error.log'       => 'nginx (errores)',
            '/var/log/nginx/access.log'      => 'nginx (accesos)',
            '/var/log/apache2/error.log'     => 'Apache (errores)',
            '/var/log/mysql/error.log'       => 'MySQL (errores)',
            '/var/log/syslog'                => 'Sistema (syslog)',
            '/var/log/php8.2-fpm.log'        => 'PHP-FPM',
        ];
        // Los diarios (laravel-2026-11-16.log) se sugieren como patrón: siempre el del día.
        foreach (glob(storage_path('logs/*.log')) ?: [] as $f) {
            if (preg_match('/^(.+)-\d{4}-\d{2}-\d{2}\.log$/', basename($f), $m)) {
                $candidatas[dirname($f) . "/{$m[1]}-*.log"] = "MegaISP · {$m[1]} (el más reciente)";
            } else {
                $candidatas[$f] = 'MegaISP · ' . basename($f);
            }
        }
        $ya = ArchivoLog::pluck('ruta')->flip();
        $out = [];
        foreach ($candidatas as $ruta => $nombre) {
            $info = $this->lector->info($ruta);
            if (isset($ya[$ruta]) || ! $info['existe']) {
                continue;
            }
            $out[] = ['ruta' => $ruta, 'nombre' => $nombre, 'legible' => $info['legible']];
        }

        return array_slice($out, 0, 30);
    }

    private function auditar(string $descripcion, GrupoLog|ArchivoLog $objeto): void
    {
        try {
            activity('logs-terceros')
                ->causedBy(Auth::user())
                ->performedOn($objeto)
                ->withProperties(array_filter([
                    'nombre' => $objeto->nombre,
                    'ruta'   => $objeto instanceof ArchivoLog ? $objeto->ruta : null,
                ]))
                ->log($descripcion);
        } catch (\Throwable $e) {
            Log::warning('[logs-terceros] no se pudo registrar en activity_log: ' . $e->getMessage());
        }
    }
}
