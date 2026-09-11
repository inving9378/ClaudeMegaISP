<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990805 (Fase 1/N — captura de metadata). Aditiva, columnas nullable, guard
 * hasColumn. Extiende talento_employee_document_signatures (#9990650, ya modela 1 fila por
 * documento×slot firmado) en vez de crear una tabla polimórfica nueva: hoy TODOS los templates
 * con requires_signature=true (11/11 en dev) ya declaran slots, así que esta tabla cubre el
 * 100% de los documentos firmables reales — decisión registrada en el log del item (desviación
 * documentada de la opción "tabla nueva talento_firmas" de la pregunta q3, a favor de la
 * alternativa que la propia descripción del item ya contemplaba: "o tabla ya existente que
 * aplique"). El flujo legado sin slots (columnas de talento_employee_documents) queda FUERA de
 * esta fase — hoy no tiene templates activos que lo usen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_employee_document_signatures', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_employee_document_signatures', 'hash_documento')) {
                $table->string('hash_documento', 64)->nullable()->after('signature_method');
            }
            if (!Schema::hasColumn('talento_employee_document_signatures', 'ip')) {
                $table->string('ip', 45)->nullable()->after('hash_documento');
            }
            if (!Schema::hasColumn('talento_employee_document_signatures', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip');
            }
            if (!Schema::hasColumn('talento_employee_document_signatures', 'dispositivo')) {
                $table->string('dispositivo', 255)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('talento_employee_document_signatures', 'trazos')) {
                $table->json('trazos')->nullable()->after('dispositivo');
            }
            if (!Schema::hasColumn('talento_employee_document_signatures', 'geolocalizacion')) {
                $table->json('geolocalizacion')->nullable()->after('trazos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_employee_document_signatures', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('talento_employee_document_signatures', 'geolocalizacion') ? 'geolocalizacion' : null,
                Schema::hasColumn('talento_employee_document_signatures', 'trazos') ? 'trazos' : null,
                Schema::hasColumn('talento_employee_document_signatures', 'dispositivo') ? 'dispositivo' : null,
                Schema::hasColumn('talento_employee_document_signatures', 'user_agent') ? 'user_agent' : null,
                Schema::hasColumn('talento_employee_document_signatures', 'ip') ? 'ip' : null,
                Schema::hasColumn('talento_employee_document_signatures', 'hash_documento') ? 'hash_documento' : null,
            ]));
        });
    }
};
