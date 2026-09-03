<?php

namespace Tests\Unit\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Services\ReleaseChangelogService;
use Mockery;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.
use ReflectionMethod;

/**
 * Item roadmap #892, Fase 3: el resumen jerárquico (map-reduce) computa el diff --stat de CADA
 * lote por separado vía computeBatchStat(), y ese método SIEMPRE pasa por filterSensitiveStat()
 * — el mismo punto único que ya protegía el (antes único) diff del rango completo. Esta prueba
 * fija que filterSensitiveStat() sigue filtrando TODOS los patrones sensibles sin importar en
 * qué lote (1, 2, ..., N) se invoque — si algún día un lote posterior tuviera su propio camino
 * que se saltara este filtro, un secreto llegaría al prompt de la IA.
 */
class ReleaseChangelogServiceSensitiveStatTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function filterSensitiveStat(string $stat): string
    {
        // Mockery::mock() no ejecuta el constructor real de ClaudeApiClient (que resuelve la
        // API key contra BD/env) — aquí no se llama ningún método del cliente, solo se necesita
        // una instancia válida para poder invocar el método privado por reflexión.
        $service = new ReleaseChangelogService(Mockery::mock(ClaudeApiClient::class));

        $method = new ReflectionMethod(ReleaseChangelogService::class, 'filterSensitiveStat');
        $method->setAccessible(true);

        return $method->invoke($service, $stat);
    }

    public function test_filtra_env_en_un_stat_que_simula_un_lote_posterior(): void
    {
        // Simula el diff --stat de un lote 2..N (NO el primero) que toca un archivo sensible.
        $statLotePosterior = <<<STAT
        app/Services/Foo.php | 4 ++--
        .env | 2 +-
        2 files changed, 5 insertions(+), 2 deletions(-)
        STAT;

        $filtrado = $this->filterSensitiveStat($statLotePosterior);

        $this->assertStringNotContainsString('.env', $filtrado);
        $this->assertStringContainsString('Foo.php', $filtrado);
        $this->assertStringContainsString('files changed', $filtrado);
    }

    public function test_filtra_todos_los_patrones_sensibles_declarados(): void
    {
        $stat = <<<STAT
        app/Services/Bar.php | 1 +
        secrets.pem | 1 +
        deploy.key | 1 +
        credentials.json | 1 +
        app_secret_rotation.php | 1 +
        5 files changed, 5 insertions(+)
        STAT;

        $filtrado = $this->filterSensitiveStat($stat);

        $this->assertStringNotContainsString('secrets.pem', $filtrado);
        $this->assertStringNotContainsString('deploy.key', $filtrado);
        $this->assertStringNotContainsString('credentials.json', $filtrado);
        $this->assertStringNotContainsString('app_secret_rotation.php', $filtrado);
        $this->assertStringContainsString('Bar.php', $filtrado);
    }
}
