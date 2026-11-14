<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\Campaign;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Log;

/**
 * Estrategia de una Campaña 360 con la IA (`marketing.campana_360`, §2.5.4). Valida la respuesta con la misma regla
 * que la edición manual (PUT estrategia); si no cumple, UN reintento con los errores; si vuelve a fallar, la campaña
 * queda con `estrategia_estado = error` y un mensaje en español. Nunca inventa contenido.
 * El reparto del presupuesto lo recalcula el backend (RepartoPresupuesto): la suma es exactamente el presupuesto.
 */
class GeneradorEstrategia
{
    public const MAX_TOKENS = 4000;

    public function __construct(
        private readonly ClienteIACampana $ia,
        private readonly PromptsCampana360 $prompts,
        private readonly RepartoPresupuesto $reparto,
    ) {}

    /** Genera y guarda. Devuelve true si la estrategia quedó lista. */
    public function generar(Campaign $c): bool
    {
        try {
            $mensaje = $this->prompts->usuarioEstrategia($c);
            $sistema = $this->prompts->sistemaEstrategia();

            $r = $this->ia->enviar(ClienteIACampana::ESTRATEGIA, $mensaje, $sistema, self::MAX_TOKENS);
            $v = $this->validarTexto($r['texto'], $c);

            if (! $v['ok']) {
                $reintento = $mensaje . "\n\nTU RESPUESTA ANTERIOR:\n" . mb_substr($r['texto'], 0, 6000) . "\n\n" . $this->prompts->reintento($v['errores']);
                $r = $this->ia->enviar(ClienteIACampana::ESTRATEGIA, $reintento, $sistema, self::MAX_TOKENS);
                $v = $this->validarTexto($r['texto'], $c);
            }

            if (! $v['ok']) {
                $this->marcarError($c, 'La IA no devolvió una estrategia válida dos veces. Intenta generar de nuevo.');

                return false;
            }

            $c->forceFill([
                'estrategia'             => $v['estrategia'],
                'estrategia_estado'      => 'lista',
                'estrategia_error'       => null,
                'estrategia_generada_at' => now(),
            ])->save();

            return true;
        } catch (IANoConfigurada) {
            $this->marcarError($c, self::mensajeIANoConfigurada(ClienteIACampana::ESTRATEGIA));

            return false;
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('Campañas 360: falló la estrategia', ['campana_id' => $c->id, 'error' => $e->getMessage()]);
            $this->marcarError($c, 'No se pudo generar la estrategia (la IA no respondió). Intenta de nuevo en unos minutos.');

            return false;
        }
    }

    public function marcarError(Campaign $c, string $mensaje): void
    {
        $c->forceFill(['estrategia_estado' => 'error', 'estrategia_error' => mb_substr($mensaje, 0, 500)])->save();
    }

    public static function mensajeIANoConfigurada(string $clave): string
    {
        // Ojo: la clave lleva punto (`marketing.campana_360`), por eso no se lee con config("ia_modulos.{$clave}").
        $nombre = IA::modulo($clave)['nombre'] ?? $clave;

        return "Falta asignar una IA a «{$nombre}» en Integraciones → Módulos IA. Pídeselo a Irving.";
    }

    /** @return array{ok: bool, estrategia: array, errores: array<string,string>} */
    private function validarTexto(string $texto, Campaign $c): array
    {
        $json = IA::json($texto);

        if ($json === null || array_is_list($json)) {
            return ['ok' => false, 'estrategia' => [], 'errores' => ['json' => 'La respuesta no es un objeto JSON válido.']];
        }

        return $this->validar($json, $c);
    }

    /**
     * Valida y normaliza una estrategia (de la IA o editada a mano).
     *
     * @return array{ok: bool, estrategia: array, errores: array<string,string>}
     */
    public function validar(array $e, Campaign $c): array
    {
        $errores = [];
        $canales = $c->canales ?? [];

        $out = [
            'resumen'         => $this->texto($e, 'resumen', 600, $errores, true),
            'propuesta_valor' => $this->texto($e, 'propuesta_valor', 300, $errores, true),
            'mensajes_clave'  => $this->listaTextos($e, 'mensajes_clave', 120, 3, 5, $errores),
            'publico_detalle' => $this->texto($e, 'publico_detalle', 600, $errores),
        ];

        $porCanal = [];
        foreach ($this->lista($e, 'por_canal', $errores) as $i => $r) {
            if (! is_array($r) || ! in_array($r['canal'] ?? null, $canales, true) || isset($porCanal[$r['canal']])) {
                continue;
            }
            $porCanal[$r['canal']] = [
                'canal'      => $r['canal'],
                'rol'        => $this->texto($r, 'rol', 60, $errores, true, "por_canal.{$i}"),
                'formato'    => $this->texto($r, 'formato', 120, $errores, false, "por_canal.{$i}"),
                'frecuencia' => $this->texto($r, 'frecuencia', 60, $errores, false, "por_canal.{$i}"),
            ];
        }
        $out['por_canal'] = array_values($porCanal);

        $calendario = [];
        foreach (array_slice($this->lista($e, 'calendario', $errores), 0, 53) as $i => $s) {
            if (! is_array($s)) {
                $errores["calendario.{$i}"] = 'Cada semana debe ser un objeto.';
                continue;
            }
            $semana = $s['semana'] ?? null;
            if (! is_int($semana) && ! (is_string($semana) && ctype_digit($semana))) {
                $errores["calendario.{$i}.semana"] = 'La semana debe ser un número entero.';
                continue;
            }
            $calendario[] = [
                'semana'   => max(1, (int) $semana),
                'acciones' => $this->listaTextos($s, 'acciones', 160, 1, 6, $errores, "calendario.{$i}"),
            ];
        }
        $out['calendario'] = $calendario;

        $out['reparto_presupuesto'] = $this->reparto->calcular($this->lista($e, 'reparto_presupuesto', $errores), $canales, (string) $c->budget);

        $kpis = [];
        foreach (array_slice($this->lista($e, 'kpis', $errores), 0, 10) as $i => $k) {
            if (! is_array($k)) {
                continue;
            }
            if (isset($k['meta']) && (is_int($k['meta']) || is_float($k['meta']))) {
                $k['meta'] = (string) $k['meta'];
            }
            $kpis[] = [
                'nombre' => $this->texto($k, 'nombre', 60, $errores, true, "kpis.{$i}"),
                'meta'   => $this->texto($k, 'meta', 60, $errores, false, "kpis.{$i}"),
            ];
        }
        $out['kpis'] = $kpis;

        $out['riesgos'] = $this->listaTextos($e, 'riesgos', 200, 0, 6, $errores);

        return ['ok' => $errores === [], 'estrategia' => $out, 'errores' => $errores];
    }

    // ── Apoyos ──────────────────────────────────────────────────────────────────────────────────

    private function lista(array $e, string $campo, array &$errores): array
    {
        $v = $e[$campo] ?? [];
        if (! is_array($v) || ($v !== [] && ! array_is_list($v))) {
            $errores[$campo] = 'Debe ser una lista.';

            return [];
        }

        return $v;
    }

    private function listaTextos(array $e, string $campo, int $tope, int $min, int $max, array &$errores, ?string $prefijo = null): array
    {
        $ruta = $prefijo ? "{$prefijo}.{$campo}" : $campo;
        $v = $e[$campo] ?? [];
        if (! is_array($v) || ($v !== [] && ! array_is_list($v))) {
            $errores[$ruta] = 'Debe ser una lista.';

            return [];
        }
        if (count($v) < $min || count($v) > $max) {
            $errores[$ruta] = "Debe tener de {$min} a {$max} elementos.";
        }

        $out = [];
        foreach (array_slice($v, 0, $max) as $i => $t) {
            $out[] = $this->texto([$i => $t], (string) $i, $tope, $errores, true, $ruta);
        }

        return $out;
    }

    private function texto(array $e, string $campo, int $tope, array &$errores, bool $requerido = false, ?string $prefijo = null): string
    {
        $ruta = $prefijo !== null ? "{$prefijo}.{$campo}" : $campo;
        $v = $e[$campo] ?? null;

        if ($v === null || $v === '') {
            if ($requerido) {
                $errores[$ruta] = 'Este campo es obligatorio.';
            }

            return '';
        }
        if (! is_string($v)) {
            $errores[$ruta] = 'Debe ser texto.';

            return '';
        }

        $limpio = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($v)) ?? '');

        if ($requerido && $limpio === '') {
            $errores[$ruta] = 'Este campo es obligatorio.';
        } elseif (mb_strlen($limpio) > $tope) {
            $errores[$ruta] = "Máximo {$tope} caracteres.";
        } elseif (str_contains($limpio, '{{') || str_contains($limpio, '}}')) {
            $errores[$ruta] = 'No puede llevar {{ }}.';
        }

        return $limpio;
    }
}
