<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula una conversación de WhatsApp con un prospecto del CRM.
     *
     * Una conversación puede no tener CRM (mensajes informativos sin intent
     * de instalación) → nullable. Si el CRM se elimina manualmente, mantener
     * la conversación con crm_id=NULL (SET NULL) para no perder el historial
     * de chat.
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->foreignId('crm_id')
                ->nullable()
                ->after('client_id')
                ->constrained('crms')
                ->onDelete('set null');
            $table->index('crm_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['crm_id']);
            $table->dropIndex(['crm_id']);
            $table->dropColumn('crm_id');
        });
    }
};
