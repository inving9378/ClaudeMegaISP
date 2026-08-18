<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2A.2 — LIMPIEZA DE FLAGS HUÉRFANOS de `excluir_pool_automatico`.
 *
 * `excluir_pool_automatico` es el master switch que saca un item del pool de reclamo. Sus tres
 * escritores vivos lo encienden SIEMPRE junto a una bandera compañera que explica el porqué
 * (`bloqueado_por_bucle`, `esperando_merge_irving`, `requiere_sesion_supervisada`). Se encontraron
 * items con el master encendido, las TRES compañeras apagadas y `motivo_bloqueo` vacío: residuo de
 * código anterior o de UPDATEs a mano. Nadie sabía por qué estaban fuera del pool.
 *
 * Por qué importa: el guard anti-re-aprobación de `RoadmapController::decidir()` mira las banderas
 * compañeras, NO el master. Con el master huérfano, aprobar el item respondía 200 y no lo movía
 * — sin 422, sin aviso. #32 acumuló 8 aprobaciones mudas de Irving; #199, 14.
 *
 * SELECCIÓN POR CONDICIÓN, NO POR ID: los ids de dev y prod divergen (auto-increments
 * independientes), así que una lista fija de ids dispararía sobre items equivocados en otra base.
 * La condición describe la huella del huérfano y es portable.
 *
 * NO se tocan (su freno sigue vigente, decisión de Irving 2026-08-18):
 *   - los rotulados [BLOCKED-…]/[PARKED-…] en el título (freno humano, se respeta)
 *   - los ya cerrados (`status=done`) o `rechazado`/`cancelado`
 *   - los que esperan decisión (`requiere_irving`)
 *   - los que sí tienen causa conocida (bucle / sesión supervisada / espera de merge)
 *
 * Idempotente: al limpiar se sella `motivo_bloqueo`, así que el WHERE deja de seleccionarlos.
 * Escribe además entrada en el `log` del item (actor, valor anterior, valor nuevo, razón), que es
 * justo la trazabilidad cuya ausencia hizo inatribuibles a estos huérfanos.
 */
return new class extends Migration
{
    private const SELLO = 'huerfano-limpiado-2A2';

    public function up(): void
    {
        $items = DB::table('roadmap_items')
            ->where('excluir_pool_automatico', 1)
            // Sin ninguna de las tres causas conocidas...
            ->where('bloqueado_por_bucle', 0)
            ->where('requiere_sesion_supervisada', 0)
            ->where('esperando_merge_irving', 0)
            // ...y sin motivo registrado: nadie puede decir por qué está fuera del pool.
            ->where(fn ($q) => $q->whereNull('motivo_bloqueo')->orWhere('motivo_bloqueo', ''))
            // Solo trabajo VIVO y ya autorizado: lo cerrado, rechazado o en bandeja no se toca.
            ->whereNull('archivado_at')
            ->where('status', 'pending')
            ->whereIn('estado_aprobacion', ['aprobado_irving', 'aprobado_claude', 'aprobado_revisor'])
            // El rótulo humano en el título es un freno deliberado de Irving: se respeta.
            ->where('title', 'not like', '%[BLOCKED-%')
            ->where('title', 'not like', '%[PARKED-%')
            ->get(['id', 'log', 'estado_aprobacion']);

        $now = now();

        foreach ($items as $item) {
            $log = json_decode($item->log ?? '[]', true);
            if (! is_array($log)) {
                $log = [];
            }

            $log[] = [
                'ts'         => $now->toIso8601String(),
                'por'        => 'migracion:2A.2',
                'estado'     => $item->estado_aprobacion,
                'decision'   => 'destrabe',
                'comentario' => 'Flag huérfano de excluir_pool_automatico: encendido sin ninguna causa '
                    . 'conocida (bucle / sesión supervisada / espera de merge) y sin motivo_bloqueo. '
                    . 'Se devuelve al pool de reclamo.',
                'flags'      => [
                    'excluir_pool_automatico' => ['antes' => true, 'despues' => false],
                    'motivo_bloqueo'          => ['antes' => null, 'despues' => self::SELLO],
                ],
            ];

            // DB::table a propósito: escribir por el modelo dispararía los hooks de `saving`
            // (parqueo de C con rama, contador anti-bucle) sobre items que no están cambiando
            // de estado. Aquí solo se corrige metadata de despacho.
            DB::table('roadmap_items')->where('id', $item->id)->update([
                'excluir_pool_automatico' => 0,
                'motivo_bloqueo'          => self::SELLO,
                'log'                     => json_encode($log, JSON_UNESCAPED_UNICODE),
                'updated_at'              => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op a propósito. Revertir significaría volver a sacar del pool items que nadie supo
        // explicar por qué estaban fuera — reintroduciría el bug, no lo desharía. El sello
        // `motivo_bloqueo` deja el rastro de qué se tocó y cuándo por si hay que auditarlo.
    }
};
