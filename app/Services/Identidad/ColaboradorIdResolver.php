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

    /** Flag de LECTURA (Fase 4 de #9990778, item #9990879) — separado del de escritura. */
    public static function lecturaHabilitada(): bool
    {
        return (bool) config('identidad.lectura_colaborador_id', false);
    }

    /**
     * Corte de lectura módulo por módulo: agrega (si el flag de lectura está
     * activo) el LEFT JOIN a `talento_colaboradores` resuelto por `colaborador_id`
     * sobre $query, y devuelve la expresión SQL que el llamador debe usar en el
     * JOIN/GROUP BY hacia `users` en vez de "{$tabla}.seller_id" directo.
     *
     * Con el flag OFF es un no-op: retorna "{$tabla}.seller_id" tal cual (mismo
     * comportamiento de siempre, sin JOIN extra). Con el flag ON, retorna un
     * COALESCE que cae a `seller_id` cuando el bridge no tiene colaborador_id
     * todavía — el valor resuelto es el mismo `users.id` en ambos casos.
     */
    public static function applyIdentityJoin($query, string $tabla = 'client_main_information', string $alias = 'colaborador_bridge_read'): string
    {
        if (!static::lecturaHabilitada()) {
            return "{$tabla}.seller_id";
        }

        $query->leftJoin("talento_colaboradores as {$alias}", "{$alias}.id", '=', "{$tabla}.colaborador_id");

        return "COALESCE({$alias}.user_id, {$tabla}.seller_id)";
    }

    public static function resolve(int $sellerId): ?int
    {
        return static::mapa()[$sellerId] ?? null;
    }

    /**
     * Resuelve el nombre de usuario asociado a un `colaborador_id` (Fase 4 módulo 3,
     * item #9991020) — para accessors de instancia (no queries agregadas) que hoy
     * resuelven el nombre del vendedor vía `seller_id`/`user_seller()`. Devuelve
     * null si no hay colaborador_id, el colaborador no existe o no tiene user
     * asociado — el llamador debe caer a su fallback histórico en ese caso.
     */
    public static function resolveNombrePorColaboradorId(?int $colaboradorId): ?string
    {
        if (!$colaboradorId) {
            return null;
        }

        return TalentoColaborador::with('user')->find($colaboradorId)?->user?->name;
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
