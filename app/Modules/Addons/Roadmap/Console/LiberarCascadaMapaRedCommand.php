<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MR-32 (#971) — LIBERADOR EN CASCADA ACOTADO de la épica MAPA DE RED (#936).
 *
 * Libera el `excluir_pool_automatico` del SIGUIENTE item de la secuencia MR-01 → MR-07 sólo cuando
 * el anterior cerró limpio, y **se detiene solo** al llegar al techo.
 *
 * POR QUÉ ACOTADO. Liberar a mano no escala; soltar el freno de todos entrega la épica entera a
 * terminales autónomas. El punto medio es esta cascada, con un techo duro en #943 (MR-07):
 * hasta ahí nada toca las tablas del módulo viejo y todo lo que se escribe va a tablas `mapared_*`
 * nuevas. De #944 (MR-08) en adelante empieza el modelo de datos de verdad, donde una decisión mal
 * tomada se arrastra a diez items — ese tramo no se libera sin Irving.
 *
 * EL CONTEXTO QUE LO JUSTIFICA. La BD de dev se borró dos veces por terminales autónomas (22 y 25
 * de agosto), por el mismo mecanismo. Lo que hace aceptable automatizar esto es el ORDEN —MR-02
 * respalda antes de que MR-04/MR-05 escriban nada— y el techo, que impide que la cadena alcance lo
 * destructivo. Por eso `verificaRedDeSeguridad()` corre en CADA vuelta, no una sola vez al activar:
 * si la red se cae después, la cascada se detiene sola.
 *
 * DIRECCIÓN ÚNICA: este comando sólo pasa `excluir_pool_automatico` de `true` a `false`. Nunca
 * cambia `estado_aprobacion`, nunca despacha, nunca cierra items y nunca vuelve a frenar nada.
 */
class LiberarCascadaMapaRedCommand extends Command
{
    protected $signature = 'circuito:liberar-cascada-mapa-red
                            {--dry-run : Muestra qué haría, sin escribir}
                            {--reactivar : Irving levanta la detención y la cascada vuelve a correr}
                            {--estado : Sólo informa en qué punto va la cascada}';

    protected $description = 'MR-32: libera en cascada MR-01→MR-07 de la épica MAPA DE RED, con techo duro en #943.';

    /** La épica paraguas. */
    public const EPICA = 936;

    /** Secuencia EXACTA y cerrada. Que sea una constante y no una consulta es el primer candado. */
    public const SECUENCIA = [937, 938, 939, 940, 941, 942, 943];

    /** Techo absoluto. Nada más allá de este id se libera por ninguna vía. */
    public const TECHO = 943;

    /** Múltiplo de `eta_minutos` a partir del cual un item se considera atascado. */
    public const FACTOR_ATASCO = 3;

    private const ARCHIVO_ESTADO = 'circuito/liberador-mapa-red.json';
    private const ARCHIVO_PAUSA  = 'circuito/PAUSA';

    public function handle(RoadmapCircuitoService $svc): int
    {
        // Candado estructural: si alguien edita SECUENCIA y mete un id por encima del techo, el
        // comando no arranca. El techo manda sobre la lista, no al revés.
        foreach (self::SECUENCIA as $id) {
            if ($id > self::TECHO) {
                $this->error("SECUENCIA contiene #{$id}, por encima del techo #" . self::TECHO . '. Abortado.');

                return self::FAILURE;
            }
        }

        if ($this->option('reactivar')) {
            return $this->reactivar();
        }

        $estado = $this->leeEstado();

        if ($this->option('estado')) {
            return $this->informaEstado($estado);
        }

        if (($estado['detenido'] ?? false) === true) {
            $this->warn('Cascada DETENIDA desde ' . ($estado['detenido_at'] ?? '?') . '.');
            $this->line('Motivo: ' . ($estado['motivo'] ?? '(sin motivo registrado)'));
            $this->line('Para reactivar: php artisan circuito:liberar-cascada-mapa-red --reactivar');

            return self::SUCCESS;   // detenido no es fallo: es el diseño funcionando
        }

        // ── Red de seguridad de datos: se comprueba en CADA vuelta ─────────────────────────────
        if ($fallo = $this->verificaRedDeSeguridad()) {
            return $this->detener('red_de_seguridad', $fallo);
        }

        // ── Frenos globales ────────────────────────────────────────────────────────────────────
        if ($svc->isPaused()) {
            $this->warn('Circuito pausado (kill switch). No se libera nada en esta vuelta.');

            return self::SUCCESS;   // pausa es transitoria: NO detiene la cascada
        }

        if (is_file(storage_path('app/' . self::ARCHIVO_PAUSA))) {
            $this->warn('Existe storage/app/' . self::ARCHIVO_PAUSA . '. No se libera nada en esta vuelta.');

            return self::SUCCESS;
        }

        // ── Frenos duros: detienen la cascada hasta que Irving la reactive ─────────────────────
        if ($motivo = $this->buscaFrenoDuro()) {
            return $this->detener($motivo['tipo'], $motivo['detalle']);
        }

        // ── ¿Dónde va la cascada? ──────────────────────────────────────────────────────────────
        $items = $this->itemsSecuencia();

        $pendientes = array_values(array_filter(
            self::SECUENCIA,
            fn ($id) => ! $this->estaCompletado($items[$id] ?? null)
        ));

        if ($pendientes === []) {
            // Todo el tramo cerró: TECHO ALCANZADO. Bitácora de cierre y autodesactivación.
            return $this->detener('techo_alcanzado',
                'MR-01→MR-07 cerró completo hasta el techo #' . self::TECHO . '. '
                . 'De #944 (MR-08) en adelante no se libera nada sin decisión de Irving.');
        }

        $siguienteId = $pendientes[0];
        $siguiente   = $items[$siguienteId] ?? null;

        if (! $siguiente) {
            return $this->detener('item_ausente', "El item #{$siguienteId} de la secuencia no existe.");
        }

        // El primero de la secuencia lo libera Irving a mano: la cascada arranca DESPUÉS de él.
        $posicion = array_search($siguienteId, self::SECUENCIA, true);
        if ($posicion === 0) {
            $this->line("La cascada aún no arranca: #{$siguienteId} (el primero) sigue abierto.");
            $this->line('Estado: ' . $siguiente->estado_aprobacion
                . ' · freno: ' . var_export((bool) $siguiente->excluir_pool_automatico, true));

            return self::SUCCESS;
        }

        $anteriorId = self::SECUENCIA[$posicion - 1];
        $anterior   = $items[$anteriorId] ?? null;

        // ── Las 5 condiciones, TODAS obligatorias ──────────────────────────────────────────────
        if (! $this->estaCompletado($anterior)) {
            $this->line("Esperando a que #{$anteriorId} cierre (está en "
                . ($anterior->estado_aprobacion ?? '?') . '). Nada que liberar.');

            return self::SUCCESS;
        }

        if ($this->respuestasDe($anteriorId) > 0) {
            return $this->detener('respuesta_del_anterior',
                "#{$anteriorId} cerró pero generó un item [RESPUESTA]: hay una decisión pendiente "
                . 'antes de seguir.');
        }

        if ($ids = $this->epicaEnRequiereIrving()) {
            $this->warn('La épica tiene items en requiere_irving (#' . implode(', #', $ids)
                . '). No se libera hasta resolverlos.');

            return self::SUCCESS;   // transitorio: se resuelve solo cuando Irving decida
        }

        if ($siguiente->estado_aprobacion !== 'aprobado_irving') {
            $this->warn("#{$siguienteId} no está en aprobado_irving (está en "
                . "{$siguiente->estado_aprobacion}). No se toca.");

            return self::SUCCESS;
        }

        if (! $siguiente->excluir_pool_automatico) {
            $this->line("#{$siguienteId} ya está liberado. Nada que hacer.");

            return self::SUCCESS;
        }

        // ── Liberar ────────────────────────────────────────────────────────────────────────────
        $evidencia = sprintf(
            '#%d (%s) cerró en %s con merge_commit %s',
            $anteriorId,
            $this->codigo($anterior),
            $anterior->completed_at ?? $anterior->updated_at ?? '(sin fecha)',
            $anterior->merge_commit ? substr($anterior->merge_commit, 0, 12) : '(sin merge_commit)'
        );

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] Liberaría #{$siguienteId} (" . $this->codigo($siguiente) . ').');
            $this->line('   Evidencia: ' . $evidencia);
            $this->line('   Techo: #' . self::TECHO . ' — #944 en adelante nunca es candidato.');

            return self::SUCCESS;
        }

        $motivo = "Liberado por la cascada de MR-32: {$evidencia}. "
            . 'Techo de la cascada: #' . self::TECHO . ' (MR-07).';

        // Sin rastro no hay liberación: si el log no se puede escribir, no se toca el freno.
        try {
            $log   = $siguiente->log ?: [];
            $log[] = [
                'ts'             => now()->toIso8601String(),
                'por'            => 'circuito:liberar-cascada-mapa-red',
                'evento'         => 'freno_liberado',
                'item_previo'    => $anteriorId,
                'evidencia'      => $evidencia,
                'motivo'         => $motivo,
            ];
            $siguiente->log                     = $log;
            $siguiente->excluir_pool_automatico = false;
            $siguiente->save();
        } catch (\Throwable $e) {
            $this->error("No se pudo dejar rastro en #{$siguienteId}: {$e->getMessage()}. NO se liberó.");
            Log::channel('roadmap_externo')->error('liberador-mapa-red-sin-rastro', [
                'item'  => $siguienteId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info("Liberado #{$siguienteId} (" . $this->codigo($siguiente) . ').');
        $this->line('   ' . $evidencia);

        Log::channel('roadmap_externo')->info('liberador-mapa-red-libero', [
            'item'        => $siguienteId,
            'item_previo' => $anteriorId,
            'evidencia'   => $evidencia,
        ]);

        // Si el que acabamos de liberar es el techo, la cascada ya no tiene a quién seguir.
        if ($siguienteId === self::TECHO) {
            $this->line('Era el último del tramo: al cerrar #' . self::TECHO
                . ' la cascada se autodesactiva.');
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────── Frenos y verificaciones ───────────────────────────────

    /**
     * La red que hace aceptable automatizar esto. Se comprueba en CADA vuelta, no sólo al activar:
     * si alguien retira un guard más adelante, la cascada se detiene sola en vez de seguir corriendo
     * sin red.
     */
    private function verificaRedDeSeguridad(): ?string
    {
        $faltan = [];

        if (! is_file(base_path('tests/GuardBaseDePruebas.php'))) {
            $faltan[] = 'tests/GuardBaseDePruebas.php no existe';
        }

        $creates = base_path('tests/CreatesApplication.php');
        if (! is_file($creates) || ! str_contains((string) @file_get_contents($creates), 'GuardBaseDePruebas::verificarApp')) {
            $faltan[] = 'CreatesApplication ya no invoca GuardBaseDePruebas::verificarApp()';
        }

        $phpunit = base_path('phpunit.xml');
        if (! is_file($phpunit) || ! preg_match('/DB_DATABASE"\s+value="[^"]*_test"/', (string) @file_get_contents($phpunit))) {
            $faltan[] = 'phpunit.xml ya no fija una base terminada en _test';
        }

        if (! is_file(base_path('app/Services/MigrationGuardService.php'))) {
            $faltan[] = 'MigrationGuardService.php no existe';
        }

        return $faltan === [] ? null : 'Red de seguridad incompleta: ' . implode(' · ', $faltan);
    }

    /**
     * Frenos que DETIENEN la cascada (a diferencia de la pausa, que sólo salta la vuelta).
     */
    private function buscaFrenoDuro(): ?array
    {
        $items = $this->itemsSecuencia();

        // (1) Item de la secuencia rechazado o cancelado.
        foreach (self::SECUENCIA as $id) {
            $i = $items[$id] ?? null;
            if (! $i) {
                continue;
            }
            if (in_array($i->estado_aprobacion, ['rechazado', 'cancelado'], true)
                || $i->status === 'cancelled') {
                return ['tipo' => 'item_rechazado',
                        'detalle' => "#{$id} terminó en {$i->estado_aprobacion}/{$i->status}."];
            }
        }

        // (2) Cualquier [RESPUESTA] en la épica — una decisión pendiente detiene todo el tramo.
        $resp = RoadmapItem::where('title', 'like', '[RESPUESTA]%')
            ->where(function ($q) {
                $q->where('origen_item_id', self::EPICA)
                  ->orWhereIn('origen_item_id', self::SECUENCIA);
            })
            ->pluck('id')->all();

        if ($resp !== []) {
            return ['tipo' => 'respuesta_en_epica',
                    'detalle' => 'Nació item [RESPUESTA] en la épica: #' . implode(', #', $resp) . '.'];
        }

        // (3) Item atascado: más de FACTOR_ATASCO × su eta_minutos sin cerrar.
        foreach (self::SECUENCIA as $id) {
            $i = $items[$id] ?? null;
            if (! $i || $this->estaCompletado($i) || empty($i->eta_minutos)) {
                continue;
            }
            $arranque = $i->trabajo_iniciado_at ?: $i->claimed_at;
            if (! $arranque) {
                continue;
            }
            $limite = (int) $i->eta_minutos * self::FACTOR_ATASCO;
            $mins   = $arranque->diffInMinutes(now());
            if ($mins > $limite) {
                return ['tipo' => 'item_atascado',
                        'detalle' => "#{$id} lleva {$mins} min abiertos, más de {$limite} "
                                   . '(' . self::FACTOR_ATASCO . '× su eta de ' . $i->eta_minutos . ' min).'];
            }
        }

        return null;
    }

    /** Items de la épica esperando decisión humana. Transitorio: no detiene, sólo salta la vuelta. */
    private function epicaEnRequiereIrving(): array
    {
        return RoadmapItem::where('estado_aprobacion', 'requiere_irving')
            ->where(function ($q) {
                $q->where('origen_item_id', self::EPICA)->orWhere('id', self::EPICA);
            })
            ->pluck('id')->all();
    }

    private function respuestasDe(int $itemId): int
    {
        return RoadmapItem::where('origen_item_id', $itemId)
            ->where('title', 'like', '[RESPUESTA]%')
            ->count();
    }

    /** @return array<int,RoadmapItem> */
    private function itemsSecuencia(): array
    {
        return RoadmapItem::whereIn('id', self::SECUENCIA)->get()->keyBy('id')->all();
    }

    private function estaCompletado(?RoadmapItem $i): bool
    {
        return $i !== null && ($i->estado_aprobacion === 'completado' || $i->status === 'done');
    }

    private function codigo(?RoadmapItem $i): string
    {
        return $i && preg_match('/^MR-\d+/', (string) $i->title, $m) ? $m[0] : '?';
    }

    // ──────────────────────────── Detención, estado y reporte ────────────────────────────

    /**
     * Detiene la cascada, deja el motivo persistido y entrega el resumen por el canal del digest.
     * Devuelve SUCCESS: detenerse es el diseño funcionando, no un fallo del comando.
     */
    private function detener(string $tipo, string $detalle): int
    {
        $resumen = $this->armaResumen($tipo, $detalle);

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] Se detendría por: {$tipo}");
            $this->line($resumen);

            return self::SUCCESS;
        }

        $this->escribeEstado([
            'detenido'    => true,
            'tipo'        => $tipo,
            'motivo'      => $detalle,
            'detenido_at' => now()->toIso8601String(),
            'resumen'     => $resumen,
        ]);

        $this->entregaReporte($tipo, $detalle, $resumen);

        $this->warn("Cascada DETENIDA — {$tipo}");
        $this->line($resumen);

        return self::SUCCESS;
    }

    /**
     * Entrega por el canal que YA usa `circuito:digest` del SupervisorService (salida de consola +
     * log del circuito) y deja copia en el `log` de MR-32 y del paraguas, que es donde la Torre lo
     * muestra. NO se construye canal nuevo — eso es exactamente lo que MR-31 previó.
     */
    private function entregaReporte(string $tipo, string $detalle, string $resumen): void
    {
        Log::channel('roadmap_externo')->info('liberador-mapa-red-detenido', [
            'tipo'    => $tipo,
            'motivo'  => $detalle,
            'resumen' => $resumen,
        ]);

        foreach ([self::EPICA, $this->itemMr32()] as $destino) {
            if (! $destino) {
                continue;
            }
            try {
                $item = RoadmapItem::find($destino);
                if (! $item) {
                    continue;
                }
                $log   = $item->log ?: [];
                $log[] = [
                    'ts'      => now()->toIso8601String(),
                    'por'     => 'circuito:liberar-cascada-mapa-red',
                    'evento'  => 'cascada_detenida',
                    'tipo'    => $tipo,
                    'motivo'  => $detalle,
                    'resumen' => $resumen,
                ];
                $item->log = $log;
                $item->save();
            } catch (\Throwable $e) {
                Log::channel('roadmap_externo')->warning('liberador-mapa-red-reporte-fallo', [
                    'destino' => $destino, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function itemMr32(): ?int
    {
        return RoadmapItem::where('title', 'like', 'MR-32 —%')->value('id');
    }

    private function armaResumen(string $tipo, string $detalle): string
    {
        $items  = $this->itemsSecuencia();
        $hechos = $faltan = [];

        foreach (self::SECUENCIA as $id) {
            $i     = $items[$id] ?? null;
            $linea = '#' . $id . ' ' . $this->codigo($i) . ' — ' . ($i->estado_aprobacion ?? 'AUSENTE');
            $this->estaCompletado($i) ? $hechos[] = $linea : $faltan[] = $linea;
        }

        return implode("\n", array_filter([
            'CASCADA MAPA DE RED — detenida (' . $tipo . ')',
            'Motivo: ' . $detalle,
            '',
            'Completados (' . count($hechos) . '/' . count(self::SECUENCIA) . '):',
            $hechos ? '  ' . implode("\n  ", $hechos) : '  (ninguno)',
            '',
            'Pendientes:',
            $faltan ? '  ' . implode("\n  ", $faltan) : '  (ninguno)',
            '',
            'Techo: #' . self::TECHO . ' (MR-07). De #944 (MR-08) en adelante no se libera nada sin Irving.',
            'Reactivar: php artisan circuito:liberar-cascada-mapa-red --reactivar',
        ]));
    }

    private function informaEstado(array $estado): int
    {
        $items = $this->itemsSecuencia();

        $this->line('<comment>CASCADA MAPA DE RED — secuencia MR-01 → MR-07</comment>');
        $filas = [];
        foreach (self::SECUENCIA as $id) {
            $i = $items[$id] ?? null;
            $filas[] = [
                $id,
                $this->codigo($i),
                $i->estado_aprobacion ?? 'AUSENTE',
                $i ? var_export((bool) $i->excluir_pool_automatico, true) : '-',
                $this->estaCompletado($i) ? 'cerrado' : '',
            ];
        }
        $this->table(['id', 'código', 'estado', 'freno', ''], $filas);

        $this->line(($estado['detenido'] ?? false)
            ? '<error>DETENIDA</error> desde ' . ($estado['detenido_at'] ?? '?') . ' — ' . ($estado['motivo'] ?? '')
            : '<info>Activa.</info>');
        $this->line('Techo: #' . self::TECHO . ' (MR-07).');

        return self::SUCCESS;
    }

    private function reactivar(): int
    {
        $estado = $this->leeEstado();

        if (($estado['detenido'] ?? false) !== true) {
            $this->line('La cascada no estaba detenida. Nada que reactivar.');

            return self::SUCCESS;
        }

        $this->escribeEstado(['detenido' => false, 'reactivado_at' => now()->toIso8601String()]);

        $this->info('Cascada REACTIVADA. Se detuvo por: ' . ($estado['tipo'] ?? '?')
            . ' — ' . ($estado['motivo'] ?? ''));
        $this->warn('El techo #' . self::TECHO . ' sigue vigente: reactivar NO lo levanta.');

        Log::channel('roadmap_externo')->info('liberador-mapa-red-reactivado', [
            'motivo_previo' => $estado['motivo'] ?? null,
        ]);

        return self::SUCCESS;
    }

    private function leeEstado(): array
    {
        $ruta = storage_path('app/' . self::ARCHIVO_ESTADO);
        if (! is_file($ruta)) {
            return [];
        }

        return json_decode((string) @file_get_contents($ruta), true) ?: [];
    }

    private function escribeEstado(array $estado): void
    {
        $ruta = storage_path('app/' . self::ARCHIVO_ESTADO);
        @mkdir(dirname($ruta), 0775, true);
        @file_put_contents($ruta, json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
