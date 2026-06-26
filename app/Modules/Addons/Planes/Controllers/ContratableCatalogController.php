<?php

namespace App\Modules\Addons\Planes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contratable\ContratableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD del catálogo de Servicios Contratables (Planes → Servicios contratables).
 *
 * Gestiona contratable_services + sus contratable_packages (paquetes por rango).
 * NO toca facturación (eso es el motor F2, gateado). El módulo se elige de una LISTA
 * FIJA → la métrica nunca es texto libre (conteoActual() siempre recibe algo válido).
 * Los precios se capturan IVA INCLUIDO (consistente con el resto del sistema).
 */
class ContratableCatalogController extends Controller
{
    /** Lista FIJA de módulos contratables → su métrica. NO texto libre. */
    public const MODULOS = [
        ['key' => 'flotas',      'label' => 'Flotas',      'metrica' => ContratableService::METRICA_VEHICULOS],
        ['key' => 'megafamilia', 'label' => 'MegaFamilia', 'metrica' => ContratableService::METRICA_DISPOSITIVOS],
    ];

    /** Página (Vue list). */
    public function index()
    {
        return view('meganet.module.planes.contratables.index', [
            'notifications' => method_exists($this, 'userNotification') ? $this->userNotification() : [],
        ]);
    }

    /** Formulario de alta/edición (Vue form). */
    public function form($id = null)
    {
        return view('meganet.module.planes.contratables.form', ['id' => $id]);
    }

    /** Lista FIJA de módulos para el dropdown del formulario. */
    public function modulos(): JsonResponse
    {
        return response()->json(['modulos' => self::MODULOS]);
    }

    /** JSON consumido por la lista Vue: servicios + sus paquetes. */
    public function data(): JsonResponse
    {
        $services = ContratableService::with(['packages' => fn ($q) => $q->orderBy('rango_min')])
            ->orderBy('nombre')
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'module_key'     => $s->module_key,
                'nombre'         => $s->nombre,
                'descripcion'    => $s->descripcion,
                'metrica'        => $s->metrica,
                'activo'         => (bool) $s->activo,
                'meses_prueba'   => (int) $s->meses_prueba,
                'aplica_iva'     => (bool) $s->aplica_iva,
                'iva_porcentaje' => (float) $s->iva_porcentaje,
                'packages'       => $s->packages->map(fn ($p) => [
                    'id'        => $p->id,
                    'nombre'    => $p->nombre,
                    'rango_min' => (int) $p->rango_min,
                    'rango_max' => $p->rango_max === null ? null : (int) $p->rango_max,
                    'precio'    => (float) $p->precio,
                    'orden'     => (int) $p->orden,
                ])->values(),
            ]);

        return response()->json(['services' => $services]);
    }

    /** Detalle de un servicio (para precargar el formulario de edición). */
    public function show($id): JsonResponse
    {
        $s = ContratableService::with(['packages' => fn ($q) => $q->orderBy('rango_min')])->findOrFail($id);

        return response()->json(['service' => $s]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, null);

        $service = DB::transaction(function () use ($data) {
            $service = ContratableService::create($data['service']);
            $this->syncPackages($service, $data['packages']);

            return $service;
        });

        return response()->json(['success' => true, 'id' => $service->id], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $service = ContratableService::findOrFail($id);
        $data = $this->validatePayload($request, $service);

        DB::transaction(function () use ($service, $data) {
            // module_key/metrica son INMUTABLES en edición (cambiarlos rompería el conteo).
            unset($data['service']['module_key'], $data['service']['metrica']);
            $service->update($data['service']);
            $this->syncPackages($service, $data['packages']);
        });

        return response()->json(['success' => true, 'id' => $service->id]);
    }

    public function destroy($id): JsonResponse
    {
        ContratableService::findOrFail($id)->delete(); // packages caen por FK cascade
        return response()->json(['success' => true]);
    }

    // ───────────────────────── Validación ─────────────────────────

    /**
     * Valida y normaliza el payload. La métrica SIEMPRE se deriva del módulo (lista
     * fija), nunca del cliente. Lanza ValidationException (422) con mensajes claros.
     */
    private function validatePayload(Request $request, ?ContratableService $existing): array
    {
        $validated = $request->validate([
            'module_key'              => ['required', 'string'],
            'nombre'                  => ['required', 'string', 'max:255'],
            'descripcion'             => ['nullable', 'string'],
            'activo'                  => ['boolean'],
            'meses_prueba'            => ['required', 'integer', 'min:0', 'max:60'],
            'aplica_iva'              => ['boolean'],
            'iva_porcentaje'          => ['required', 'numeric', 'min:0', 'max:100'],
            'packages'                => ['required', 'array', 'min:1'],
            'packages.*.nombre'       => ['required', 'string', 'max:255'],
            'packages.*.rango_min'    => ['required', 'integer', 'min:1'],
            'packages.*.rango_max'    => ['nullable', 'integer', 'min:1'],
            'packages.*.precio'       => ['required', 'numeric', 'min:0'],
            'packages.*.orden'        => ['nullable', 'integer', 'min:0'],
        ]);

        // Módulo válido de la lista FIJA → métrica derivada (nunca texto libre).
        $modulo = collect(self::MODULOS)->firstWhere('key', $validated['module_key']);
        if (! $modulo) {
            throw ValidationException::withMessages(['module_key' => 'Módulo no válido. Elige uno de la lista.']);
        }

        // module_key único por servicio (un servicio por módulo), salvo en su propia edición.
        $dup = ContratableService::where('module_key', $validated['module_key'])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($dup && ! $existing) {
            throw ValidationException::withMessages(['module_key' => "Ya existe un servicio contratable para «{$modulo['label']}»."]);
        }

        $this->validatePackageRanges($validated['packages']);

        return [
            'service' => [
                'module_key'     => $modulo['key'],
                'metrica'        => $modulo['metrica'], // derivada, autoritativa
                'nombre'         => $validated['nombre'],
                'descripcion'    => $validated['descripcion'] ?? null,
                'activo'         => (bool) ($validated['activo'] ?? true),
                'meses_prueba'   => (int) $validated['meses_prueba'],
                'aplica_iva'     => (bool) ($validated['aplica_iva'] ?? true),
                'iva_porcentaje' => (float) $validated['iva_porcentaje'],
            ],
            'packages' => $validated['packages'],
        ];
    }

    /**
     * Reglas DURAS de rangos dentro de un mismo servicio:
     *  - sin solape entre rangos.
     *  - máximo UN paquete con rango_max abierto (NULL) y debe ser el de mayor rango_min.
     *  - rango_max (si existe) >= rango_min; rango_min únicos.
     */
    private function validatePackageRanges(array $packages): void
    {
        // rango_max >= rango_min
        foreach ($packages as $i => $p) {
            $max = $p['rango_max'] ?? null;
            if ($max !== null && (int) $max < (int) $p['rango_min']) {
                throw ValidationException::withMessages([
                    "packages.$i.rango_max" => 'El rango máximo no puede ser menor que el mínimo.',
                ]);
            }
        }

        // máximo un abierto (rango_max NULL)
        $abiertos = array_values(array_filter($packages, fn ($p) => ($p['rango_max'] ?? null) === null));
        if (count($abiertos) > 1) {
            throw ValidationException::withMessages([
                'packages' => 'Solo un paquete puede quedar sin tope superior (rango máximo vacío).',
            ]);
        }

        // ordenar por rango_min para validar solape; el abierto debe ser el último
        $sorted = $packages;
        usort($sorted, fn ($a, $b) => $a['rango_min'] <=> $b['rango_min']);

        $prevMax = null; // tope del paquete anterior
        foreach ($sorted as $idx => $p) {
            $min = (int) $p['rango_min'];
            $max = $p['rango_max'] ?? null;

            if ($prevMax === null && $idx > 0) {
                // el anterior era abierto pero no es el último → abierto no es el de mayor rango
                throw ValidationException::withMessages([
                    'packages' => 'El paquete sin tope superior debe ser el de mayor rango (el último).',
                ]);
            }
            if ($idx > 0 && $min <= $prevMax) {
                throw ValidationException::withMessages([
                    'packages' => "Los rangos se solapan (un paquete empieza en $min y el anterior llega a $prevMax).",
                ]);
            }

            $prevMax = $max === null ? null : (int) $max;
        }
    }

    private function syncPackages(ContratableService $service, array $packages): void
    {
        $service->packages()->delete(); // catálogo simple: se reescriben los paquetes del servicio
        foreach (array_values($packages) as $orden => $p) {
            $service->packages()->create([
                'nombre'    => $p['nombre'],
                'rango_min' => (int) $p['rango_min'],
                'rango_max' => ($p['rango_max'] ?? null) === null ? null : (int) $p['rango_max'],
                'precio'    => (float) $p['precio'],
                'orden'     => (int) ($p['orden'] ?? $orden + 1),
            ]);
        }
    }
}
