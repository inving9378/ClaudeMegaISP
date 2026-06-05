<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_work_order_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')
                ->constrained('talento_work_orders')
                ->cascadeOnDelete();
            $table->enum('motivo', [
                'cliente_ausente',
                'sin_acceso',
                'falta_material',
                'olt_sin_senal',
                'riesgo',
                'otro',
            ]);
            $table->string('nota', 1000)->nullable(); // obligatorio cuando motivo=otro
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('server_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_incidents');
    }
};
