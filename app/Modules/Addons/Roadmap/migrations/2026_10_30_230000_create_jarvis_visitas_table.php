<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1068 — «¿En qué nos quedamos?»: cuándo abrió cada persona por última vez la conversación con
 * Jarvis en la Torre, para resumirle solo lo que pasó desde entonces. La memoria vive en la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jarvis_visitas')) {
            Schema::create('jarvis_visitas', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->timestamp('visto_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jarvis_visitas');
    }
};
