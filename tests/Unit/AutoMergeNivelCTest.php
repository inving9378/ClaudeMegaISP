<?php

namespace Tests\Unit;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use Tests\TestCase;

/** #1065 — candado del auto-merge de nivel C (interruptor + condición 1). */
class AutoMergeNivelCTest extends TestCase
{
    public function test_interruptor_apagado_bloquea_nivel_c(): void
    {
        config(['circuito.jarvis.automerge.nivel_c.enabled' => false]);
        $i = new RoadmapItem(['nivel_riesgo' => 'C', 'title' => 'x', 'description' => 'y']);
        $this->assertFalse(app(JarvisService::class)->elegibleAutoMerge($i)['elegible']);
    }

    public function test_encendido_exige_aprobacion_de_irving(): void
    {
        config(['circuito.jarvis.automerge.nivel_c.enabled' => true]);
        $i = new RoadmapItem(['nivel_riesgo' => 'C', 'title' => 'x', 'description' => 'y']);
        $r = app(JarvisService::class)->elegibleAutoMerge($i);
        $this->assertFalse($r['elegible']);
        $this->assertStringContainsString('aprobación de Irving', $r['motivo']);
    }

    public function test_dropcolumn_de_columna_propia_en_down_no_es_destructivo(): void
    {
        $m = new \ReflectionMethod(JarvisService::class, 'sinReversionPropia');
        $m->setAccessible(true);
        $mig = "<?php\nreturn new class {\n    public function up(): void\n    {\n        \$t->boolean('x');\n    }\n\n    public function down(): void\n    {\n        \$t->dropColumn('x');\n    }\n};\n";
        $this->assertStringNotContainsStringIgnoringCase('dropColumn', $m->invoke(app(JarvisService::class), $mig));
        $this->assertStringContainsStringIgnoringCase('dropColumn', $m->invoke(app(JarvisService::class), str_replace("dropColumn('x')", "dropColumn('y')", $mig)));
    }
}
