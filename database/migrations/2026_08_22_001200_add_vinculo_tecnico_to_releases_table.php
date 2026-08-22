<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('commit_sha', 64)->nullable()->after('release_date');
            $table->string('migracion_desde', 180)->nullable()->after('commit_sha');
            $table->string('migracion_hasta', 180)->nullable()->after('migracion_desde');
            $table->string('snapshot_bd', 255)->nullable()->after('migracion_hasta');
            $table->timestamp('aplicada_en_dev_at')->nullable()->after('snapshot_bd');
            $table->timestamp('aplicada_en_prod_at')->nullable()->after('aplicada_en_dev_at');
            $table->boolean('reversible')->nullable()->after('aplicada_en_prod_at');
            $table->string('reversible_motivo', 255)->nullable()->after('reversible');
        });

        // Backfill (item #1017): las versiones que ya existían antes de este vínculo técnico
        // no tienen commit/migraciones/snapshot reales que inferir — NO se inventan hacia atrás.
        // Quedan marcadas explícitamente como no reversibles, con el motivo visible.
        DB::table('releases')->update([
            'reversible'        => false,
            'reversible_motivo' => 'Sin vínculo técnico — release previa al rastreo de commit/migraciones (item #1017).',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn([
                'commit_sha',
                'migracion_desde',
                'migracion_hasta',
                'snapshot_bd',
                'aplicada_en_dev_at',
                'aplicada_en_prod_at',
                'reversible',
                'reversible_motivo',
            ]);
        });
    }
};
