<?php

namespace Tests\Feature\Seguridad;

use App\Console\Commands\Active\RemoteDeployCommand;
use App\Models\Core\ApiIntegration;
use App\Services\Updates\GitHubUpdateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

/**
 * Seguridad V1.64: con el repositorio privado, las actualizaciones usan el token de lectura del
 * Hub (Integraciones → GitHub) y, si falta o no sirve, la pantalla lo dice en vez de «no se pudo
 * verificar». El deploy pasa el token a git por variables de entorno solo con origen https.
 * Sin red: GitHub se simula con Http::fake.
 */
class GithubTokenLecturaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['updates.enabled' => true, 'updates.repo' => 'org/repo', 'updates.read_token' => '']);
        DB::table('api_integrations')->where('provider', 'github')->delete();
    }

    private function revisar(): ?array
    {
        $servicio = app(GitHubUpdateService::class);
        $servicio->clearCache();

        return $servicio->check();
    }

    private function tokenEnElHub(string $token): void
    {
        $i = new ApiIntegration(['company_id' => 1, 'provider' => 'github', 'type' => 'servicios', 'slug' => 'github-prueba',
            'name' => 'GitHub', 'active' => true, 'is_default_for_provider' => true]);
        $i->value = $token;
        $i->save();
    }

    public function test_repo_privado_sin_token_lo_dice_tal_cual(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

        $r = $this->revisar();

        $this->assertTrue($r['check_failed']);
        $this->assertSame('repo_privado_sin_token', $r['motivo']);
        $this->assertStringContainsString('Integraciones → GitHub', $r['error']);
    }

    public function test_usa_el_token_del_hub_y_avisa_si_no_es_valido(): void
    {
        $this->tokenEnElHub('github_pat_prueba');
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        $r = $this->revisar();

        Http::assertSent(fn (Request $req) => $req->header('Authorization') === ['Bearer github_pat_prueba']);
        $this->assertSame('token_invalido', $r['motivo']);
        $this->assertSame('github_pat_prueba', GitHubUpdateService::tokenDeLectura());
    }

    public function test_el_deploy_pasa_el_token_a_git_solo_con_origen_https(): void
    {
        $this->tokenEnElHub('github_pat_prueba');
        $entorno = function (string $origen): array {
            $cmd = new class extends RemoteDeployCommand {
                public string $origen = '';
                protected function origenGit(): string { return $this->origen; }
                public function entorno(): array { return $this->buildEnv(); }
            };
            $cmd->origen = $origen;

            return $cmd->entorno();
        };

        $https = $entorno('https://github.com/org/repo.git');
        $this->assertSame('2', $https['GIT_CONFIG_COUNT']);
        $this->assertSame('safe.directory', $https['GIT_CONFIG_KEY_0']);
        $this->assertSame('http.https://github.com/.extraheader', $https['GIT_CONFIG_KEY_1']);
        $this->assertSame('AUTHORIZATION: basic ' . base64_encode('x-access-token:github_pat_prueba'), $https['GIT_CONFIG_VALUE_1']);

        $ssh = $entorno('git@github.com:org/repo.git');
        $this->assertSame('1', $ssh['GIT_CONFIG_COUNT'], 'Con llave SSH no se agrega el token.');
    }
}
