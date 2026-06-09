<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evidencias (upload de fotos)
        Schema::table('talento_work_order_media', function (Blueprint $table) {
            $table->string('client_uuid', 36)->nullable()->after('id');
            $table->index('client_uuid', 'media_client_uuid_idx');
        });

        // Check-in de asistencia
        Schema::table('talento_attendances', function (Blueprint $table) {
            $table->string('client_uuid', 36)->nullable()->after('id');
            $table->index('client_uuid', 'attendance_client_uuid_idx');
        });

        // Incidencias reportadas desde la app
        Schema::table('talento_work_order_incidents', function (Blueprint $table) {
            $table->string('client_uuid', 36)->nullable()->after('id');
            $table->index('client_uuid', 'incident_client_uuid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('talento_work_order_media', function (Blueprint $table) {
            $table->dropIndex('media_client_uuid_idx');
            $table->dropColumn('client_uuid');
        });
        Schema::table('talento_attendances', function (Blueprint $table) {
            $table->dropIndex('attendance_client_uuid_idx');
            $table->dropColumn('client_uuid');
        });
        Schema::table('talento_work_order_incidents', function (Blueprint $table) {
            $table->dropIndex('incident_client_uuid_idx');
            $table->dropColumn('client_uuid');
        });
    }
};
