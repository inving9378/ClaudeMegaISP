<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JARVIS — archivos que el usuario le manda al chat (fotos, PDF, Excel, Word, video, audio) y los
 * que JARVIS genera (Excel de resultados). Los bytes viven en storage/app/asistente/adjuntos y se
 * borran solos (expira_at, «tema nuevo»); la fila queda como constancia con borrado_at.
 *
 * ADITIVA e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asistente_adjuntos')) {
            return;
        }

        Schema::create('asistente_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('conversacion_id')->nullable()->index();
            $table->unsignedBigInteger('mensaje_id')->nullable()->index();
            $table->string('origen', 20)->default('usuario'); // usuario | jarvis
            $table->string('nombre', 255);
            $table->string('mime', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('tipo', 20)->nullable(); // imagen | pdf | hoja | documento | video | audio
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('carpeta', 255);
            $table->json('meta')->nullable();
            $table->string('estado', 20)->default('subiendo')->index(); // subiendo | listo | error | borrado
            $table->string('error', 500)->nullable();
            $table->timestamp('expira_at')->nullable()->index();
            $table->timestamp('borrado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_adjuntos');
    }
};
