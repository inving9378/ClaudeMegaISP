<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAMensaje;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAUsoToken;
use Illuminate\Support\Carbon;

/**
 * Calcula el costo estimado de las llamadas a la IA y persiste el consumo
 * en ia_uso_tokens para alimentar el dashboard de uso.
 *
 * Los precios están en USD por 1 millón de tokens y son una aproximación
 * a tarifas públicas al 2026-05. Pueden ajustarse desde la UI de configuración
 * en el futuro (por ahora son constantes en código).
 */
class IAPricingService
{
    /**
     * Tarifas USD por 1M tokens [input, output] por modelo.
     * Se hace match por prefijo: la primera clave que sea prefijo del modelo gana.
     */
    protected const TARIFAS = [
        // Claude
        'claude-opus-4-7' => [15.00, 75.00],
        'claude-opus-4-6' => [15.00, 75.00],
        'claude-opus' => [15.00, 75.00],
        'claude-sonnet-4-6' => [3.00, 15.00],
        'claude-sonnet' => [3.00, 15.00],
        'claude-haiku-4-5' => [0.80, 4.00],
        'claude-haiku' => [0.80, 4.00],
        'claude-3-5-sonnet' => [3.00, 15.00],
        'claude-3-5-haiku' => [0.80, 4.00],
        'claude-3-opus' => [15.00, 75.00],
        'claude' => [3.00, 15.00],

        // OpenAI
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4-turbo' => [10.00, 30.00],
        'gpt-4' => [30.00, 60.00],
        'gpt-3.5-turbo' => [0.50, 1.50],
        'o1-mini' => [3.00, 12.00],
        'o1' => [15.00, 60.00],
        'gpt' => [2.50, 10.00],

        // Gemini
        'gemini-2.5-pro' => [1.25, 5.00],
        'gemini-2.5-flash' => [0.075, 0.30],
        'gemini-2.0-flash' => [0.075, 0.30],
        'gemini-1.5-pro' => [1.25, 5.00],
        'gemini-1.5-flash' => [0.075, 0.30],
        'gemini' => [1.25, 5.00],
    ];

    /**
     * Devuelve el costo USD estimado para una llamada dado el modelo y
     * los tokens consumidos. Si el modelo no se reconoce, costo=0 (no se infla).
     */
    public function calcularCosto(string $modelo, int $tokensInput, int $tokensOutput): float
    {
        $modeloLower = strtolower($modelo);
        $tarifa = null;

        foreach (self::TARIFAS as $clave => $valores) {
            if (str_starts_with($modeloLower, $clave)) {
                $tarifa = $valores;
                break;
            }
        }

        if ($tarifa === null) {
            return 0.0;
        }

        $costoInput = ($tokensInput / 1_000_000) * $tarifa[0];
        $costoOutput = ($tokensOutput / 1_000_000) * $tarifa[1];

        return round($costoInput + $costoOutput, 6);
    }

    /**
     * Registra un consumo en ia_uso_tokens. No lanza si falla: el registro
     * de uso es best-effort y no debe romper el flujo principal del chat.
     */
    public function registrarUso(
        IAConversacion $conversacion,
        IAMensaje $mensaje,
        IAProveedor $proveedor,
        int $tokensInput,
        int $tokensOutput,
        string $origen = 'ia'
    ): ?IAUsoToken {
        try {
            $costo = $this->calcularCosto($proveedor->modelo_default, $tokensInput, $tokensOutput);

            return IAUsoToken::create([
                'user_id' => $conversacion->user_id ?? auth()->id(),
                'ia_conversacion_id' => $conversacion->id,
                'ia_mensaje_id' => $mensaje->id,
                'ia_proveedor_id' => $proveedor->id,
                'proveedor' => $proveedor->driver,
                'modelo' => $proveedor->modelo_default,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'tokens_total' => $tokensInput + $tokensOutput,
                'costo_estimado' => $costo,
                'fecha' => Carbon::now()->toDateString(),
                'origen' => $origen,
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('IAPricingService.registrarUso falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resumen por usuario y rango de fechas: total tokens y costo.
     */
    public function resumenPorUsuario(int $userId, string $desde, string $hasta): array
    {
        $registros = IAUsoToken::query()
            ->where('user_id', $userId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->get();

        return [
            'tokens_input' => (int) $registros->sum('tokens_input'),
            'tokens_output' => (int) $registros->sum('tokens_output'),
            'tokens_total' => (int) $registros->sum('tokens_total'),
            'costo_estimado' => (float) $registros->sum('costo_estimado'),
            'llamadas' => $registros->count(),
        ];
    }

    /**
     * Agrupa el consumo por día y proveedor en un rango. Útil para gráficas.
     */
    public function porDia(int $userId, string $desde, string $hasta): array
    {
        return IAUsoToken::query()
            ->selectRaw('fecha, proveedor, SUM(tokens_total) as tokens, SUM(costo_estimado) as costo, COUNT(*) as llamadas')
            ->where('user_id', $userId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->groupBy('fecha', 'proveedor')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($r) => [
                'fecha' => (string) $r->fecha,
                'proveedor' => $r->proveedor,
                'tokens' => (int) $r->tokens,
                'costo' => round((float) $r->costo, 6),
                'llamadas' => (int) $r->llamadas,
            ])
            ->all();
    }
}
