<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FASE 2A.3 — BACKFILL de `origen_bloqueo`: separa el freno HUMANO del consejo del CLASIFICADOR.
 *
 * Antes de esto, un bloqueo era un string y había DOS escritores que no se hablaban:
 *
 *   · A mano, en el TÍTULO (`[BLOCKED-NEGOCIO]`, `[PARKED-PROD]`) — decisión de Irving. Los 8
 *     guards lo leen con `LIKE` y por eso SÍ frena.
 *   · `circuito:priorizar-seguridad`, en `comentarios_claude` dentro del sello `⟪SEG-TRIAGE⟫`.
 *     NINGÚN guard lee esa columna, así que ese "bloqueo" nunca frenó nada.
 *
 * El backfill lleva ambos a columna con su origen, para que la regla de Irving quede codificada:
 * `humano` frena, `clasificador` informa.
 *
 * DRY-RUN por defecto: escribe SOLO con `--apply`.
 */
class BackfillBloqueosCommand extends Command
{
    protected $signature = 'circuito:backfill-bloqueos {--apply : escribe (por defecto solo reporta)}';

    protected $description = 'Backfill de origen_bloqueo: rótulo en título → humano; sello del clasificador → clasificador (2A.3).';

    /** Rótulos de frontera dura. El `u` es obligatorio: hay rótulos con acentos. */
    private const RE_ROTULO = '/\[(BLOCKED|PARKED)-[^\]]+\]/u';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $items = RoadmapItem::query()
            ->whereNull('archivado_at')
            ->where(function ($q) {
                $q->where('title', 'like', '%[BLOCKED-%')->orWhere('title', 'like', '%[PARKED-%')
                  ->orWhere('comentarios_claude', 'like', '%[BLOCKED-%')->orWhere('comentarios_claude', 'like', '%[PARKED-%');
            })
            ->get(['id', 'title', 'comentarios_claude', 'motivo_bloqueo', 'origen_bloqueo']);

        if ($items->isEmpty()) {
            $this->info('Backfill de bloqueos: nada que hacer.');

            return self::SUCCESS;
        }

        $plan = [];
        foreach ($items as $it) {
            $enTitulo = (bool) preg_match(self::RE_ROTULO, (string) $it->title, $mT);

            // El rótulo del título GANA: es la decisión explícita de Irving. Si además el
            // clasificador opinó, esa opinión ya vive en el brief de `comentarios_claude`.
            if ($enTitulo) {
                $origen = 'humano';
                $motivo = 'Rótulo ' . $mT[0] . ' puesto a mano en el título.';
            } else {
                preg_match(self::RE_ROTULO, (string) $it->comentarios_claude, $mC);
                $origen = 'clasificador';
                $motivo = 'Clasificador de riesgo (Opus) marcó ' . ($mC[0] ?? '[BLOCKED-?]')
                    . '. Es un CONSEJO: no frena el despacho.';
            }

            // `motivo_bloqueo` puede venir ya escrito por otra causa viva (espera de merge,
            // anti-bucle, destrabe, o el propio worker). Eso NO se pisa: se conserva y solo se
            // sella el origen. Perder ese texto sería volver a la ceguera que 2A.2 vino a cerrar.
            $motivoPrevio = trim((string) $it->motivo_bloqueo);
            $conservado   = $motivoPrevio !== '';

            $plan[] = [
                'id'          => $it->id,
                'origen'      => $origen,
                'motivo'      => $conservado ? $motivoPrevio : $motivo,
                'conservado'  => $conservado,
                'ya_sellado'  => $it->origen_bloqueo !== null,
                'title'       => mb_substr((string) $it->title, 0, 44),
            ];
        }

        $humano = collect($plan)->where('origen', 'humano')->count();
        $clasif = collect($plan)->where('origen', 'clasificador')->count();
        $consv  = collect($plan)->where('conservado', true)->count();
        $sella  = collect($plan)->where('ya_sellado', true)->count();

        $this->newLine();
        $this->info('BACKFILL DE BLOQUEOS ' . ($apply ? '(APLICANDO)' : '(DRY-RUN — no escribe)'));
        $this->line("  candidatos                : " . count($plan));
        $this->line("  → origen=humano  (FRENA)  : {$humano}");
        $this->line("  → origen=clasificador     : {$clasif}   (informativo, NO frena)");
        $this->line("  motivo_bloqueo conservado : {$consv}   (ya tenían texto de otra causa; no se pisa)");
        $this->line("  ya sellados de antes      : {$sella}   (re-corrida idempotente)");
        $this->newLine();

        $this->table(
            ['item', 'origen', 'motivo conservado', 'título'],
            collect($plan)->sortBy('origen')->map(fn ($p) => [
                '#' . $p['id'], $p['origen'], $p['conservado'] ? 'sí' : '—', $p['title'],
            ])->all()
        );

        if (! $apply) {
            $this->comment('Dry-run. Vuelve a correr con --apply para escribir.');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($plan as $p) {
            // Por el MODELO a propósito: así el hook de traza (2A.4) deja en el log quién selló
            // cada bloqueo y con qué valores. Es el rastro que no existía.
            $it = RoadmapItem::find($p['id']);
            if (! $it) {
                continue;
            }
            $it->origen_bloqueo = $p['origen'];
            $it->motivo_bloqueo = $p['motivo'];
            $it->save();
            $n++;
        }

        $this->info("Backfill aplicado a {$n} item(s).");

        DB::table('settings')->updateOrInsert(
            ['key' => 'circuito_backfill_bloqueos_at'],
            ['value' => now()->toDateTimeString()]
        );

        return self::SUCCESS;
    }
}
