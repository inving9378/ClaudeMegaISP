<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #858 — la tabla `permissions` no tiene columna `description`, pero
 * ModuleLifecycleService::registerPermissions() y
 * PermissionSyncService::syncFromModuleManifests() ya la escriben al crear un
 * permiso nuevo → truena "Unknown column description". Additive: nullable,
 * sin default (los permisos existentes se llenan en la migración de backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'description')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('description')->nullable()->after('context');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'description')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
