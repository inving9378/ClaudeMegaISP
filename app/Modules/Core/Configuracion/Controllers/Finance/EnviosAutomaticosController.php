<?php

namespace App\Modules\Core\Configuracion\Controllers\Finance;

use App\Services\Finance\Billing\EnviosAutomaticos;
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
    public const PALABRA_PAUSAR = 'PAUSAR';
    public const PALABRA_REACTIVAR = 'REACTIVAR';
    public const PALABRA_DEPURAR = 'DEPURAR';

    public function __construct(private EnviosAutomaticos $envios)
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

    public function pausar(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $request->validate(['confirmacion' => ['required', Rule::in([self::PALABRA_PAUSAR])]], [
            'confirmacion.in' => 'Escribe ' . self::PALABRA_PAUSAR . ' para confirmar.',
        ]);

        return $this->responder(fn () => $this->envios->pausar($request->user()));
    }

    public function reactivar(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate([
            'opcion' => ['required', Rule::in(EnviosAutomaticos::OPCIONES)],
            'por_hora' => ['nullable', 'required_if:opcion,' . EnviosAutomaticos::LOTES_POR_HORA, 'integer',
                'min:' . EnviosAutomaticos::LOTE_MIN, 'max:' . EnviosAutomaticos::LOTE_MAX],
            'confirmacion' => ['required', Rule::in([self::PALABRA_REACTIVAR])],
        ], [
            'opcion.required' => 'Elige qué hacer con lo acumulado.',
            'confirmacion.in' => 'Escribe ' . self::PALABRA_REACTIVAR . ' para confirmar.',
        ], ['por_hora' => 'avisos por hora']);

        return $this->responder(fn () => $this->envios->reactivar($request->user(), $datos['opcion'], isset($datos['por_hora']) ? (int) $datos['por_hora'] : null));
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
