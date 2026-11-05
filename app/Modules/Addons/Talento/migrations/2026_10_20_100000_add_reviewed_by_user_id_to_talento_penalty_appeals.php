<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un administrador sin ficha de colaborador resuelve apelaciones firmando como
 * MEGAISP (2026-10-20, mismo criterio que confirmar una penalización). reviewed_by
 * guarda esa ficha; esta columna guarda a la persona que de verdad resolvió, para
 * poder decir quién fue y aplicar "quien aplicó no resuelve" entre dos admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('talento_penalty_appeals', 'reviewed_by_user_id')) {
            return;
        }
        Schema::table('talento_penalty_appeals', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_penalty_appeals', 'reviewed_by_user_id')) {
            Schema::table('talento_penalty_appeals', function (Blueprint $table) {
                $table->dropColumn('reviewed_by_user_id');
            });
        }
    }
};
