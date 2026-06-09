<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            // Guarda version/title/summary/release_date del webhook para que el
            // scheduler (remote:run-pending-deploys) los recupere al ejecutar el deploy.
            $table->json('payload')->nullable()->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
