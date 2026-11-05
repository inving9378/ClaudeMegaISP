<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNI-T D8: responsable principal y participantes de una orden (aditivo; task_user no se toca). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_order_participants')) {
            return;
        }
        Schema::create('talento_order_participants', function (Blueprint $t) {
            $t->id();
            $t->string('origen', 20);              // task | work_order
            $t->unsignedBigInteger('orden_id');
            $t->unsignedBigInteger('user_id');
            $t->string('rol', 20)->default('participante'); // principal | participante
            $t->string('aporte', 255)->nullable();
            $t->timestamps();
            $t->unique(['origen', 'orden_id', 'user_id'], 'tl_order_part_unique');
            $t->index(['origen', 'orden_id']);
        });
    }

    public function down(): void
    {
        // Aditiva a propósito: conserva el historial de contribuciones.
    }
};
