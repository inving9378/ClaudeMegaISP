<?php

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
        Schema::table('internets', function (Blueprint $table) {
            $table->decimal('price', 20, 2)->change();
        });
          Schema::table('customs', function (Blueprint $table) {
            $table->decimal('price', 20, 2)->change();
        });
         Schema::table('voises', function (Blueprint $table) {
            $table->decimal('price', 20, 2)->change();
        });
         Schema::table('bundles', function (Blueprint $table) {
            $table->decimal('price', 20, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internets', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });
        Schema::table('customs', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });
        Schema::table('voises', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });
        Schema::table('bundles', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });
    }
};
