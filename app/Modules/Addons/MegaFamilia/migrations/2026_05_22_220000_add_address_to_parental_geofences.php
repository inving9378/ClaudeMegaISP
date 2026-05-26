<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parental_geofences', function (Blueprint $t) {
            $t->string('address', 500)->nullable()->after('name');
            // Permitir categorías libres (Casa/Escuela/Trabajo/...) en vez del enum estrecho.
            $t->string('type', 64)->default('home')->change();
        });
    }

    public function down(): void
    {
        Schema::table('parental_geofences', function (Blueprint $t) {
            $t->dropColumn('address');
            $t->enum('type', ['home', 'school', 'family'])->default('home')->change();
        });
    }
};
