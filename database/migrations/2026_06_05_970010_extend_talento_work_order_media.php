<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_order_media', function (Blueprint $table) {
            // Tipo de evidencia del catálogo v1
            $table->foreignId('evidence_type_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained('talento_evidence_types')
                ->nullOnDelete();

            // Hora exacta en que el servidor recibió la foto (no la de la app)
            $table->timestamp('server_captured_at')->nullable()->after('captured_at');

            // Texto justificatorio (obligatorio para tipo "Otro")
            $table->string('justificacion', 500)->nullable()->after('potencia_dbm');

            // Flag de mock-location reportado por el dispositivo Android
            $table->boolean('is_mock_location')->default(false)->after('location_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('talento_work_order_media', function (Blueprint $table) {
            $table->dropForeign(['evidence_type_id']);
            $table->dropColumn(['evidence_type_id', 'server_captured_at', 'justificacion', 'is_mock_location']);
        });
    }
};
