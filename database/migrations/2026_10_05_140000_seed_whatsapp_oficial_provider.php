<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo del Hub: agrega "WhatsApp Business Platform (oficial)" junto al
 * "evolution" ya existente -- mismo type='servicios', mismo mecanismo de
 * credenciales cifradas. has_config=true porque, a diferencia de Evolution
 * (solo api_url+api_key), este necesita varios campos adicionales
 * (phone_number_id, waba_id, app_secret, verify_token, api_version) que
 * viven en la columna `config` (json) de la integración.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('api_integration_providers')->where('slug', 'whatsapp-oficial')->exists()) {
            return;
        }

        DB::table('api_integration_providers')->insert([
            'slug'        => 'whatsapp-oficial',
            'name'        => 'WhatsApp Business Platform (oficial)',
            'description' => 'API oficial de Meta -- requiere cuenta de Meta Business verificada y un numero de WhatsApp Business (WABA)',
            'type'        => 'servicios',
            'icon'        => 'verified',
            'docs_url'    => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
            'key_format'  => 'access token permanente (EAAG...)',
            'has_config'  => true,
            'is_system'   => true,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('api_integration_providers')->where('slug', 'whatsapp-oficial')->delete();
    }
};
