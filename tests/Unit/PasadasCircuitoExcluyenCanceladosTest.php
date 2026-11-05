<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** #701 — las pasadas automáticas no deben tocar items cancelados/completados/rechazados. */
class PasadasCircuitoExcluyenCanceladosTest extends TestCase
{
    public function test_las_tres_pasadas_excluyen_status_cancelled_y_estados_cerrados(): void
    {
        foreach (['PriorizarSeguridadCommand', 'DestrabeCommand', 'BriefCCommand'] as $cmd) {
            $src = file_get_contents(__DIR__ . '/../../app/Modules/Addons/Roadmap/Console/' . $cmd . '.php');
            $this->assertStringContainsString("['done', 'cancelled', 'cancelado']", $src, $cmd);
            $this->assertStringContainsString("['completado', 'cancelado', 'rechazado']", $src, $cmd);
        }
    }

    public function test_priorizar_seguridad_registra_cambio_de_estado_en_log(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Modules/Addons/Roadmap/Console/PriorizarSeguridadCommand.php');
        $this->assertStringContainsString('cambio_estado_priorizar_seguridad', $src);
    }
}
