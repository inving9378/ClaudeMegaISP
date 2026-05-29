<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\MarketingNiche;
use Illuminate\Database\Seeder;

class MarketingNichesSeeder extends Seeder
{
    public function run(): void
    {
        $niches = [
            [
                'slug'                => 'gamer',
                'name'                => 'Gamer',
                'description'         => 'Jugadores PC/consola que viven online. Alto consumo de bandwidth, extremadamente sensibles a latencia.',
                'motivators'          => ['Ping bajo', 'Estabilidad sin desconexiones', 'Carga rápida de juegos', 'Streaming Twitch sin cortes'],
                'pain_points'         => ['Lag en partidas competitivas', 'Desconexiones a media partida', 'Compartir red con familia', 'Carga de updates eterna'],
                'objections'          => ['Precio alto', 'Compromisos forzosos', 'Instalación complicada'],
                'vocabulary'          => ['megas', 'ping', 'MS', 'fibra óptica', 'sin lag', 'partida', 'rank'],
                'emotional_tone'      => 'energetic',
                'music_mood'          => 'epic_electronic',
                'broll_tags'          => ['gaming', 'esports', 'keyboard', 'mechanical', 'fps', 'rgb', 'setup'],
                'voice_style'         => 'youthful',
                'preferred_voice_id'  => 'echo',
                'display_order'       => 1,
            ],
            [
                'slug'                => 'familia',
                'name'                => 'Familia',
                'description'         => 'Familias con niños y adolescentes. Múltiples dispositivos simultáneos.',
                'motivators'          => ['Netflix 4K sin pausas', 'Aguanta 5+ dispositivos', 'Tareas escolares', 'Tranquilidad de los niños'],
                'pain_points'         => ['Se cae cuando todos están conectados', 'Niños se quejan', 'Tarea no se sube'],
                'objections'          => ['Precio', 'Confianza en marca'],
                'vocabulary'          => ['hogar', 'niños', 'familia', 'tranquilidad', 'suficiente para todos'],
                'emotional_tone'      => 'warm',
                'music_mood'          => 'uplifting_family',
                'broll_tags'          => ['family', 'kids', 'home', 'livingroom', 'smarttv', 'dinner', 'homework'],
                'voice_style'         => 'warm',
                'preferred_voice_id'  => 'nova',
                'display_order'       => 2,
            ],
            [
                'slug'                => 'home_office',
                'name'                => 'Home Office',
                'description'         => 'Profesionales que trabajan desde casa. Videollamadas constantes.',
                'motivators'          => ['Zoom/Teams estable', 'Subir archivos rápido', 'Sin cortes en juntas', 'Buena imagen en cámara'],
                'pain_points'         => ['Imagen pixeleada en juntas', 'Se cae Zoom', 'Subir archivos pesados toma horas'],
                'objections'          => ['Precio', 'Tiempo de instalación', 'Necesidad de comprobación'],
                'vocabulary'          => ['home office', 'remoto', 'videollamada', 'productividad', 'profesional'],
                'emotional_tone'      => 'professional',
                'music_mood'          => 'corporate_clean',
                'broll_tags'          => ['laptop', 'videocall', 'desk', 'workfromhome', 'coffee', 'meeting', 'office'],
                'voice_style'         => 'professional',
                'preferred_voice_id'  => 'onyx',
                'display_order'       => 3,
            ],
            [
                'slug'                => 'streamer',
                'name'                => 'Streamer / Creador',
                'description'         => 'Creadores de contenido (Twitch, YouTube, TikTok). Suben mucho material pesado.',
                'motivators'          => ['Upload alto y estable', 'OBS sin frame drops', 'Subir videos en 4K rápido', 'Comunidad activa'],
                'pain_points'         => ['Stream se cae en momento crítico', 'Subir un video 4K toma horas', 'Cuello de botella en upload'],
                'objections'          => ['Precio', 'Symmetry de velocidades'],
                'vocabulary'          => ['stream', 'OBS', 'upload', 'simétrico', 'live', 'contenido', 'community'],
                'emotional_tone'      => 'energetic',
                'music_mood'          => 'modern_creative',
                'broll_tags'          => ['streaming', 'content', 'camera', 'microphone', 'rgb', 'obs', 'youtube', 'tiktok'],
                'voice_style'         => 'youthful',
                'preferred_voice_id'  => 'nova',
                'display_order'       => 4,
            ],
            [
                'slug'                => 'pequeno_negocio',
                'name'                => 'Pequeño Negocio',
                'description'         => 'Dueños de PyMEs, oficinas pequeñas, comercios. Necesitan estabilidad para operación.',
                'motivators'          => ['Internet empresarial confiable', 'TPV/sistema POS funcional', 'Cámaras de seguridad online', 'Atención a clientes sin cortes'],
                'pain_points'         => ['Se cae el TPV con cliente enfrente', 'Cámaras pierden grabación', 'No puedo cobrar con tarjeta'],
                'objections'          => ['Costo mensual', 'Tiempo de instalación', 'Garantía SLA'],
                'vocabulary'          => ['negocio', 'empresa', 'TPV', 'cámaras', 'clientes', 'operación', 'estabilidad'],
                'emotional_tone'      => 'professional',
                'music_mood'          => 'corporate_confident',
                'broll_tags'          => ['business', 'shop', 'smallbusiness', 'pos', 'retail', 'security', 'employees'],
                'voice_style'         => 'authoritative',
                'preferred_voice_id'  => 'onyx',
                'display_order'       => 5,
            ],
            [
                'slug'                => 'adulto_mayor',
                'name'                => 'Adulto Mayor',
                'description'         => 'Personas 60+ que usan internet principalmente para comunicarse con familia y entretenimiento.',
                'motivators'          => ['Hablar con nietos por video', 'Ver YouTube', 'Netflix sin complicaciones', 'Soporte humano cuando hay problemas'],
                'pain_points'         => ['No entiende cuando se cae el internet', 'Le cuesta resolver problemas técnicos', 'Soporte por chatbot lo frustra'],
                'objections'          => ['Precio', 'Complejidad', 'Confianza'],
                'vocabulary'          => ['familia', 'tranquilo', 'sencillo', 'siempre', 'soporte', 'seguro'],
                'emotional_tone'      => 'calm',
                'music_mood'          => 'warm_acoustic',
                'broll_tags'          => ['senior', 'grandparents', 'tablet', 'videocall', 'family', 'garden', 'home'],
                'voice_style'         => 'warm',
                'preferred_voice_id'  => 'fable',
                'display_order'       => 6,
            ],
        ];

        foreach ($niches as $data) {
            MarketingNiche::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge(['company_id' => 1], $data)
            );
        }
    }
}
