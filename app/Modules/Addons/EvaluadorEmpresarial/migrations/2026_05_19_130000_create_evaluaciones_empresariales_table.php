<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_empresariales', function (Blueprint $table) {
            $table->id();

            $table->string('nombre_contacto');
            $table->string('empresa');
            $table->string('email_contacto')->nullable();
            $table->string('whatsapp_contacto', 20)->nullable();
            $table->string('cargo')->nullable();

            $table->unsignedSmallInteger('puntaje_total')->default(0);
            $table->string('categoria', 30);
            $table->string('plan_recomendado', 100)->nullable();

            $table->json('respuestas_json');

            $table->unsignedSmallInteger('score_criticidad')->default(0);
            $table->unsignedSmallInteger('score_redundancia')->default(0);
            $table->unsignedSmallInteger('score_ancho_banda')->default(0);
            $table->unsignedSmallInteger('score_sla')->default(0);

            $table->enum('canal_origen', ['whatsapp', 'email', 'directo', 'vendedor'])->default('directo');
            $table->unsignedBigInteger('vendedor_id')->nullable();

            $table->unsignedBigInteger('lead_id')->nullable();

            $table->string('token_publico', 64)->unique()->nullable();
            $table->timestamp('completado_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('categoria');
            $table->index('vendedor_id');
            $table->index('lead_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_empresariales');
    }
};
