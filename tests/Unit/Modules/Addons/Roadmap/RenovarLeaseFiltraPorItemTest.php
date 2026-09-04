<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DEL FIX #640 (causa raíz compartida con #210).
 *
 * El caso medido: #100 y #86 quedaron huérfanos (`en_progreso`, `updated_at` congelado hace >30
 * min) pero INVISIBLES a `circuito:reap-stuck` porque su `claimed_at` seguía renovándose en
 * lockstep con el item que sí trabajaba su worker_sid (#139/#114). Causa: `renovarLease()` hacía
 * `UPDATE roadmap_items SET claimed_at=now() WHERE worker_sid=? AND estado_aprobacion='en_progreso'`
 * — SIN filtrar por `id` — así que un mismo `worker_sid` con más de un item `en_progreso` (el
 * huérfano + el actual) le renovaba el lease a AMBOS en cada latido, blindando al huérfano para
 * siempre mientras el worker siguiera vivo.
 *
 * ⚠️ TestCase PURO a propósito, igual que `RetriageNoRevocaFrenoHumanoTest`/`PoolGuardCoherenceTest`
 * de este mismo directorio: `Tests\TestCase` corre `migrate:fresh --seed` contra la base compartida
 * de dev. El fix es mecánico (una cláusula `where` condicional) y se verifica leyendo el código
 * fuente, sin necesidad de tocar BD.
 */
class RenovarLeaseFiltraPorItemTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function servicio(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php';
        $this->assertFileExists($f, 'Se movió/renombró el servicio: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    /** `renovarLease()` debe seguir aceptando el item actual y acotar el UPDATE por `id` cuando lo recibe. */
    public function test_renovar_lease_acota_por_id_cuando_recibe_el_item_actual(): void
    {
        $cuerpo = $this->metodo($this->servicio(), 'renovarLease');

        $this->assertStringContainsString('currentItemId', $cuerpo,
            'renovarLease() dejó de recibir el item actual: sin él no puede distinguir el lease del '
            . 'item que de verdad se trabaja del de un huérfano con el mismo worker_sid (bug de #100/#86).');

        $this->assertStringContainsString("where('id', \$currentItemId)", $cuerpo,
            "renovarLease() ya no acota el UPDATE por `id`: volvería a renovarle el claimed_at a "
            . 'TODOS los en_progreso del worker_sid (huérfano incluido), como en el bug medido en #640.');
    }

    /** `liveBeat()` debe pasarle el item ACTUAL de la vuelta a renovarLease(), no una llamada ciega. */
    public function test_live_beat_pasa_el_current_item_a_renovar_lease(): void
    {
        $cuerpo = $this->metodo($this->servicio(), 'liveBeat');

        $this->assertMatchesRegularExpression(
            "/renovarLease\(\\\$sid,\s*\\\$d\['current_item'\]/",
            $cuerpo,
            "liveBeat() ya no pasa \$d['current_item'] a renovarLease(): volvería a renovar el lease "
            . 'de TODOS los en_progreso del sid en cada latido, sin distinguir cuál es el huérfano.'
        );
    }

    /** Cuerpo fuente de un método, balanceando llaves. */
    private function metodo(string $src, string $nombre): string
    {
        $pos = strpos($src, "function {$nombre}(");
        $this->assertNotFalse($pos, "No encontré `{$nombre}()` — ¿se renombró?");

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        for ($i = $inicio, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $inicio, $i - $inicio + 1);
            }
        }

        $this->fail("No pude delimitar `{$nombre}()`.");
    }
}
