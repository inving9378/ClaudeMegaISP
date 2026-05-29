<?php

namespace Tests\Feature\Core;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use Database\Seeders\Core\MigrateExistingKeysToHubSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MigrationFromLegacySeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_is_idempotent(): void
    {
        $seeder = new MigrateExistingKeysToHubSeeder();
        $seeder->setCommand(new class {
            public function info(string $msg): void {}
            public function error(string $msg): void {}
        });

        $seeder->run();
        $countAfterFirst = ApiIntegration::forCompany(1)->count();

        $seeder->run();
        $countAfterSecond = ApiIntegration::forCompany(1)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond, 'Seeder should be idempotent');
    }

    public function test_seeder_creates_evolution_integration(): void
    {
        $seeder = new MigrateExistingKeysToHubSeeder();
        $seeder->setCommand(new class {
            public function info(string $msg): void {}
        });
        $seeder->run();

        $this->assertDatabaseHas('api_integrations', [
            'company_id' => 1,
            'provider'   => 'evolution',
            'slug'       => 'evolution-default',
        ]);
    }

    public function test_seeder_creates_google_maps_integration(): void
    {
        $seeder = new MigrateExistingKeysToHubSeeder();
        $seeder->setCommand(new class {
            public function info(string $msg): void {}
        });
        $seeder->run();

        $this->assertDatabaseHas('api_integrations', [
            'company_id' => 1,
            'provider'   => 'google_maps',
            'slug'       => 'google-maps-default',
        ]);
    }

    public function test_seeder_does_not_log_key_values(): void
    {
        $seeder = new MigrateExistingKeysToHubSeeder();
        $seeder->setCommand(new class {
            public function info(string $msg): void {}
        });
        $seeder->run();

        $logs = ApiIntegrationLog::where('company_id', 1)->get();
        foreach ($logs as $log) {
            $meta = json_encode($log->metadata);
            $this->assertStringNotContainsString('sk-ant-', $meta, 'Log must not contain key values');
            $this->assertStringNotContainsString('c0227ee5', $meta, 'Log must not contain Evolution key');
            $this->assertStringNotContainsString('AIza', $meta, 'Log must not contain Google Maps key');
        }
    }
}
