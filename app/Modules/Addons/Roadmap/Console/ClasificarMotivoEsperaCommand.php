<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * CIRC-03 Fase B (#9990905) — clasifica `motivo_espera` para los items ya identificados por
 * #9990886 y hace un barrido de candidatos nuevos.
 *
 * Dos pasos, cada uno idempotente por su cuenta:
 *
 *   1) CONOCIDOS — aplica el mapeo fijo (abajo) a los items que #9990886 ya había identificado.
 *      Solo escribe si `motivo_espera` sigue NULL (no pisa una clasificación manual distinta ya
 *      hecha por alguien — correrlo 2 veces no cambia nada en el 2do run).
 *
 *   2) BARRIDO — busca por patrones de texto (título/descripción/comentarios_claude) items
 *      `aprobado_irving`/`requiere_irving` con `motivo_espera` NULL que PARECEN estar esperando
 *      algo externo. Decisión de Irving (brief del item, q2): el barrido SOLO PROPONE — nunca
 *      escribe motivo_espera por su cuenta. Esto es de solo lectura siempre, sin --apply.
 */
class ClasificarMotivoEsperaCommand extends Command
{
    protected $signature = 'circuito:clasificar-motivo-espera
        {--sid= : tu slot de terminal (wt-K), solo para el log}';

    protected $description = 'CIRC-03 Fase B (#9990905) — clasifica motivo_espera de los items conocidos + propone candidatos del barrido (solo lectura).';

    /**
     * Mapeo fijo verificado contra el texto real de cada item (2026-09-12, ver
     * docs/bitacora/2026-09-12-item-9990905.md para el detalle de cada uno).
     */
    private const CONOCIDOS = [
        683 => 'credencial',          // PAC CFDI 4.0 — bloqueado por credenciales de Irving
        692 => 'sesion_presencial',   // OLT Huawei Fase A — validar en lab con Irving presente
        283 => 'decision',            // "Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL?"
        671 => 'hardware',            // ZteDriver real — decisión ya tomada en #283, falta el piloto físico
        9990075 => 'credencial',      // Hook DNS-01 — falta credencial + delegación NS
        691 => 'autorizacion',        // Cuota Medussa — BLOQUEADO sin autorización legal/CNBV
        718 => 'decision',            // Tiempo de pantalla Vista Hijo — decisión de #661 sin resolver
        661 => 'decision',            // Tiempo de pantalla — decisión de alcance/plataforma pendiente
        9990571 => 'frontera_produccion', // Build atómico en RemoteDeployCommand (PROD)
        724 => 'frontera_produccion', // Unificación invoices — ventana de monitoreo en PROD
    ];

    /**
     * Patrones de barrido (case-insensitive, sobre título+descripción+comentarios_claude).
     * Los mismos que cita el spec del item, más sinónimos evidentes.
     */
    /**
     * OJO: 'frontera dura' se probó y se descartó — es la etiqueta con la que el propio circuito
     * marca CASI CUALQUIER item escalado a Irving (153 de 166 matches en la corrida de prueba),
     * no una señal de que ESE item esté esperando algo externo específico. Incluirla vaciaría de
     * sentido la clasificación (todo terminaría en "frontera_produccion" sin serlo).
     */
    private const PATRONES = [
        'bloqueado por', 'bloqueado:', 'bloqueado sin',
        'requiere credencial', 'falta credencial', 'falta la credencial',
        'requiere hardware', 'falta hardware', 'hardware piloto',
        'pendiente de autorización', 'sin autorización',
        'sesión presencial', 'requiere sesión presencial',
        'decisión de irving', 'decisión pendiente', 'decisión de negocio',
    ];

    public function handle(): int
    {
        $sid = $this->option('sid');

        $this->aplicarConocidos();
        $this->proponerBarrido();

        return self::SUCCESS;
    }

    private function aplicarConocidos(): void
    {
        $clasificados = 0;
        $yaIguales = 0;
        $conservados = 0;
        $noExisten = 0;

        foreach (self::CONOCIDOS as $id => $motivo) {
            $item = RoadmapItem::find($id);

            if (! $item) {
                $this->warn("#{$id} no existe, se omite.");
                $noExisten++;
                continue;
            }

            if ($item->motivo_espera === $motivo) {
                $yaIguales++;
                continue;
            }

            if ($item->motivo_espera !== null) {
                $this->line("#{$id} ya tiene motivo_espera='{$item->motivo_espera}' (manual), se conserva.");
                $conservados++;
                continue;
            }

            $item->motivo_espera = $motivo;
            $item->excluir_pool_automatico = true;
            $item->save();
            $this->info("#{$id} -> motivo_espera='{$motivo}'");
            $clasificados++;
        }

        $this->info("Conocidos: {$clasificados} clasificados ahora, {$yaIguales} ya estaban, {$conservados} conservados (manual), {$noExisten} no existen.");
    }

    private function proponerBarrido(): void
    {
        $conocidosIds = array_keys(self::CONOCIDOS);

        $candidatos = RoadmapItem::query()
            ->whereIn('estado_aprobacion', ['aprobado_irving', 'requiere_irving'])
            ->whereNull('motivo_espera')
            ->whereNotIn('id', $conocidosIds)
            ->get(['id', 'title', 'description', 'comentarios_claude']);

        $propuestas = [];

        foreach ($candidatos as $item) {
            $texto = Str::lower($item->title . ' ' . $item->description . ' ' . $item->comentarios_claude);

            foreach (self::PATRONES as $patron) {
                if (Str::contains($texto, Str::lower($patron))) {
                    $propuestas[] = ['id' => $item->id, 'title' => $item->title, 'patron' => $patron];
                    break; // un match por item basta para proponerlo
                }
            }
        }

        $total = count($propuestas);

        $this->info("Barrido: {$total} candidato(s) nuevo(s) encontrados (solo propuesta, NO se escribió nada).");

        foreach ($propuestas as $p) {
            $this->line("  #{$p['id']} [{$p['patron']}] {$p['title']}");
        }

        if ($total > 15) {
            $this->warn('Más de 15 candidatos: según el spec del item, no se tocan en masa aquí — queda para un sub-item aparte.');
        }
    }
}
