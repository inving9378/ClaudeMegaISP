<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\IA\Services\IAProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IAProveedorController extends Controller
{
    public function __construct(protected IAProveedorService $service)
    {
    }

    public function index()
    {
        $proveedores = IAProveedor::orderBy('nombre')->get()
            ->map(fn ($p) => $this->serializar($p));

        return response()->json([
            'success' => true,
            'data' => $proveedores,
            'drivers' => IAAdaptadorFactory::driversDisponibles(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validar($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['headers_personalizados'] = $this->parseJson($request->input('headers_personalizados'));
        $datos['config_extra'] = $this->parseJson($request->input('config_extra'));
        $datos['created_by'] = auth()->id();
        $datos['estado'] = empty($datos['api_key']) ? 'sin_configurar' : 'sin_configurar';

        $proveedor = IAProveedor::create($datos);

        return response()->json(['success' => true, 'data' => $this->serializar($proveedor)]);
    }

    public function update(Request $request, $id)
    {
        $proveedor = IAProveedor::findOrFail($id);

        $validator = $this->validar($request, $proveedor->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['headers_personalizados'] = $this->parseJson($request->input('headers_personalizados'));
        $datos['config_extra'] = $this->parseJson($request->input('config_extra'));
        $datos['updated_by'] = auth()->id();

        // Si el frontend manda la api_key vacía, conservamos la actual.
        if (empty($datos['api_key'])) {
            unset($datos['api_key']);
        }

        $proveedor->update($datos);

        return response()->json(['success' => true, 'data' => $this->serializar($proveedor->fresh())]);
    }

    public function destroy($id)
    {
        $proveedor = IAProveedor::findOrFail($id);
        $proveedor->delete();
        return response()->json(['success' => true]);
    }

    public function probar($id)
    {
        $proveedor = IAProveedor::findOrFail($id);
        $ok = $this->service->probarProveedor($proveedor);

        return response()->json([
            'success' => $ok,
            'data' => $this->serializar($proveedor->fresh()),
            'message' => $ok ? 'Conexión exitosa.' : ($proveedor->ultimo_error ?: 'No se pudo conectar.'),
        ]);
    }

    public function toggleActivo($id)
    {
        $proveedor = IAProveedor::findOrFail($id);
        $proveedor->update([
            'activo' => !$proveedor->activo,
            'updated_by' => auth()->id(),
        ]);
        return response()->json(['success' => true, 'data' => $this->serializar($proveedor)]);
    }

    protected function validar(Request $request, $id = null): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('ia_proveedores', 'nombre')->ignore($id),
            ],
            'driver' => ['required', Rule::in(IAAdaptadorFactory::driversDisponibles())],
            'api_key' => ['nullable', 'string'],
            'endpoint_url' => ['nullable', 'string', 'max:500'],
            'modelo_default' => ['required', 'string', 'max:120'],
            'soporta_imagenes' => ['boolean'],
            'activo' => ['boolean'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un proveedor con ese nombre.',
            'driver.required' => 'El driver es obligatorio.',
            'driver.in' => 'Driver no válido.',
            'modelo_default.required' => 'El modelo por defecto es obligatorio.',
        ]);
    }

    protected function parseJson($valor): ?array
    {
        if (is_array($valor)) return $valor;
        if (!$valor) return null;
        $decoded = json_decode((string) $valor, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function serializar(IAProveedor $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'driver' => $p->driver,
            'endpoint_url' => $p->endpoint_url,
            'modelo_default' => $p->modelo_default,
            'soporta_imagenes' => (bool) $p->soporta_imagenes,
            'headers_personalizados' => $p->headers_personalizados,
            'config_extra' => $p->config_extra,
            'activo' => (bool) $p->activo,
            'estado' => $p->estado,
            'ultimo_error' => $p->ultimo_error,
            'probado_at' => $p->probado_at,
            'tiene_api_key' => !empty($p->getRawOriginal('api_key')),
        ];
    }
}
