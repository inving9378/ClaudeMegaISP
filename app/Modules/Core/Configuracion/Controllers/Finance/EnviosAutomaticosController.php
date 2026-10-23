<?php

namespace App\Modules\Core\Configuracion\Controllers\Finance;

use App\Services\Finance\Billing\ColaAvisos;
use App\Services\Finance\Billing\EnviosAutomaticos;
use App\Services\Finance\Billing\InterruptorProformaAnticipada;
use App\Services\Finance\Billing\ReactivacionEnvios;
use App\Services\Finance\Invoice\ProformaAnticipada;
use App\Support\ArchivoObligatorioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Configuración › Finanzas › «Envíos automáticos». Solo super-administrador (los dos nombres del
 * rol; DESARROLLADOR no basta aunque pase el middleware de rutas). La lógica vive en
 * EnviosAutomaticos; aquí solo se valida la petición y la confirmación escrita.
 */
class EnviosAutomaticosController extends Controller
{
    public const PALABRA_REACTIVAR = 'REACTIVAR';
    public const PALABRA_DEPURAR = 'DEPURAR';
    public const PALABRA_CANCELAR = 'CANCELAR';

    public function __construct(private EnviosAutomaticos $envios, private ColaAvisos $colaAvisos, private InterruptorProformaAnticipada $proforma)
    {
    }

    public function index()
    {
        return view('core-configuracion::finance.envios-automaticos.index', [
            'puedeGestionar' => EnviosAutomaticos::puedeGestionar(auth()->user()),
        ]);
    }

    public function estado(): JsonResponse
    {
        $this->exigirPermiso();

        return response()->json($this->envios->estado());
    }

    /** Encender: lo acumulado, el ritmo y la palabra REACTIVAR. */
    public function encender(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate([
            'opcion' => ['required', Rule::in(EnviosAutomaticos::OPCIONES)],
            'ritmo' => ['required', Rule::in(ReactivacionEnvios::RITMOS)],
            'por_hora' => ['nullable', 'required_if:opcion,' . EnviosAutomaticos::LOTES_POR_HORA, 'integer',
                'min:' . EnviosAutomaticos::LOTE_MIN, 'max:' . EnviosAutomaticos::LOTE_MAX],
            'confirmacion' => ['required', Rule::in([self::PALABRA_REACTIVAR])],
        ], [
            'opcion.required' => 'Elige qué hacer con lo acumulado.',
            'ritmo.required' => 'Elige el ritmo: Prueba o Normal.',
            'confirmacion.in' => 'Escribe ' . self::PALABRA_REACTIVAR . ' para confirmar.',
        ], ['por_hora' => 'avisos por hora']);

        return $this->responder(fn () => $this->envios->encender($request->user(), $datos['opcion'], $datos['ritmo'],
            isset($datos['por_hora']) ? (int) $datos['por_hora'] : null));
    }

    /** Apagar: confirmación simple en la pantalla, sin palabra. */
    public function apagar(Request $request): JsonResponse
    {
        $this->exigirPermiso();

        return $this->responder(fn () => $this->envios->apagar($request->user()));
    }

    /** Cambiar el ritmo (Prueba ↔ Normal) con los envíos encendidos. */
    public function ritmo(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate(['ritmo' => ['required', Rule::in(ReactivacionEnvios::RITMOS)]], ['ritmo.required' => 'Elige el ritmo: Prueba o Normal.']);

        return $this->responder(fn () => $this->envios->cambiarRitmo($request->user(), $datos['ritmo']));
    }

    /** #581: encender la proforma anticipada con los días de anticipo y la palabra ENCENDER. */
    public function proformaEncender(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate([
            'dias' => 'required|integer|min:0|max:' . ProformaAnticipada::MAX_DIAS,
            'confirmacion' => ['required', Rule::in([InterruptorProformaAnticipada::PALABRA])],
        ], ['confirmacion.in' => 'Escribe ' . InterruptorProformaAnticipada::PALABRA . ' para confirmar.'], ['dias' => 'días de anticipo']);

        return $this->responder(fn () => $this->proforma->encender($request->user(), (int) $datos['dias']));
    }

    /** #581: apagar la proforma anticipada (confirmación simple, sin palabra). */
    public function proformaApagar(Request $request): JsonResponse
    {
        $this->exigirPermiso();

        return $this->responder(fn () => $this->proforma->apagar($request->user()));
    }

    /** #581: días de anticipo (valor general), encendida o apagada. */
    public function proformaDias(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate(['dias' => 'required|integer|min:0|max:' . ProformaAnticipada::MAX_DIAS], [], ['dias' => 'días de anticipo']);

        return $this->responder(fn () => $this->proforma->cambiarDias($request->user(), (int) $datos['dias'], 'Configuración › Envíos automáticos'));
    }

    public function conteoDepuracion(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate(['horas' => 'required|integer|min:1|max:' . EnviosAutomaticos::DEPURAR_MAX_HORAS]);

        return response()->json($this->envios->conteoDepuracion((int) $datos['horas']));
    }

    /** «Depurar cola»: cancela (no borra) lo que lleve más de N horas, por tipo o todo. */
    public function depurar(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate([
            'horas' => 'required|integer|min:1|max:' . EnviosAutomaticos::DEPURAR_MAX_HORAS,
            'tipo' => ['nullable', Rule::in(array_keys(EnviosAutomaticos::TIPOS))],
            'esperado' => 'required|integer|min:0',
            'confirmacion' => ['required', Rule::in([self::PALABRA_DEPURAR])],
        ], ['confirmacion.in' => 'Escribe ' . self::PALABRA_DEPURAR . ' para confirmar.'], ['horas' => 'horas']);

        try {
            return response()->json($this->envios->depurar($request->user(), (int) $datos['horas'], $datos['tipo'] ?? null, (int) $datos['esperado'])
                + ['estado' => $this->envios->estado()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'conteo' => $this->envios->conteoDepuracion((int) $datos['horas']),
                'estado' => $this->envios->estado()], 422);
        } catch (ArchivoObligatorioException $e) {
            return response()->json(['message' => $e->getMessage(), 'estado' => $this->envios->estado()], 500);
        }
    }

    /** #579: «Ver detalle de la cola». */
    public function cola()
    {
        return view('core-configuracion::finance.envios-automaticos.cola', [
            'puedeGestionar' => EnviosAutomaticos::puedeGestionar(auth()->user()),
        ]);
    }

    public function listarCola(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $request->validate($this->reglasFiltro('') + ['pagina' => 'nullable|integer|min:1']);

        return response()->json($this->colaAvisos->listar($request->only(['tipo', 'estado', 'horas', 'desde', 'hasta', 'cliente']), (int) $request->input('pagina', 1))
            + ['estados' => ColaAvisos::ESTADOS, 'tipos' => EnviosAutomaticos::TIPOS]);
    }

    public function conteoCola(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate($this->reglasSeleccion());

        try {
            return response()->json($this->colaAvisos->conteo($datos['accion'], $this->seleccion($datos)));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Cancela o reactiva lo seleccionado (filas sueltas o todos los del filtro). */
    public function aplicarCola(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate($this->reglasSeleccion() + [
            'esperado' => 'required|integer|min:0',
            'confirmacion' => 'required|string',
        ]);
        $palabra = $datos['accion'] === ColaAvisos::CANCELAR ? self::PALABRA_CANCELAR : self::PALABRA_REACTIVAR;
        if ($datos['confirmacion'] !== $palabra) {
            return response()->json(['message' => "Escribe {$palabra} para confirmar.", 'errors' => ['confirmacion' => ["Escribe {$palabra} para confirmar."]]], 422);
        }
        $seleccion = $this->seleccion($datos);

        try {
            return response()->json($this->colaAvisos->aplicar($request->user(), $datos['accion'], $seleccion, (int) $datos['esperado']));
        } catch (InvalidArgumentException $e) {
            try {
                $conteo = $this->colaAvisos->conteo($datos['accion'], $seleccion);
            } catch (InvalidArgumentException) {
                $conteo = null;
            }

            return response()->json(['message' => $e->getMessage(), 'conteo' => $conteo], 422);
        }
    }

    private function reglasFiltro(string $prefijo): array
    {
        return [
            $prefijo . 'tipo' => ['nullable', Rule::in(array_keys(EnviosAutomaticos::TIPOS))],
            $prefijo . 'estado' => ['nullable', Rule::in(array_keys(ColaAvisos::ESTADOS))],
            $prefijo . 'horas' => 'nullable|integer|min:1|max:' . ColaAvisos::MAX_HORAS,
            $prefijo . 'desde' => 'nullable|date_format:Y-m-d',
            $prefijo . 'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:' . $prefijo . 'desde',
            $prefijo . 'cliente' => 'nullable|string|max:80',
        ];
    }

    private function reglasSeleccion(): array
    {
        return [
            'accion' => ['required', Rule::in(ColaAvisos::ACCIONES)],
            'ids' => 'required_without:filtro|array|min:1|max:' . ColaAvisos::MAX_IDS,
            'ids.*' => 'integer|min:1',
            'filtro' => 'required_without:ids|array',
        ] + $this->reglasFiltro('filtro.');
    }

    /** @return array{ids: int[]}|array{filtro: array} */
    private function seleccion(array $datos): array
    {
        return isset($datos['ids']) ? ['ids' => array_map('intval', $datos['ids'])] : ['filtro' => $datos['filtro'] ?? []];
    }

    private function responder(callable $accion): JsonResponse
    {
        try {
            return response()->json($accion() + ['estado' => $this->envios->estado()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'estado' => $this->envios->estado()], 422);
        } catch (ArchivoObligatorioException $e) {
            return response()->json(['message' => $e->getMessage(), 'estado' => $this->envios->estado()], 500);
        }
    }

    private function exigirPermiso(): void
    {
        if (! EnviosAutomaticos::puedeGestionar(auth()->user())) {
            throw new AuthorizationException('Solo un super-administrador puede ver y cambiar los envíos automáticos.');
        }
    }
}
