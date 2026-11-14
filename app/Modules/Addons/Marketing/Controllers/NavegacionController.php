<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GET /api/marketing/navegacion (Fase 1, §2.4.3 de docs/marketing/fase-1-paquetes.md).
 *
 * La barra de navegación del módulo sale de `module.json` → `menu[0].children`, EN SU ORDEN (reordenar el
 * menú = editar module.json). Un item (y cada `sub`) se incluye si el rol de la persona en la empresa
 * activa está en su `roles_empresa` y, si trae `permission`, la persona lo tiene. Los `sub` heredan los
 * `roles_empresa` de su item. Solo devuelve clave, etiqueta, url, icono y sub: nada de permisos ni datos
 * del usuario. Sin empresa activa responde el middleware (403 `sin_empresa`).
 */
class NavegacionController extends Controller
{
    public function __construct(private readonly EmpresaActiva $contexto)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rol = (string) $this->contexto->rol();
        $usuario = $request->user();

        $items = [];
        foreach (self::menu() as $item) {
            $roles = (array) ($item['roles_empresa'] ?? []);
            if (! $this->visible($item, $roles, $rol, $usuario)) {
                continue;
            }

            $clave = (string) ($item['clave'] ?? Str::slug((string) ($item['label'] ?? '')));

            $sub = [];
            foreach ((array) ($item['sub'] ?? []) as $hijo) {
                if (! is_array($hijo) || ! $this->visible($hijo, $hijo['roles_empresa'] ?? $roles, $rol, $usuario)) {
                    continue;
                }
                $sub[] = [
                    'clave'    => (string) ($hijo['clave'] ?? $clave . '-' . Str::slug((string) ($hijo['label'] ?? ''))),
                    'etiqueta' => (string) ($hijo['label'] ?? ''),
                    'url'      => (string) ($hijo['url'] ?? ''),
                ];
            }

            $items[] = [
                'clave'    => $clave,
                'etiqueta' => (string) ($item['label'] ?? ''),
                'url'      => (string) ($item['url'] ?? ''),
                'icono'    => (string) ($item['icono_material'] ?? ''),
                'sub'      => $sub,
            ];
        }

        return response()->json(['ok' => true, 'rol' => $rol, 'items' => $items]);
    }

    /** @return array<int, array<string, mixed>> `menu[0].children` de module.json */
    public static function menu(): array
    {
        $manifiesto = json_decode((string) file_get_contents(dirname(__DIR__) . '/module.json'), true);

        $hijos = $manifiesto['menu'][0]['children'] ?? [];

        return is_array($hijos) ? array_values(array_filter($hijos, 'is_array')) : [];
    }

    private function visible(array $item, array $roles, string $rol, $usuario): bool
    {
        if (! in_array($rol, $roles, true)) {
            return false;
        }

        $permiso = $item['permission'] ?? null;

        return $permiso === null || $permiso === '' || ($usuario && $usuario->can($permiso));
    }
}
