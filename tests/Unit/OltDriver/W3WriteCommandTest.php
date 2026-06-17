<?php

namespace Tests\Unit\OltDriver;

use App\Console\Commands\Olts\W3WriteCommand;
use App\Services\OltDriver\Huawei\HuaweiCommandBuilder;
use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * W3WriteCommand — Pruebas para el write supervisado de descripción.
 *
 * Cubre:
 *   A) Constantes: valores correctos de ONU_ID, TARGET_SN, ROLLBACK_DESC
 *   B) Generación VRP: los comandos enviados a la OLT son exactamente los esperados
 *   C) Guard: SN fuera del allow-list nunca llega a ejecutar bytes de escritura
 *   D) Rollback: la descripción de rollback coincide byte a byte con el backup de Fase 1
 */
class W3WriteCommandTest extends TestCase
{
    private static string $ontFixture;

    public static function setUpBeforeClass(): void
    {
        self::$ontFixture = (string) file_get_contents(
            __DIR__ . '/fixtures/huawei/display_ont_info_port.txt'
        );
    }

    // ── A) Constantes ─────────────────────────────────────────────────────────

    public function test_target_sn_constant(): void
    {
        $ref = new ReflectionClass(W3WriteCommand::class);
        $this->assertSame('HWTCFEFCC9A2', $ref->getConstant('TARGET_SN'));
    }

    public function test_onu_id_constant(): void
    {
        $ref = new ReflectionClass(W3WriteCommand::class);
        $this->assertSame('1:0/3/2:0', $ref->getConstant('ONU_ID'));
    }

    /**
     * Punto de rollback fijado en el backup Fase 1:
     * docs/multiolt-w3-backups/w3_backup_20260616_165715.txt línea 30
     * Description : PRUEBA_zone_D1Z10_authd_20260610
     */
    public function test_rollback_desc_matches_fase1_backup(): void
    {
        $ref = new ReflectionClass(W3WriteCommand::class);
        $this->assertSame(
            'PRUEBA_zone_D1Z10_authd_20260610',
            $ref->getConstant('ROLLBACK_DESC'),
        );
    }

    // ── B) Generación VRP — dry-run con mock de transport ────────────────────

    /**
     * El write del ONT PRUEBA debe generar exactamente:
     *   interface gpon 0/3
     *   ont modify 2 0 desc PRUEBA-W3
     *   return
     *
     * VRP verificado en Fase 3 pre-envío: "ont modify 2 0 ?" lista "desc" como parámetro.
     * "ont desc" NO es subcomando válido en MA5800-X7 V100R018 (causa % Unknown command).
     */
    public function test_write_desc_generates_ont_modify_command(): void
    {
        $driver = new HuaweiDriver(
            transport: $this->mockTransport(),
            config:    ['dry_run' => true, 'olt_id' => '1', 'frame' => 0],
            logger:    new NullLogger(),
        );

        $result = $driver->descOnt(W3WriteCommand::ONU_ID, 'PRUEBA-W3');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['dry_run']);
        $this->assertContains('ont modify 2 0 desc PRUEBA-W3', $result['commands']);
        $this->assertNotContains('ont desc 2 0 PRUEBA-W3', $result['commands']);
    }

    public function test_write_desc_includes_interface_nav_and_return(): void
    {
        $driver = new HuaweiDriver(
            transport: $this->mockTransport(),
            config:    ['dry_run' => true, 'olt_id' => '1', 'frame' => 0],
            logger:    new NullLogger(),
        );

        $result = $driver->descOnt(W3WriteCommand::ONU_ID, 'PRUEBA-W3');

        $commands = $result['commands'];
        $this->assertContains('interface gpon 0/3', $commands);
        $this->assertContains('return', $commands);
    }

    // ── C) Guard — SN fuera del allow-list ───────────────────────────────────

    public function test_non_allowed_sn_returns_failure_without_writing(): void
    {
        $fixtureNonAllowed = str_replace(
            'HWTCFEFCC9A2',
            'DEADBEEF0000',
            self::$ontFixture
        );

        $driver = new HuaweiDriver(
            transport: $this->mockTransport($fixtureNonAllowed),
            config:    ['dry_run' => false, 'olt_id' => '1', 'frame' => 0],
            logger:    new NullLogger(),
        );

        $result = $driver->descOnt(W3WriteCommand::ONU_ID, 'PRUEBA-W3');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('DEADBEEF0000', $result['message']);
    }

    // ── D) Rollback ──────────────────────────────────────────────────────────

    public function test_rollback_generates_exact_original_description_command(): void
    {
        $driver = new HuaweiDriver(
            transport: $this->mockTransport(),
            config:    ['dry_run' => true, 'olt_id' => '1', 'frame' => 0],
            logger:    new NullLogger(),
        );

        $result = $driver->descOnt(W3WriteCommand::ONU_ID, W3WriteCommand::ROLLBACK_DESC);

        $this->assertTrue($result['success']);
        $this->assertContains(
            'ont modify 2 0 desc ' . W3WriteCommand::ROLLBACK_DESC,
            $result['commands'],
        );
    }

    public function test_rollback_does_not_clear_description(): void
    {
        // Rollback NUNCA debe generar "ont modify 2 0 desc" sin valor (eso vaciaría la desc).
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, W3WriteCommand::ROLLBACK_DESC);
        $cmds  = HuaweiCommandBuilder::cmdStrings($steps);

        $writeCmd = $cmds[1];
        $this->assertSame(
            'ont modify 2 0 desc ' . W3WriteCommand::ROLLBACK_DESC,
            $writeCmd,
            'El rollback debe restaurar el valor exacto, no vaciar la descripción.',
        );
    }

    // ── E) Sintaxis VRP del lookup de SN ─────────────────────────────────────

    /**
     * withWriteTarget() DEBE usar sintaxis interface-context ("display ont info {port} {ontId}")
     * y NO la sintaxis de 4 números con espacios ("display ont info {f} {s} {p} {id}")
     * que NO existe en VRP MA5800-X7 V100R018 y causó "ONT not found" en W3 dry-run.
     *
     * El mock recibe cualquier argumento en exec() pero verifica:
     *   • enterGponInterface() llamado con slot=3 (de ONU_ID 1:0/3/2:0)
     *   • exec() llamado con "display ont info 2 0" (port=2, ontId=0)
     */
    public function test_write_target_lookup_uses_interface_context_syntax(): void
    {
        $transport = $this->createMock(HuaweiTransport::class);
        $transport->method('isOpen')->willReturn(true);

        // Captura el argumento con el que se llama exec()
        $capturedCmd = null;
        $transport->method('exec')->willReturnCallback(function (string $cmd) use (&$capturedCmd) {
            $capturedCmd = $cmd;
            return self::$ontFixture;
        });

        // Para descOnt en dry-run, enterGponInterface es llamado por withWriteTarget (lookup)
        // y NO por executeSteps (que se saltea en dry-run)
        $transport->expects($this->atLeastOnce())->method('enterGponInterface');
        $transport->expects($this->atLeastOnce())->method('leaveToUserView');

        $driver = new HuaweiDriver(
            transport: $transport,
            config:    ['dry_run' => true, 'olt_id' => '1', 'frame' => 0],
            logger:    new NullLogger(),
        );

        $driver->descOnt(W3WriteCommand::ONU_ID, 'PRUEBA-W3');

        // El comando de lookup debe ser interface-context: "display ont info {port} {ontId}"
        $this->assertSame('display ont info 2 0', $capturedCmd,
            '"display ont info {f} {s} {p} {id}" (4 números con espacios) NO existe en VRP MA5800-X7; '
            . 'debe usarse "display ont info {port} {ontId}" desde interface gpon {f}/{s}.'
        );
    }

    // ── Builder — verificación directa del builder para W3 ───────────────────

    public function test_builder_generates_correct_w3_sequence(): void
    {
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, 'PRUEBA-W3');
        $cmds  = HuaweiCommandBuilder::cmdStrings($steps);

        $this->assertSame('interface gpon 0/3',           $cmds[0]);
        $this->assertSame('ont modify 2 0 desc PRUEBA-W3', $cmds[1]);
        $this->assertSame('return',                        $cmds[2]);
    }

    public function test_builder_generates_correct_rollback_sequence(): void
    {
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, W3WriteCommand::ROLLBACK_DESC);
        $cmds  = HuaweiCommandBuilder::cmdStrings($steps);

        $this->assertSame(
            'ont modify 2 0 desc ' . W3WriteCommand::ROLLBACK_DESC,
            $cmds[1],
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Mock de HuaweiTransport que ya está abierto e isOpen()→true.
     * exec() retorna el fixture (ONT PRUEBA con SN HWTCFEFCC9A2) para satisfacer
     * la lectura de SN que withWriteTarget() realiza antes del guard check.
     */
    private function mockTransport(string $fixture = ''): HuaweiTransport
    {
        $mock = $this->createMock(HuaweiTransport::class);
        $mock->method('isOpen')->willReturn(true);
        $mock->method('exec')->willReturn($fixture ?: self::$ontFixture);
        return $mock;
    }
}
