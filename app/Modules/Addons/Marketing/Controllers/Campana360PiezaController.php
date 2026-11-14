<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Jobs\RegenerarPiezaCampanaJob;
use App\Modules\Addons\Marketing\Requests\ActualizarPiezaRequest;
use App\Modules\Addons\Marketing\Requests\ProducirVideoRequest;
use App\Modules\Addons\Marketing\Requests\RegenerarPiezaRequest;
use App\Modules\Addons\Marketing\Services\Campanas360\Campana360Presenter;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use App\Modules\Addons\Marketing\Services\Campanas360\ClienteIACampana;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorEstrategia;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorPiezas;
use App\Modules\Addons\Marketing\Services\Campanas360\ProductorVideoCampana;
use App\Modules\Addons\Marketing\Services\Campanas360\ValidadorPiezas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Campañas 360 (Fase 2): editar, regenerar (solo esa pieza) y producir en Video MVM. `{piezaId}` debe pertenecer a
 * `{id}` (si no, 404). Ver §2.4.10–§2.4.12 de docs/marketing/fase-2-paquetes.md.
 */
class Campana360PiezaController extends Controller
{
    public function __construct(
        private readonly Campana360Presenter $presenter,
        private readonly ValidadorPiezas $validador,
        private readonly GeneradorPiezas $generador,
        private readonly ClienteIACampana $ia,
        private readonly ProductorVideoCampana $productor,
    ) {}

    /** 10) PUT /api/marketing/campanas-360/{id}/piezas/{piezaId} */
    public function actualizar(ActualizarPiezaRequest $request, int $id, int $piezaId): JsonResponse
    {
        [$campana, $pieza] = $this->buscar($id, $piezaId);
        if (! $pieza) {
            return self::piezaNoEncontrada($campana);
        }

        if (! in_array($pieza->pieza, CatalogosCampana360::PIEZAS_TEXTO, true)) {
            return Campana360Controller::negocio('no_editable', 'Los videos producidos no se editan: edita su guion y vuelve a producir.', 422);
        }

        if (in_array($pieza->status, ['pending', 'generating'], true)) {
            return Campana360Controller::negocio('generando', 'Esta pieza se está generando; espera a que termine.', 409);
        }

        $contenido = (array) $request->validated('contenido');
        if ($pieza->pieza === 'mensaje_whatsapp' && ! isset($contenido['uso'])) {
            $contenido['uso'] = $pieza->contenido['uso'] ?? (CatalogosCampana360::USOS_WHATSAPP[$pieza->variante] ?? null);
        }

        $v = $this->validador->validar($pieza->pieza, $contenido);
        if (! $v['ok']) {
            return response()->json([
                'message' => reset($v['errores']),
                'errors'  => array_map(fn ($m) => [$m], $v['errores']),
            ], 422);
        }

        $this->generador->guardarContenido($pieza, $v['contenido'], null, true, true);

        return response()->json(['ok' => true, 'data' => $this->presenter->pieza($pieza->fresh()), 'avisos' => $v['avisos']]);
    }

    /** 11) POST /api/marketing/campanas-360/{id}/piezas/{piezaId}/regenerar */
    public function regenerar(RegenerarPiezaRequest $request, int $id, int $piezaId): JsonResponse
    {
        [$campana, $pieza] = $this->buscar($id, $piezaId);
        if (! $pieza) {
            return self::piezaNoEncontrada($campana);
        }

        if (! in_array($pieza->pieza, CatalogosCampana360::PIEZAS_TEXTO, true)) {
            return Campana360Controller::negocio('no_editable', 'Los videos producidos no se regeneran: regenera su guion y vuelve a producir.', 422);
        }

        if (in_array($campana->status, ['finished', 'canceled'], true)) {
            return Campana360Controller::negocio('campana_cerrada', 'La campaña está cerrada: ya no se puede generar.', 409);
        }

        if (! $this->ia->configurada(ClienteIACampana::PIEZAS)) {
            return Campana360Controller::negocio('ia_no_configurada', GeneradorEstrategia::mensajeIANoConfigurada(ClienteIACampana::PIEZAS), 422);
        }

        // Paso atómico: solo una regeneración a la vez (doble clic = una sola).
        $tomada = GeneratedContent::query()
            ->whereKey($pieza->id)
            ->whereNotIn('status', ['pending', 'generating'])
            ->update([
                'status'         => 'generating',
                'failure_reason' => null,
                'regeneraciones' => DB::raw('regeneraciones + 1'),
                'updated_at'     => now(),
            ]);

        if ($tomada === 0) {
            return Campana360Controller::negocio('generando', 'Esta pieza ya se está generando.', 409);
        }

        $respuesta = $this->presenter->pieza($pieza->fresh());

        RegenerarPiezaCampanaJob::dispatch($pieza->id, $request->indicaciones())->onQueue('default');

        return response()->json(['ok' => true, 'data' => $respuesta], 202);
    }

    /** 12) POST /api/marketing/campanas-360/{id}/piezas/{piezaId}/producir */
    public function producir(ProducirVideoRequest $request, int $id, int $piezaId): JsonResponse
    {
        [$campana, $pieza] = $this->buscar($id, $piezaId);
        if (! $pieza) {
            return self::piezaNoEncontrada($campana);
        }

        $r = $this->productor->producir($campana, $pieza, $request->validated('voz'), $request->validated('musica'));

        if (! $r['ok']) {
            return Campana360Controller::negocio($r['error'], $r['mensaje'], $r['http'], $r['extra'] ?? []);
        }

        $video = $r['video']->fresh() ?? $r['video'];

        return response()->json(['ok' => true, 'data' => $this->presenter->video($video, (int) $campana->id)], 202);
    }

    /** @return array{0: ?Campaign, 1: ?GeneratedContent} */
    private function buscar(int $id, int $piezaId): array
    {
        $campana = Campana360Controller::buscar($id);
        if (! $campana) {
            return [null, null];
        }

        $pieza = GeneratedContent::query()
            ->whereKey($piezaId)
            ->where('source_campaign_id', $campana->id)
            ->whereNotNull('pieza')
            ->first();

        return [$campana, $pieza];
    }

    private static function piezaNoEncontrada(?Campaign $campana): JsonResponse
    {
        return $campana
            ? Campana360Controller::negocio('no_encontrado', 'No encontramos esa pieza en esta campaña.', 404)
            : Campana360Controller::noEncontrada();
    }
}
