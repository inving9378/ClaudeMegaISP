<?php

namespace App\Services\Identidad;

use App\Models\Identidad\IdentidadColaboradorIdPendiente;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de resolución seller_id → colaborador_id (Fase 3b de #9990778, item
 * #9990963). Reusa TALCUAL el JOIN de `identidad:backfill-colaborador-id`
 * (BackfillColaboradorIdBridgeCommand): `talento_colaboradores.user_id = seller_id`
 * — `seller_id` en `client_main_information` es en realidad `users.id`, no
 * `sellers.id` (ver docblock de ese comando). Lo usan tanto el observer como los
 * parches raw, sin duplicar el JOIN.
 */
class ColaboradorIdResolver
{
    /** @var array<int,int>|null user_id => colaborador_id, cacheado por proceso */
    private static ?array $mapa = null;

    public static function habilitado(): bool
    {
        return (bool) config('identidad.doble_escritura_colaborador_id', false);
    }

    public static function resolve(int $sellerId): ?int
    {
        return static::mapa()[$sellerId] ?? null;
    }

    /**
     * Resuelve; si no hay match, loguea y registra la fila en la tabla de
     * auditoría de faltantes (NUNCA lanza excepción, nunca bloquea el alta/edición).
     */
    public static function resolveOrRegistrarPendiente(int $sellerId, string $tabla, ?int $registroId): ?int
    {
        $colaboradorId = static::resolve($sellerId);

        if ($colaboradorId === null) {
            Log::warning(
                "ColaboradorIdResolver: seller_id={$sellerId} sin talento_colaboradores.user_id correspondiente "
                . "(tabla={$tabla}, registro_id=" . ($registroId ?? 'pendiente') . ')'
            );

            IdentidadColaboradorIdPendiente::create([
                'seller_id' => $sellerId,
                'tabla' => $tabla,
                'registro_id' => $registroId,
            ]);
        }

        return $colaboradorId;
    }

    /** @return array<int,int> */
    private static function mapa(): array
    {
        if (static::$mapa === null) {
            static::$mapa = TalentoColaborador::pluck('id', 'user_id')->all();
        }

        return static::$mapa;
    }

    /** Solo para tests: fuerza releer el mapa en la siguiente resolución. */
    public static function resetCache(): void
    {
        static::$mapa = null;
    }
}
