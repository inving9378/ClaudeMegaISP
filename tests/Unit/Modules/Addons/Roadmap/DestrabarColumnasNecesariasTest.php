<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO — item #36 (2026-08-28).
 *
 * `DestrabarCommand` trae los items con `select(COLUMNAS_NECESARIAS)` (item #864, para no
 * arrastrar columnas TEXT/JSON al sort buffer). Ese `select` recorta el modelo: cualquier
 * columna que un guard lea DESPUÉS, si no está en la lista, llega `null` en silencio (Eloquent
 * no revienta por columna no seleccionada — solo revienta 1054 si la columna no existe en la
 * tabla, que es un caso distinto).
 *
 * `JarvisService::evaluarYaDecidido()` frena si `$item->requiere_sesion_supervisada` es true
 * (#893: "Irving pidió estar presente, no se auto-despacha"), pero la columna faltaba en
 * `COLUMNAS_NECESARIAS` → el guard siempre leía `null` sobre items salidos de este comando y
 * `aprobarYaDecidido()` los re-aprobaba solo. Síntoma real: el item #36 se marcó
 * `requiere_sesion_supervisada=true` a mano (para pararlo hasta que Irving estuviera presente) y
 * `circuito:destrabar-bandeja --apply` lo re-aprobó minutos después, repetidamente.
 *
 * Este test fija la columna en la lista para que la próxima vez que alguien la quite (o agregue
 * un guard nuevo sin sumar su columna) truene aquí, no en producción silenciosamente.
 */
class DestrabarColumnasNecesariasTest extends TestCase
{
    public function test_requiere_sesion_supervisada_esta_en_columnas_necesarias(): void
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Console/DestrabarCommand.php';
        $this->assertFileExists($f);

        $src = file_get_contents($f);
        $pos = strpos($src, 'COLUMNAS_NECESARIAS = [');
        $this->assertNotFalse($pos, 'No encontré `COLUMNAS_NECESARIAS` en DestrabarCommand — ¿se renombró?');

        $fin = strpos($src, '];', $pos);
        $bloque = substr($src, $pos, $fin - $pos);

        $this->assertStringContainsString("'requiere_sesion_supervisada'", $bloque,
            'Falta `requiere_sesion_supervisada` en `COLUMNAS_NECESARIAS` de DestrabarCommand: '
            . 'el guard homónimo de `JarvisService::evaluarYaDecidido()` volvería a leer `null` '
            . 'en silencio y a re-aprobar items que Irving marcó para verse en persona (regresión #36).');
    }
}
