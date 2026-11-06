<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #499 — política de PIN decidida por Irving (brief del item, q3): 6 dígitos,
 * 3 intentos, bloqueo con desbloqueo manual del padre. Requiere contador de intentos
 * fallidos + marca de bloqueo, aditivo sobre `pin_hash` (ya agregado en #498).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parental_profiles')) {
            return;
        }

        Schema::table('parental_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('parental_profiles', 'pin_attempts')) {
                $table->unsignedTinyInteger('pin_attempts')->default(0)->after('pin_hash');
            }
            if (! Schema::hasColumn('parental_profiles', 'pin_locked_at')) {
                $table->timestamp('pin_locked_at')->nullable()->after('pin_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parental_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('parental_profiles', 'pin_locked_at')) {
                $table->dropColumn('pin_locked_at');
            }
            if (Schema::hasColumn('parental_profiles', 'pin_attempts')) {
                $table->dropColumn('pin_attempts');
            }
        });
    }
};
