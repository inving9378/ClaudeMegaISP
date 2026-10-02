<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('api_integrations', 'type')) {
            Schema::table('api_integrations', function (Blueprint $table) {
                $table->string('type', 20)->default('servicios')->after('provider')->index();
            });
        }

        // Backfill: proveedores de IA existentes
        DB::table('api_integrations')
            ->whereIn('provider', ['anthropic', 'anthropic-legacy', 'openai'])
            ->update(['type' => 'ia']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('api_integrations', 'type')) {
            Schema::table('api_integrations', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};
