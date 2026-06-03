<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Item #73 — Settings de MegaFamilia persistentes en BD (antes vivían solo en
// Cache::forever, que cache:clear borraba silenciosamente). BD = fuente, cache = acelerador.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('megafamilia_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // ej. firebase_server_key
            $table->text('value')->nullable();         // JSON-encoded; cifrado si encrypted=true
            $table->boolean('encrypted')->default(false);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('megafamilia_settings');
    }
};
