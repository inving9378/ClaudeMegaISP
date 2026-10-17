<?php

namespace App\Modules\Addons\PortalCliente\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\AparienciaImagen;
use App\Modules\Addons\PortalCliente\Services\AparienciaConflictoException;
use App\Modules\Addons\PortalCliente\Services\AparienciaImagenService;
use App\Modules\Addons\PortalCliente\Services\AparienciaService;
use App\Modules\Addons\PortalCliente\Support\AparienciaCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Configuración → Portal Cliente. Las rutas ya exigen el permiso (can:); aquí se repite el
 * chequeo como defensa en profundidad, igual que el resto de pantallas administrativas.
 */
class AparienciaController extends Controller
{
    public function __construct(
        private AparienciaService $apariencia,
        private AparienciaImagenService $imagenes,
    ) {
    }

    public function index()
    {
        $this->exigir('ver');

        return view('addon-portal-cliente::admin.apariencia');
    }

    public function estado(Request $request): JsonResponse
    {
        $this->exigir('ver');

        return response()->json($this->apariencia->estadoPanel($request->user()));
    }

    public function versiones(): JsonResponse
    {
        $this->exigir('ver');

        return response()->json(['versiones' => $this->apariencia->versiones()]);
    }

    public function guardarBorrador(Request $request): JsonResponse
    {
        $this->exigir('editar');
        $datos = $request->validate([
            'config'   => ['required', 'array'],
            'revision' => ['required', 'integer', 'min:0'],
        ]);

        return $this->conConflicto(function () use ($datos, $request) {
            $this->apariencia->guardarBorrador($datos['config'], (int) $datos['revision'], $request->user()->id);

            return $this->respuestaEstado($request, 'Borrador guardado. Los clientes todavía no ven estos cambios.');
        });
    }

    public function descartarBorrador(Request $request): JsonResponse
    {
        $this->exigir('editar');
        $datos = $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        return $this->conConflicto(function () use ($datos, $request) {
            $this->apariencia->descartarBorrador((int) $datos['revision'], $request->user()->id);

            return $this->respuestaEstado($request, 'Borrador descartado. Se mantiene lo publicado.');
        });
    }

    public function restaurar(Request $request, int $version): JsonResponse
    {
        $this->exigir('editar');
        $datos = $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        return $this->conConflicto(function () use ($datos, $request, $version) {
            $this->apariencia->restaurar($version, (int) $datos['revision'], $request->user()->id);

            return $this->respuestaEstado($request, 'Versión copiada al borrador. Revísala en la vista previa y publícala para aplicarla.');
        });
    }

    public function publicar(Request $request): JsonResponse
    {
        $this->exigir('publicar');
        $datos = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'nota'     => ['nullable', 'string', 'max:255'],
        ]);

        return $this->conConflicto(function () use ($datos, $request) {
            $version = $this->apariencia->publicar((int) $datos['revision'], $datos['nota'] ?? null, $request->user()->id);

            return $this->respuestaEstado($request, "Versión {$version->numero} publicada. Los clientes ya ven los cambios.");
        });
    }

    public function subirImagen(Request $request): JsonResponse
    {
        $this->exigir('editar');
        $request->validate([
            'tipo'    => ['required', Rule::in(AparienciaCatalogo::TIPOS_IMAGEN)],
            'archivo' => ['required', 'file', 'max:' . (int) config('portalcliente.apariencia.peso_maximo_kb', 10240)],
        ], [
            'archivo.max'      => 'La imagen pesa demasiado: el máximo es :max KB.',
            'archivo.required' => 'Selecciona una imagen.',
            'archivo.file'     => 'No se recibió el archivo completo. Intenta de nuevo.',
        ]);

        $imagen = $this->imagenes->subir($request->file('archivo'), $request->input('tipo'), $request->user()->id);

        return response()->json([
            'message' => 'Imagen cargada. Guarda el borrador para conservarla.',
            'imagen'  => $this->apariencia->metaImagen($imagen),
        ]);
    }

    public function imagen(string $uuid)
    {
        $this->exigir('ver');
        abort_unless(Str::isUuid($uuid), 404);

        return $this->imagenes->respuesta(AparienciaImagen::where('uuid', $uuid)->firstOrFail(), false);
    }

    public function vistaPrevia(Request $request, string $pantalla)
    {
        $this->exigir('ver');
        $datos = $request->validate([
            'fuente'  => ['nullable', Rule::in(['borrador', 'publicada', 'version'])],
            'version' => ['nullable', 'required_if:fuente,version', 'integer', 'min:1'],
        ]);
        $fuente = $datos['fuente'] ?? 'borrador';
        $parametros = ['fuente' => $fuente] + ($fuente === 'version' ? ['version' => (int) $datos['version']] : []);

        // La vista previa debe verse como el portal: sin la barra de depuración de desarrollo.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $config = $this->apariencia->configParaPrevia($fuente, $datos['version'] ?? null);
        $apariencia = $this->apariencia->vista($config, $pantalla, $parametros);

        return response()
            ->view("addon-portal-cliente::auth.{$pantalla}", ['apariencia' => $apariencia])
            ->header('Cache-Control', 'no-store')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function respuestaEstado(Request $request, string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje] + $this->apariencia->estadoPanel($request->user()));
    }

    private function conConflicto(\Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (AparienciaConflictoException $e) {
            return response()->json([
                'message'   => $e->getMessage(),
                'conflicto' => ['por' => $e->por, 'fecha' => $e->fecha],
            ], 409);
        }
    }

    private function exigir(string $accion): void
    {
        abort_unless(auth()->user()?->can("portal-cliente.apariencia.{$accion}"), 403);
    }
}
