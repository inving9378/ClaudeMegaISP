<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repone en megaisp (dev) los índices que sus migraciones originales ya declaraban
 * pero que la BD viva nunca tuvo (drift detectado por schema:diff-tablas-columnas,
 * item #868, origen #829/#816). Las migraciones de origen constan como `Ran` en la
 * tabla `migrations`, así que el hueco no es una migración pendiente — es la BD viva
 * desalineada de lo que su propia migración pide. Aditiva e idempotente
 * (Schema::hasIndex guard); down() la revierte igual de guardada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_integrations', function (Blueprint $table) {
            if (!Schema::hasIndex('api_integrations', 'api_integrations_provider_index')) {
                $table->index('provider');
            }
            if (!Schema::hasIndex('api_integrations', 'api_integrations_slug_index')) {
                $table->index('slug');
            }
        });

        Schema::table('marketing_generated_content', function (Blueprint $table) {
            if (!Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_company_id_index')) {
                $table->index('company_id');
            }
            if (!Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_source_plan_id_index')) {
                $table->index('source_plan_id');
            }
            if (!Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_template_id_index')) {
                $table->index('template_id');
            }
        });

        Schema::table('marketing_messages', function (Blueprint $table) {
            if (!Schema::hasIndex('marketing_messages', 'marketing_messages_external_message_id_index')) {
                $table->index('external_message_id');
            }
            if (!Schema::hasIndex('marketing_messages', 'marketing_messages_sender_user_id_index')) {
                $table->index('sender_user_id');
            }
            if (!Schema::hasIndex('marketing_messages', 'marketing_messages_sent_at_index')) {
                $table->index('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_integrations', function (Blueprint $table) {
            if (Schema::hasIndex('api_integrations', 'api_integrations_provider_index')) {
                $table->dropIndex('api_integrations_provider_index');
            }
            if (Schema::hasIndex('api_integrations', 'api_integrations_slug_index')) {
                $table->dropIndex('api_integrations_slug_index');
            }
        });

        Schema::table('marketing_generated_content', function (Blueprint $table) {
            if (Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_company_id_index')) {
                $table->dropIndex('marketing_generated_content_company_id_index');
            }
            if (Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_source_plan_id_index')) {
                $table->dropIndex('marketing_generated_content_source_plan_id_index');
            }
            if (Schema::hasIndex('marketing_generated_content', 'marketing_generated_content_template_id_index')) {
                $table->dropIndex('marketing_generated_content_template_id_index');
            }
        });

        Schema::table('marketing_messages', function (Blueprint $table) {
            if (Schema::hasIndex('marketing_messages', 'marketing_messages_external_message_id_index')) {
                $table->dropIndex('marketing_messages_external_message_id_index');
            }
            if (Schema::hasIndex('marketing_messages', 'marketing_messages_sender_user_id_index')) {
                $table->dropIndex('marketing_messages_sender_user_id_index');
            }
            if (Schema::hasIndex('marketing_messages', 'marketing_messages_sent_at_index')) {
                $table->dropIndex('marketing_messages_sent_at_index');
            }
        });
    }
};
