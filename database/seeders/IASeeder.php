<?php

namespace Database\Seeders;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAProyecto;
use Illuminate\Database\Seeder;

class IASeeder extends Seeder
{
    public function run(): void
    {
        // Proyecto "General" por defecto.
        IAProyecto::firstOrCreate(
            ['nombre' => 'General', 'es_default' => true],
            [
                'descripcion' => 'Proyecto por defecto para conversaciones sin clasificar',
                'color' => '#888888',
            ]
        );

        // Proveedores iniciales (Claude, OpenAI, Gemini).
        // Las api_keys se toman de .env si están definidas; si no, queda vacío
        // y el proveedor se marca como "sin_configurar".
        $proveedores = [
            [
                'nombre' => 'Claude (Anthropic)',
                'driver' => 'claude',
                'api_key' => env('IA_CLAUDE_API_KEY'),
                'endpoint_url' => 'https://api.anthropic.com/v1/messages',
                'modelo_default' => 'claude-3-5-sonnet-20241022',
                'soporta_imagenes' => true,
                'config_extra' => ['max_tokens' => 4096],
                'activo' => false,
            ],
            [
                'nombre' => 'OpenAI (GPT)',
                'driver' => 'openai',
                'api_key' => env('IA_OPENAI_API_KEY'),
                'endpoint_url' => 'https://api.openai.com/v1/chat/completions',
                'modelo_default' => 'gpt-4o',
                'soporta_imagenes' => true,
                'config_extra' => null,
                'activo' => false,
            ],
            [
                'nombre' => 'Gemini (Google)',
                'driver' => 'gemini',
                'api_key' => env('IA_GEMINI_API_KEY'),
                'endpoint_url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
                'modelo_default' => 'gemini-2.0-flash',
                'soporta_imagenes' => true,
                'config_extra' => null,
                'activo' => false,
            ],
        ];

        foreach ($proveedores as $p) {
            $p['estado'] = !empty($p['api_key']) ? 'sin_configurar' : 'sin_configurar';
            IAProveedor::firstOrCreate(
                ['nombre' => $p['nombre']],
                $p
            );
        }
    }
}
