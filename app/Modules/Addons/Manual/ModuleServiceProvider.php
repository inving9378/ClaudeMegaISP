<?php

namespace App\Modules\Addons\Manual;

use App\Modules\Addons\Manual\Commands\RegenerateManualCommand;
use App\Modules\Addons\Manual\Listeners\RegenerateManualAfterMigrate;
use App\Modules\Addons\Manual\Observers\ModuleObserver;
use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-manual';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-manual';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegenerateManualCommand::class,
            ]);
        }

        Event::listen(MigrationEnded::class, [RegenerateManualAfterMigrate::class, 'handleSingle']);
        Event::listen(MigrationsEnded::class, [RegenerateManualAfterMigrate::class, 'handleEnded']);

        ModuleRegistry::observe(ModuleObserver::class);
    }
}
