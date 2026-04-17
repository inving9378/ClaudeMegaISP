<?php

use Doctrine\DBAL\Types\SmallIntType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('map_layers', function (Blueprint $table) {
            $table->integer('level')->after('inputs')->default(1000000);
        });
        Schema::table('map_proyects', function (Blueprint $table) {
            $table->integer('level')->after('classification')->default(1000000);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropColumn('level');
        });
        Schema::table('map_proyects', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
