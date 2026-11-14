<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Modules\Addons\Marketing\Jobs\GenerarEstrategiaCampanaJob;
use App\Modules\Addons\Marketing\Jobs\GenerarPiezasCampanaJob;
use App\Modules\Addons\Marketing\Requests\ActualizarEstrategiaRequest;
use App\Modules\Addons\Marketing\Requests\GenerarCampana360Request;
use App\Modules\Addons\Marketing\Services\Campanas360\Campana360Presenter;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use App\Modules\Addons\Marketing\Services\Campanas360\ClienteIACampana;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorEstrategia;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorPiezas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Campañas 360 (Fase 2): generar con IA (asíncrono, cola `default`), progreso para sondeo y edición de la estrategia.
 * Una generación a la vez por campaña (candado de fila + 409). Ver §2.4.7–§2.4.9 de docs/marketing/fase-2-paquetes.md.
 */
class Campana360GeneracionController extends Controller
{
    public function __construct(
        private readonly Campana360Presenter $presenter,
        private readonly ClienteIACampana $ia,
        private readonly GeneradorPiezas $piezas,
        private readonly GeneradorEstrategia $estrategia,
    ) {}

    /** 7) POST /api/marketing/campanas-360/{id}/generar */
    public function generar(GenerarCampana360Request $request, int $id): JsonResponse
    {
        $campana = Campana360Controller::buscar($id);

        if (! $campana) {
            return Campana360Controller::noEncontrada();
        }

        if (in_array($campana->status, ['finished', 'canceled'], true)) {
            return Campana360Controller::negocio('campana_cerrada', 'La campaña está cerrada: ya no se puede generar.', 409);
        }

        // F-1: el borrador puede guardarse incompleto, pero para gastar IA la campaña debe estar completa.
        $faltantes = Campana360Presenter::faltantes($campana);
        if ($faltantes !== []) {
            return Campana360Controller::negocio('campana_incompleta', Campana360Presenter::mensajeFaltantes($faltantes), 422, ['faltantes' => $faltantes]);
        }

        $alcance    = $request->alcance();
        $reemplazar = $request->reemplazar();

        $claves = match ($alcance) {
            'estrategia' => [ClienteIACampana::ESTRATEGIA],
            'piezas'     => [ClienteIACampana::PIEZAS],
            default      => [ClienteIACampana::ESTRATEGIA, ClienteIACampana::PIEZAS],
        };
        foreach ($claves as $clave) {
            if (! $this->ia->configurada($clave)) {
                return Campana360Controller::negocio('ia_no_configurada', GeneradorEstrategia::mensajeIANoConfigurada($clave), 422);
            }
        }

        $resultado = DB::transaction(function () use ($campana, $alcance, $reemplazar) {
            /** @var Campaign $c */
            $c = Campaign::query()->lockForUpdate()->find($campana->id);

            if (in_array($c->status, ['finished', 'canceled'], true)) {
                return Campana360Controller::negocio('campana_cerrada', 'La campaña está cerrada: ya no se puede generar.', 409);
            }

            $filas = $this->presenter->filas($c)->whereIn('pieza', CatalogosCampana360::PIEZAS_TEXTO);

            if ($this->presenter->generacion($c, $filas)['estado'] === 'generando') {
                return Campana360Controller::negocio('generando', 'Ya se está generando esta campaña.', 409);
            }

            if ($alcance !== 'estrategia' && $filas->isNotEmpty() && ! $reemplazar) {
                return Campana360Controller::negocio('piezas_existentes', 'Ya hay piezas. Confirma que quieres reemplazarlas.', 409);
            }

            if ($alcance === 'piezas' && $c->estrategia_estado !== 'lista') {
                return Campana360Controller::negocio('sin_estrategia', 'Primero genera la estrategia de la campaña.', 422);
            }

            if ($alcance !== 'estrategia' && $reemplazar) {
                $this->piezas->borrarPiezas($c);
            }

            if ($alcance === 'piezas') {
                return ['tipos' => $this->piezas->crearFilas($c)];
            }

            $c->forceFill(['estrategia_estado' => 'generando', 'estrategia_error' => null])->save();

            return ['tipos' => []];
        });

        if ($resultado instanceof JsonResponse) {
            return $resultado;
        }

        // Fuera de la transacción: con la cola `sync` (pruebas) el trabajo corre aquí mismo y debe ver lo ya guardado.
        if ($alcance === 'piezas') {
            foreach ($resultado['tipos'] as $tipo) {
                GenerarPiezasCampanaJob::dispatch($campana->id, $tipo)->onQueue('default');
            }
        } else {
            GenerarEstrategiaCampanaJob::dispatch($campana->id, $alcance)->onQueue('default');
        }

        $fresca = $campana->fresh();

        return response()->json([
            'ok'         => true,
            'generacion' => $this->presenter->generacion($fresca, $this->presenter->filas($fresca)->whereIn('pieza', CatalogosCampana360::PIEZAS_TEXTO)),
        ], 202);
    }

    /** 8) GET /api/marketing/campanas-360/{id}/progreso */
    public function progreso(int $id): JsonResponse
    {
        $campana = Campana360Controller::buscar($id);

        if (! $campana) {
            return Campana360Controller::noEncontrada();
        }

        return response()->json($this->presenter->progreso($campana));
    }

    /** 9) PUT /api/marketing/campanas-360/{id}/estrategia */
    public function actualizarEstrategia(ActualizarEstrategiaRequest $request, int $id): JsonResponse
    {
        $campana = Campana360Controller::buscar($id);

        if (! $campana) {
            return Campana360Controller::noEncontrada();
        }

        if ($campana->estrategia_estado === 'generando') {
            return Campana360Controller::negocio('generando', 'Ya se está generando esta campaña.', 409);
        }

        $v = $this->estrategia->validar((array) $request->validated('estrategia'), $campana);

        if (! $v['ok']) {
            return response()->json([
                'message' => reset($v['errores']),
                'errors'  => array_map(fn ($m) => [$m], $v['errores']),
            ], 422);
        }

        $campana->forceFill([
            'estrategia'             => $v['estrategia'],
            'estrategia_estado'      => 'lista',
            'estrategia_error'       => null,
            'estrategia_generada_at' => $campana->estrategia_generada_at ?? now(),
        ])->save();

        return response()->json(['ok' => true, 'data' => $this->presenter->campana($campana->fresh())]);
    }
}
