<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * Item roadmap #9990206 — GUARD CONTRA IDs EXPLÍCITOS en `roadmap_items`.
 *
 * Una sola inserción con `id` puesto a mano (p.ej. `id=999999` para probar un comando) despega el
 * AUTO_INCREMENT de InnoDB para siempre: MySQL nunca lo baja por debajo de `max(id)+1`, ni
 * borrando la fila después. Así nació el salto real de 990 → 1.000.000 y luego → 9.990.000 que
 * motivó este item. `RoadmapItem::booted()` cierra la puerta con un hook `creating` que rechaza
 * cualquier `id` puesto a mano, salvo dentro de la suite de tests (donde varios tests hermanos —
 * `DiagnosticoItemServiceTest`, `ColisionPorTablaMigracionTest` — siembran ids fijos a propósito
 * sobre una base descartable).
 *
 * ⚠️ TestCase PURO a propósito, igual que `RenovarLeaseFiltraPorItemTest`/
 * `RawWritesDontTouchBloqueoFlagsTest` de este mismo directorio: `Tests\TestCase` corre
 * `migrate:fresh --seed` contra la base compartida de dev si `DB_DATABASE` no apunta a una base
 * `_test` real. El fix es mecánico (un guard en un hook de Eloquent) y se verifica leyendo el
 * código fuente, sin necesidad de tocar BD — la verificación EN VIVO (rechazo real vía tinker +
 * alta normal vía `RoadmapIntakeService::crear()` + suite completa de Roadmap con
 * `DB_DATABASE=megaisp_test`) se hizo aparte, a mano, al cerrar el item.
 */
class GuardIdExplicitoRoadmapItemsTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function modelo(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Models/RoadmapItem.php';
        $this->assertFileExists($f, 'Se movió/renombró el modelo: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    /** El primer `static::creating` de `booted()` debe ser el guard, no otro hook cualquiera. */
    private function primerCreatingDeBooted(): string
    {
        $fuente = $this->modelo();

        $inicio = strpos($fuente, 'protected static function booted()');
        $this->assertNotFalse($inicio, 'No encontré booted(): el candado ya no puede localizar el guard.');

        $pos = strpos($fuente, 'static::creating(function', $inicio);
        $this->assertNotFalse($pos, 'booted() ya no registra ningún static::creating().');

        // Balancea llaves desde el `function (self $item) {` hasta su cierre, para extraer
        // exactamente el cuerpo del primer hook `creating`.
        $aperturaFn = strpos($fuente, '{', $pos);
        $this->assertNotFalse($aperturaFn);

        $profundidad = 0;
        $cierre = null;
        for ($i = $aperturaFn; $i < strlen($fuente); $i++) {
            if ($fuente[$i] === '{') {
                $profundidad++;
            } elseif ($fuente[$i] === '}') {
                $profundidad--;
                if ($profundidad === 0) {
                    $cierre = $i;
                    break;
                }
            }
        }
        $this->assertNotNull($cierre, 'No pude balancear las llaves del primer static::creating().');

        return substr($fuente, $pos, $cierre - $pos + 1);
    }

    public function test_el_primer_creating_de_booted_es_el_guard_de_id_explicito(): void
    {
        $bloque = $this->primerCreatingDeBooted();

        $this->assertStringContainsString('getKeyName()', $bloque,
            'El guard debe leer la PK real del modelo (getKeyName()), no asumir la columna "id" a mano.');

        $this->assertStringContainsString('RuntimeException', $bloque,
            'El guard debe rechazar la inserción lanzando una excepción, no solo registrar un warning '
            . '(un warning no impide el INSERT que despega el AUTO_INCREMENT).');

        $this->assertMatchesRegularExpression('/id\s*explícito/u', $bloque,
            'El mensaje de la excepción debe explicar POR QUÉ está prohibido (id explícito), no solo decir "no".');
    }

    public function test_el_guard_exime_a_la_suite_de_tests(): void
    {
        $bloque = $this->primerCreatingDeBooted();

        $this->assertStringContainsString('runningUnitTests()', $bloque,
            'Sin esta excepción, los tests hermanos que siembran ids fijos '
            . '(DiagnosticoItemServiceTest, ColisionPorTablaMigracionTest) se romperían.');
    }

    public function test_el_mensaje_del_guard_recomienda_la_via_de_alta_correcta(): void
    {
        $bloque = $this->primerCreatingDeBooted();

        $this->assertStringContainsString('RoadmapIntakeService::crear', $bloque,
            'El mensaje del guard debe apuntar al punto único de alta (RoadmapIntakeService::crear()), '
            . 'para que quien lo encuentre sepa cómo seguir sin insertar con id explícito.');
    }

    /**
     * Los dos tests hermanos que siembran ids fijos a propósito deben seguir existiendo: si
     * alguien los borra "porque ya no hacen falta", este candado deja de tener sentido y nadie se
     * entera de que la excepción de `runningUnitTests()` ya no protege ningún caso real.
     */
    public function test_los_tests_hermanos_que_siembran_ids_fijos_siguen_existiendo(): void
    {
        $this->assertFileExists($this->raiz() . '/tests/Unit/Modules/Addons/Roadmap/DiagnosticoItemServiceTest.php');
        $this->assertFileExists($this->raiz() . '/tests/Unit/Modules/Addons/Roadmap/ColisionPorTablaMigracionTest.php');
    }
}
