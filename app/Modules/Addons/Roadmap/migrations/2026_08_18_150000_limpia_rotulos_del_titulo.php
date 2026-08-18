<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FASE 2A.3 (último paso) — SACA EL FRENO DEL TÍTULO.
 *
 * El rótulo `[BLOCKED-…]`/`[PARKED-…]` dentro del `title` era el mecanismo de freno: frágil (no es
 * consultable, no expira, se pierde si alguien edita el título) y sobre todo INCAPAZ de distinguir
 * quién lo puso. Ya no hace falta: el freno vive en `origen_bloqueo='humano'`.
 *
 * ORDEN QUE IMPORTA, y por eso esta migración va al final:
 *   1. columnas (2026_08_18_140000)
 *   2. backfill (`circuito:backfill-bloqueos --apply`) → los 33 quedan sellados `humano`
 *   3. la Torre pinta el badge «⛔ frenado por ti» con el motivo en el tooltip
 *   4. reciÉN AHORA se puede quitar el texto del título
 *
 * Invertir 3 y 4 dejaría el freno VIGENTE pero INVISIBLE: seguiría frenando y nadie sabría por qué.
 * Ese es justo el fallo que esta fase entera vino a corregir, así que no se repite al revés.
 *
 * SOLO toca items ya sellados como `humano`: si el backfill no alcanzó a alguno, conserva su rótulo
 * y el fallback legacy del `LIKE` lo sigue frenando. Fail-safe por construcción.
 *
 * Deja el título anterior en el `log` del item: borrar un rótulo sin constancia de que existió
 * sería volver a la ceguera de 2A.2.
 */
return new class extends Migration
{
    private const RE = '/\[(BLOCKED|PARKED)-[^\]]*\]\s*/i';

    public function up(): void
    {
        $items = DB::table('roadmap_items')
            ->where('origen_bloqueo', 'humano')
            ->whereNull('archivado_at')
            ->where(fn ($q) => $q->where('title', 'like', '%[BLOCKED-%')->orWhere('title', 'like', '%[PARKED-%'))
            ->get(['id', 'title', 'log', 'estado_aprobacion', 'motivo_bloqueo']);

        $now = now();

        foreach ($items as $item) {
            $nuevo = trim((string) preg_replace(self::RE, '', (string) $item->title));
            if ($nuevo === '' || $nuevo === $item->title) {
                continue;   // no dejar un item sin título por un rótulo que era TODO el título
            }

            $log = json_decode($item->log ?? '[]', true);
            if (! is_array($log)) {
                $log = [];
            }

            $log[] = [
                'ts'         => $now->toIso8601String(),
                'por'        => 'migracion:2A.3',
                'estado'     => $item->estado_aprobacion,
                'decision'   => 'rotulo_a_columna',
                'comentario' => 'El rótulo sale del título: el freno ahora vive en '
                    . "origen_bloqueo='humano' y la Torre lo pinta como badge. El item SIGUE frenado.",
                'titulo_previo' => $item->title,
            ];

            DB::table('roadmap_items')->where('id', $item->id)->update([
                'title'      => $nuevo,
                'log'        => json_encode($log, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op. El título previo queda en el `log` de cada item (entrada `rotulo_a_columna`) por si
        // hay que reconstruirlo a mano; restaurarlo automáticamente reintroduciría el mecanismo
        // frágil que se acaba de retirar.
    }
};
