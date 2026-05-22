<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acumulador de datos extraídos por la IA mensaje a mensaje en una
     * conversación. Cuando un cliente da su información en varios turnos
     * ("Mi nombre es Juan" → mensaje 1, "Vivo en Av. Reforma 123" → mensaje 2,
     * "CP 06000" → mensaje 3), WhatsAppCrmService::accumulateData() hace merge
     * en este campo. Una vez completo se vuelca a un nuevo registro Crm +
     * CrmMainInformation + CrmLeadInformation y conversation.crm_id se llena.
     *
     * Forma esperada (no estricta — la IA decide qué claves emite):
     * {
     *   "nombre": "Juan", "apellido_paterno": "Pérez", "apellido_materno": "...",
     *   "email": "...", "phone2": "...",
     *   "calle": "...", "numero_ext": "123", "numero_int": "A",
     *   "colonia_texto": "Centro", "municipio_texto": "Cuauhtémoc",
     *   "estado_texto": "CDMX", "cp": "06000",
     *   "colony_id": 1234, "municipality_id": 1, "state_id": 9,  // resueltos por addressResolver
     *   "referencia_origen": "amigo / facebook / ..."
     * }
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->json('collected_data')->nullable()->after('crm_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn('collected_data');
        });
    }
};
