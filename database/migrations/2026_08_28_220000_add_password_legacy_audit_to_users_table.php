<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #153 (q3, decisión de Irving): columnas aditivas de auditoría/rollback
 * para la migración base64→bcrypt de `users.password`.
 *
 * `password_legacy` guarda el valor base64 anterior justo antes de re-hashear
 * (tanto el upgrade-on-login de LoginController como el backfill
 * `auth:rehash-passwords` lo escriben); `password_migrated_at` marca cuándo.
 * `down()` restaura: para toda fila con `password_legacy` no nulo, regresa
 * `password` a ese valor (deshace el re-hash) antes de tirar las columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'password_legacy')) {
                $table->string('password_legacy', 255)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'password_migrated_at')) {
                $table->timestamp('password_migrated_at')->nullable()->after('password_legacy');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_legacy')) {
            DB::table('users')
                ->whereNotNull('password_legacy')
                ->update(['password' => DB::raw('password_legacy')]);
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_migrated_at')) {
                $table->dropColumn('password_migrated_at');
            }
            if (Schema::hasColumn('users', 'password_legacy')) {
                $table->dropColumn('password_legacy');
            }
        });
    }
};
