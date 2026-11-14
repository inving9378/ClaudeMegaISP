<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Piezas de una Campaña 360 con la IA (`marketing.piezas_campana`, §2.5.3 y §2.5.5): una llamada por TIPO de pieza
 * (todas sus variantes juntas) y una por variante al regenerar. Cada `contenido` pasa por ValidadorPiezas; si algo no
 * cumple, UN reintento con los errores; lo que vuelva a fallar queda `failed` con mensaje en español. Un tipo que
 * falla no afecta a los demás. Nunca se reintenta solo (cada llamada cuesta).
 */
class GeneradorPiezas
{
    public const MAX_TOKENS = 3500;

    public const MAX_VERSIONES = 5;

    public function __construct(
        private readonly ClienteIACampana $ia,
        private readonly PromptsCampana360 $prompts,
        private readonly ValidadorPiezas $validador,
    ) {}

    /**
     * Crea las filas `pending` de §2.5.3 según los canales. Devuelve los tipos de pieza creados.
     *
     * @return array<int,string>
     */
    public function crearFilas(Campaign $c): array
    {
        $tipos = [];

        foreach (CatalogosCampana360::piezasDeCanales($c->canales ?? []) as $pieza => $variantes) {
            foreach ($variantes as $variante) {
                $fila = new GeneratedContent();
                $fila->forceFill([
                    'company_id'          => Empresa::PRINCIPAL_ID,
                    'type'                => 'copy',
                    'source_campaign_id'  => $c->id,
                    'pieza'               => $pieza,
                    'variante'            => $variante,
                    'status'              => 'pending',
                    'generation_engine'   => 'ia',
                    'generation_cost_usd' => 0,
                    'generation_metadata' => ['versiones' => []],
                    'regeneraciones'      => 0,
                ])->save();
            }
            $tipos[] = $pieza;
        }

        return $tipos;
    }

    /** Borrado lógico de las piezas de texto (los videos se quedan). */
    public function borrarPiezas(Campaign $c): void
    {
        GeneratedContent::query()
            ->where('source_campaign_id', $c->id)
            ->whereIn('pieza', CatalogosCampana360::PIEZAS_TEXTO)
            ->get()
            ->each->delete();
    }

    /** Genera todas las variantes de un tipo de pieza (una llamada a la IA, más un reintento si hace falta). */
    public function generarTipo(Campaign $c, string $pieza, ?array $soloVariantes = null): void
    {
        $filas = GeneratedContent::query()
            ->where('source_campaign_id', $c->id)
            ->where('pieza', $pieza)
            ->whereIn('status', ['pending', 'generating'])
            ->when($soloVariantes !== null, fn ($q) => $q->whereIn('variante', $soloVariantes))
            ->orderBy('variante')
            ->get();

        if ($filas->isEmpty()) {
            return;
        }

        GeneratedContent::query()->whereIn('id', $filas->pluck('id'))->update(['status' => 'generating', 'updated_at' => now()]);

        $variantes = $filas->pluck('variante')->all();

        try {
            $resultado = $this->pedir($c, $pieza, $variantes);
        } catch (IANoConfigurada) {
            $this->fallar($filas, GeneradorEstrategia::mensajeIANoConfigurada(ClienteIACampana::PIEZAS));

            return;
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('Campañas 360: falló la generación de piezas', ['campana_id' => $c->id, 'pieza' => $pieza, 'error' => $e->getMessage()]);
            $this->fallar($filas, 'No se pudo generar esta pieza (la IA no respondió). Intenta regenerarla.');

            return;
        }

        foreach ($filas as $fila) {
            $ok = $resultado['validas'][$fila->variante] ?? null;
            if ($ok === null) {
                $this->fallarConDetalle($fila, 'La IA no devolvió esta variante válida dos veces. Regenérala o edítala a mano.', $resultado['intentos']);
                continue;
            }
            $this->guardarContenido($fila->fresh(), $ok, $resultado['modelo'], false);
        }
    }

    /** Regenera UNA variante (con las otras como contexto para no repetirlas). Las demás piezas no cambian. */
    public function regenerar(GeneratedContent $fila, string $indicaciones = ''): void
    {
        $c = Campaign::query()->find($fila->source_campaign_id);
        if (! $c) {
            $this->fallar(collect([$fila]), 'La campaña ya no existe.');

            return;
        }

        $otras = GeneratedContent::query()
            ->where('source_campaign_id', $c->id)
            ->where('pieza', $fila->pieza)
            ->where('id', '!=', $fila->id)
            ->whereNotNull('contenido')
            ->get()
            ->map(fn ($o) => ['variante' => $o->variante, 'contenido' => $o->contenido])
            ->values()
            ->all();

        try {
            $resultado = $this->pedir($c, $fila->pieza, [$fila->variante], $otras, $indicaciones);
        } catch (IANoConfigurada) {
            $this->fallar(collect([$fila]), GeneradorEstrategia::mensajeIANoConfigurada(ClienteIACampana::PIEZAS));

            return;
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('Campañas 360: falló la regeneración', ['pieza_id' => $fila->id, 'error' => $e->getMessage()]);
            $this->fallar(collect([$fila]), 'No se pudo regenerar esta pieza (la IA no respondió). Intenta de nuevo.');

            return;
        }

        $ok = $resultado['validas'][$fila->variante] ?? null;
        if ($ok === null) {
            $this->fallarConDetalle($fila, 'La IA no devolvió una versión válida dos veces. Intenta de nuevo o edítala a mano.', $resultado['intentos']);

            return;
        }

        $this->guardarContenido($fila->fresh(), $ok, $resultado['modelo'], true);
    }

    /**
     * Guarda un contenido ya validado: versión previa a `generation_metadata.versiones` (máx. 5), texto plano, estado lista.
     */
    public function guardarContenido(GeneratedContent $fila, array $contenido, ?string $modelo, bool $versionar, bool $edicion = false): void
    {
        $meta = $fila->generation_metadata ?? [];
        $versiones = $meta['versiones'] ?? [];

        if ($versionar && is_array($fila->contenido)) {
            array_unshift($versiones, [
                'contenido'  => $fila->contenido,
                'guardada_at' => now()->toIso8601String(),
                'origen'     => $fila->editado_at ? 'edicion' : 'ia',
            ]);
            $versiones = array_slice($versiones, 0, self::MAX_VERSIONES);
        }
        $meta['versiones'] = $versiones;

        $datos = [
            'contenido'           => $contenido,
            'output_text'         => ValidadorPiezas::textoPlano($fila->pieza, $contenido),
            'status'              => 'ready',
            'failure_reason'      => null,
            'generation_metadata' => $meta,
            'generated_at'        => $edicion ? $fila->generated_at : now(),
        ];

        if ($modelo !== null) {
            $datos['generation_engine'] = mb_substr('ia:' . ($modelo !== '' ? $modelo : 'desconocido'), 0, 50);
        }
        if ($edicion) {
            $datos['editado_at'] = now();
        }

        $fila->forceFill($datos)->save();
    }

    /**
     * Pide a la IA las variantes de un tipo y las valida (con un reintento si alguna no cumple). Devuelve también, por
     * intento, los errores de validación (campo → mensaje; nunca el texto de la IA) y si la respuesta se cortó por el
     * tope de tokens, para registrar POR QUÉ falló una pieza.
     *
     * @return array{validas: array<string,array>, modelo: string, intentos: array<int,array{errores: array, cortada: bool}>}
     */
    private function pedir(Campaign $c, string $pieza, array $variantes, array $otras = [], string $indicaciones = ''): array
    {
        $mensaje = $this->prompts->usuarioPiezas($c, $pieza, $variantes, $otras, $indicaciones);
        $sistema = $this->prompts->sistemaPiezas();

        $r = $this->ia->enviar(ClienteIACampana::PIEZAS, $mensaje, $sistema, self::MAX_TOKENS);
        [$validas, $errores] = $this->validarRespuesta($r['texto'], $pieza, $variantes);
        $modelo = $r['modelo'];
        $intentos = [['errores' => $errores, 'cortada' => (bool) ($r['cortada'] ?? false)]];

        if ($errores !== []) {
            $nota = ($r['cortada'] ?? false)
                ? "\n\nTu respuesta anterior se CORTÓ por largo. Responde completo y más breve (menos texto por campo)."
                : '';
            $reintento = $mensaje . "\n\nTU RESPUESTA ANTERIOR:\n" . mb_substr($r['texto'], 0, 8000) . "\n\n" . $this->prompts->reintento($errores) . $nota;
            $r2 = $this->ia->enviar(ClienteIACampana::PIEZAS, $reintento, $sistema, self::MAX_TOKENS);
            [$validas2, $errores2] = $this->validarRespuesta($r2['texto'], $pieza, $variantes);
            $validas = $validas2 + $validas;
            $modelo = $r2['modelo'] !== '' ? $r2['modelo'] : $modelo;
            $intentos[] = ['errores' => $errores2, 'cortada' => (bool) ($r2['cortada'] ?? false)];
        }

        return ['validas' => $validas, 'modelo' => $modelo, 'intentos' => $intentos];
    }

    /**
     * Fila fallida con el detalle de por qué: en `generation_metadata.errores_validacion` y en el log `marketing`
     * (errores de cada intento que tocan a esta variante o a toda la respuesta; marca de «respuesta cortada»).
     */
    private function fallarConDetalle(GeneratedContent $fila, string $mensaje, array $intentos): void
    {
        $detalle = [];
        $cortada = false;
        foreach ($intentos as $n => $intento) {
            $propios = array_filter(
                $intento['errores'],
                fn ($k) => ! str_starts_with((string) $k, 'variante ') || str_starts_with((string) $k, "variante {$fila->variante}"),
                ARRAY_FILTER_USE_KEY
            );
            $detalle['intento_' . ($n + 1)] = [
                'errores'            => array_slice($propios, 0, 30, true),
                'respuesta_cortada'  => $intento['cortada'],
            ];
            $cortada = $cortada || $intento['cortada'];
        }

        $mensaje = self::motivoHumano($fila->pieza, $detalle, $cortada) ?? $mensaje;

        $meta = $fila->fresh()->generation_metadata ?? [];
        $meta['errores_validacion'] = $detalle + ['registrado_at' => now()->toIso8601String()];

        $fila->forceFill([
            'status'              => 'failed',
            'failure_reason'      => mb_substr($mensaje, 0, 500),
            'generation_metadata' => $meta,
        ])->save();

        Log::channel('marketing')->warning('Campañas 360: pieza sin contenido válido', [
            'campana_id' => $fila->source_campaign_id,
            'pieza_id'   => $fila->id,
            'pieza'      => $fila->pieza,
            'variante'   => $fila->variante,
            'detalle'    => $detalle,
        ]);
    }

    /**
     * Motivo corto en español para `failure_reason` (Henry: que se vea en pantalla por qué falló), tomado del último
     * intento: «La IA no entregó un guion válido: escena 3: máximo 18 caracteres (tiene 22). Regenéralo o edítalo a mano.»
     */
    public static function motivoHumano(string $pieza, array $detalle, bool $cortada): ?string
    {
        $esGuion = in_array($pieza, CatalogosCampana360::GUIONES, true);
        $inicio  = $esGuion ? 'La IA no entregó un guion válido' : 'La IA no entregó esta pieza válida';
        $cierre  = $esGuion ? ' Regenéralo o edítalo a mano.' : ' Regenérala o edítala a mano.';

        $ultimo  = end($detalle) ?: ['errores' => [], 'respuesta_cortada' => false];
        $motivos = [];

        if ($ultimo['respuesta_cortada'] ?? false) {
            $motivos[] = 'la respuesta llegó cortada (demasiado larga)';
        }

        foreach ($ultimo['errores'] ?? [] as $campo => $msg) {
            $campo = (string) preg_replace('/^variante [A-Z]\.?/', '', (string) $campo);
            $prefijo = preg_match('/^escenas\.(\d+)/', $campo, $m) ? 'escena ' . ((int) $m[1] + 1) . ': ' : '';
            $texto = rtrim((string) $msg, '.');
            if ($campo === 'json') {
                $texto = $cortada ? '' : 'la respuesta no fue un JSON válido';
            }
            if ($texto !== '') {
                $motivos[] = $prefijo . mb_strtolower(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
            }
            if (count($motivos) >= 2) {
                break;
            }
        }

        if ($motivos === []) {
            return null;
        }

        return mb_substr($inicio . ': ' . implode('; ', array_unique($motivos)) . '.' . $cierre, 0, 500);
    }

    /** @return array{0: array<string,array>, 1: array<string,string>} */
    private function validarRespuesta(string $texto, string $pieza, array $variantes): array
    {
        $json = IA::json($texto);
        $lista = is_array($json) ? ($json['piezas'] ?? null) : null;

        if (! is_array($lista) || ! array_is_list($lista)) {
            return [[], ['json' => 'La respuesta debe ser un objeto JSON con la lista «piezas».']];
        }

        $validas = [];
        $errores = [];

        foreach ($lista as $i => $item) {
            $variante = is_array($item) ? ($item['variante'] ?? null) : null;
            if (! is_string($variante) || ! in_array($variante, $variantes, true) || isset($validas[$variante])) {
                continue;
            }

            $contenido = $item['contenido'] ?? null;
            if ($pieza === 'mensaje_whatsapp' && is_array($contenido) && ! isset($contenido['uso'])) {
                $contenido['uso'] = CatalogosCampana360::USOS_WHATSAPP[$variante] ?? null;
            }

            $v = $this->validador->validar($pieza, $contenido);
            if ($v['ok']) {
                $validas[$variante] = $v['contenido'];
            } else {
                foreach ($v['errores'] as $campo => $msg) {
                    $errores["variante {$variante}.{$campo}"] = $msg;
                }
            }
        }

        foreach ($variantes as $v) {
            if (! isset($validas[$v]) && ! $this->tieneErrorDe($errores, $v)) {
                $errores["variante {$v}"] = 'Falta esta variante.';
            }
        }

        return [$validas, $errores];
    }

    private function tieneErrorDe(array $errores, string $variante): bool
    {
        foreach (array_keys($errores) as $k) {
            if (str_starts_with($k, "variante {$variante}")) {
                return true;
            }
        }

        return false;
    }

    private function fallar(Collection $filas, string $mensaje): void
    {
        GeneratedContent::query()->whereIn('id', $filas->pluck('id'))->update([
            'status'         => 'failed',
            'failure_reason' => mb_substr($mensaje, 0, 500),
            'updated_at'     => now(),
        ]);
    }
}
