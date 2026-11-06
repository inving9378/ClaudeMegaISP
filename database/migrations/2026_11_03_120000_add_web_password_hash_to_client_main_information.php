<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// #1155 — Hash bcrypt de la Contraseña WEB del portal. Aditiva: la columna legacy `password` NO se toca.
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('client_main_information', 'web_password_hash')) {
            Schema::table('client_main_information', function (Blueprint $t) {
                $t->string('web_password_hash', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_main_information', 'web_password_hash')) {
            Schema::table('client_main_information', function (Blueprint $t) {
                $t->dropColumn('web_password_hash');
            });
        }
    }
};
