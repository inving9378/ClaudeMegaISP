<?php

namespace Tests\Unit\Modules\Addons\ModuleManager;

use App\Modules\Core\ModuleManager\Services\ModuleLifecycleService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * Item #669 — `keep_data` de module.json era INERTE: nadie la leía, así que un módulo que la
 * declaraba (ej. PortalPago) NO estaba protegido de una desinstalación destructiva pese a
 * parecerlo. `ModuleLifecycleService::resolveKeepData()` ahora la honra: si el manifiesto declara
 * `"keep_data": true`, el parámetro `$keepData` de `uninstall()` (que viene del request/comando)
 * se ignora y se fuerza a true.
 *
 * Se invoca el método privado por reflexión para probar la regla en aislamiento, sin bootear
 * Laravel ni tocar BD (mismo patrón que ValvulaGuardaTerminoPresenteTest).
 */
class ModuleLifecycleServiceKeepDataTest extends TestCase
{
    private function resolveKeepData(array $manifest, bool $requested): bool
    {
        $service = new ModuleLifecycleService();
        $method  = new \ReflectionMethod($service, 'resolveKeepData');
        $method->setAccessible(true);

        return $method->invoke($service, $manifest, $requested);
    }

    public function test_manifiesto_con_keep_data_true_gana_aunque_la_llamada_pida_false(): void
    {
        $manifest = ['slug' => 'addon-portal-pago', 'keep_data' => true];

        $this->assertTrue($this->resolveKeepData($manifest, false));
    }

    public function test_manifiesto_con_keep_data_true_respeta_true_explicito(): void
    {
        $manifest = ['slug' => 'addon-portal-pago', 'keep_data' => true];

        $this->assertTrue($this->resolveKeepData($manifest, true));
    }

    public function test_manifiesto_sin_keep_data_respeta_lo_que_pida_la_llamada(): void
    {
        $manifest = ['slug' => 'addon-cualquiera'];

        $this->assertFalse($this->resolveKeepData($manifest, false));
        $this->assertTrue($this->resolveKeepData($manifest, true));
    }

    public function test_manifiesto_con_keep_data_false_respeta_lo_que_pida_la_llamada(): void
    {
        $manifest = ['slug' => 'addon-cualquiera', 'keep_data' => false];

        $this->assertFalse($this->resolveKeepData($manifest, false));
        $this->assertTrue($this->resolveKeepData($manifest, true));
    }

    public function test_valor_no_booleano_de_keep_data_no_se_trata_como_proteccion(): void
    {
        // Defensa en profundidad: solo el bool(true) estricto protege. Un valor "truthy" raro
        // en el JSON (ej. string) no debe activar la protección silenciosamente.
        $manifest = ['slug' => 'addon-raro', 'keep_data' => 'true'];

        $this->assertFalse($this->resolveKeepData($manifest, false));
    }
}
