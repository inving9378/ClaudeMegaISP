<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contexto de empresa de la persona (selector de empresa del módulo).
 *  GET  /api/marketing/contexto                 → empresa activa, sus empresas y capacidades
 *  POST /api/marketing/contexto/empresa-activa  → cambia la empresa activa (validada contra sus asignaciones)
 */
class ContextoEmpresaController extends Controller
{
    public function __construct(private readonly EmpresaActiva $contexto)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function cambiar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'company_id' => ['required', 'integer', 'min:1'],
        ], [
            'company_id.required' => 'Elige la empresa.',
            'company_id.integer'  => 'La empresa elegida no es válida.',
            'company_id.min'      => 'La empresa elegida no es válida.',
        ]);

        // company_id llega del request solo como ELECCIÓN: debe ser una asignación activa de la
        // persona en una empresa activa; si no, 404 (no se revela si la empresa existe).
        $asignacion = ResolverEmpresaActiva::asignacionesActivas((int) $request->user()->getKey())
            ->firstWhere('company_id', (int) $datos['company_id']);

        if (! $asignacion) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_encontrado',
                'mensaje' => 'No se encontró esa empresa entre las tuyas.',
            ], 404);
        }

        $request->session()->put(ResolverEmpresaActiva::CLAVE_SESION, $asignacion->company_id);
        $this->contexto->establecer($asignacion->empresa, $asignacion->rol);

        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $usuario = $request->user();
        $empresa = $this->contexto->empresa();
        $rol = $this->contexto->rol();

        $empresas = ResolverEmpresaActiva::asignacionesActivas((int) $usuario->getKey())
            ->map(fn (EmpresaUsuario $a) => [
                'id'             => (int) $a->company_id,
                'nombre'         => $a->empresa->nombre,
                'slug'           => $a->empresa->slug,
                'color_primario' => $a->empresa->color_primario,
                'rol'            => $a->rol,
            ])
            ->values()
            ->all();

        return [
            'ok'      => true,
            'usuario' => [
                'id'     => (int) $usuario->getKey(),
                'nombre' => self::nombreDe($usuario),
            ],
            'empresa_activa' => $empresa ? [
                'id'               => (int) $empresa->getKey(),
                'nombre'           => $empresa->nombre,
                'slug'             => $empresa->slug,
                'color_primario'   => $empresa->color_primario,
                'color_secundario' => $empresa->color_secundario,
                'rol'              => $rol,
            ] : null,
            'empresas'    => $empresas,
            'capacidades' => [
                'gestionar_empresas'       => $rol === 'admin' && $usuario->can('marketing.empresas.gestionar'),
                'editar_config_compartida' => $this->contexto->id() === Empresa::PRINCIPAL_ID
                    && in_array($rol, ['admin', 'marketing'], true),
            ],
        ];
    }

    /**
     * Nombre para mostrar: nombre + apellidos, sin espacios dobles.
     */
    public static function nombreDe(object $usuario): string
    {
        $partes = [$usuario->name ?? '', $usuario->father_last_name ?? '', $usuario->mother_last_name ?? ''];

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $partes)));
    }
}
