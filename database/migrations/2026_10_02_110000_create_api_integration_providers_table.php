<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_integration_providers')) {
            Schema::create('api_integration_providers', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 50)->unique();
                $table->string('name', 150);
                $table->string('description', 255)->nullable();
                $table->string('type', 20)->default('servicios')->index();
                $table->string('icon', 60)->nullable();
                $table->string('docs_url', 255)->nullable();
                $table->string('key_format', 100)->nullable();
                $table->boolean('has_config')->default(false);
                $table->boolean('is_system')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $now = now();
        $seed = [
            ['anthropic',   'Anthropic / Claude',          'IA conversacional — Claude API',            'ia',        'smart_toy',        'https://console.anthropic.com/settings/keys',     'sk-ant-...',          false],
            ['openai',      'OpenAI',                      'TTS, GPT — OpenAI API',                     'ia',        'record_voice_over','https://platform.openai.com/api-keys',            'sk-proj-...',         false],
            ['evolution',   'Evolution API / WhatsApp',    'Mensajería WhatsApp',                       'servicios', 'chat',             '',                                            'token hex',           true],
            ['pexels',      'Pexels',                      'Banco de imágenes y videos',                'servicios', 'image',            'https://www.pexels.com/api/',                 'alphanumeric',        false],
            ['google_maps', 'Google Maps',                 'Geocodificación y mapas',                   'servicios', 'map',              'https://console.cloud.google.com/apis/credentials', 'AIza...',     false],
            ['meta',        'Meta (Facebook / Instagram)', 'Publicador multicanal — App ID + App Secret de Facebook/Instagram', 'servicios', 'share', 'https://developers.facebook.com/apps', 'App ID + App Secret', true],
        ];

        foreach ($seed as [$slug, $name, $desc, $type, $icon, $docs, $fmt, $cfg]) {
            if (DB::table('api_integration_providers')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('api_integration_providers')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $desc, 'type' => $type,
                'icon' => $icon, 'docs_url' => $docs, 'key_format' => $fmt, 'has_config' => $cfg,
                'is_system' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integration_providers');
    }
};
