<?php

namespace Tests\Feature\Marketing\Fase2\Backend\Concerns;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\MarketingNiche;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Services\Campanas360\ClienteIACampana;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;

/**
 * Datos de prueba del agente backend (Fase 2). Todo vive dentro de la transacción de MarketingTestCase.
 * La IA se sustituye en el contenedor (`ClienteIACampana`): ninguna llamada real; las respuestas se encolan.
 */
trait EscenarioBackendFase2
{
    use EscenarioBackendFase1;

    /** @var object{respuestas: array, llamadas: array, configurada: array} */
    protected object $iaFalsa;

    protected function sembrarNichos(): void
    {
        foreach (['familia' => 'Familia', 'home_office' => 'Home office'] as $slug => $nombre) {
            MarketingNiche::query()->updateOrCreate(
                ['company_id' => Empresa::PRINCIPAL_ID, 'slug' => $slug],
                ['name' => $nombre, 'description' => 'Nicho de prueba', 'active' => true, 'display_order' => 1,
                 'motivators' => ['ahorro'], 'pain_points' => ['lentitud'], 'objections' => ['precio'], 'vocabulary' => ['fibra'],
                 'emotional_tone' => 'cálido', 'music_mood' => 'uplifting_family', 'broll_tags' => ['familia'],
                 'voice_style' => 'cálida', 'preferred_voice_id' => 'nova']
            );
        }
    }

    /** Sustituye la puerta a la IA. `$configurada` por clave (por defecto ambas configuradas). */
    protected function falsearIA(array $respuestas = [], array $configurada = []): void
    {
        $estado = (object) [
            'respuestas'  => $respuestas,
            'llamadas'    => [],
            'configurada' => $configurada + [ClienteIACampana::ESTRATEGIA => true, ClienteIACampana::PIEZAS => true],
        ];
        $this->iaFalsa = $estado;

        $this->app->instance(ClienteIACampana::class, new class($estado) extends ClienteIACampana {
            public function __construct(private object $estado)
            {
            }

            public function configurada(string $clave): bool
            {
                return (bool) ($this->estado->configurada[$clave] ?? false);
            }

            public function enviar(string $clave, string $mensaje, string $system, int $maxTokens): array
            {
                $this->estado->llamadas[] = ['clave' => $clave, 'mensaje' => $mensaje, 'system' => $system];
                $r = array_shift($this->estado->respuestas);
                if ($r instanceof \Throwable) {
                    throw $r;
                }

                // Respuesta cortada por el tope de tokens: ['__cortada' => '<texto>'].
                if (is_array($r) && array_key_exists('__cortada', $r)) {
                    return ['texto' => (string) $r['__cortada'], 'modelo' => 'modelo-falso', 'cortada' => true];
                }

                return ['texto' => is_string($r) ? $r : json_encode($r, JSON_UNESCAPED_UNICODE), 'modelo' => 'modelo-falso', 'cortada' => false];
            }
        });
    }

    protected function payloadCampana(array $cambios = []): array
    {
        $hoy = Carbon::now('America/Mexico_City');

        $payload = array_replace_recursive([
            'nombre'          => 'Fibra 500 prueba',
            'objetivo'        => 'nuevos_clientes',
            'publico'         => ['descripcion' => 'Familias con fibra nueva', 'zonas' => ['Centro'], 'edad_min' => 25, 'edad_max' => 55,
                                  'nichos' => ['familia'], 'intereses' => ['streaming']],
            'oferta'          => ['descripcion' => '500 Megas con instalación gratis', 'plan' => 'Fibra 500', 'precio_mxn' => '599',
                                  'condiciones' => 'Primeros 100', 'vigencia_hasta' => $hoy->copy()->addMonth()->toDateString()],
            'canales'         => ['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp'],
            'presupuesto_mxn' => '15000.00',
            'fecha_inicio'    => $hoy->copy()->addDay()->toDateString(),
            'fecha_fin'       => $hoy->copy()->addDays(30)->toDateString(),
        ], $cambios);

        // Las listas se reemplazan completas (array_replace_recursive las mezclaría por índice).
        foreach (['canales', 'publico.zonas', 'publico.nichos', 'publico.intereses'] as $ruta) {
            if (data_get($cambios, $ruta) !== null) {
                data_set($payload, $ruta, data_get($cambios, $ruta));
            }
        }

        return $payload;
    }

    protected function admin(): User
    {
        return $this->adminMeganet();
    }

    /** Campaña 360 creada por la API (así pasa por la misma validación). */
    protected function crearCampana360(User $admin, array $cambios = []): Campaign
    {
        $r = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', $this->payloadCampana($cambios));
        $r->assertStatus(201);
        $this->contexto()->limpiar();

        return Campaign::query()->findOrFail($r->json('data.id'));
    }

    protected function estrategiaValida(array $canales = ['facebook', 'reels']): array
    {
        return [
            'resumen'             => 'Campaña para familias de la zona centro.',
            'propuesta_valor'     => 'Internet estable para toda la familia.',
            'mensajes_clave'      => ['Sin trabas', 'Instalación gratis', 'Soporte local'],
            'publico_detalle'     => 'Familias de 25 a 55 años.',
            'por_canal'           => array_map(fn ($c) => ['canal' => $c, 'rol' => 'Atraer', 'formato' => 'Corto', 'frecuencia' => '3 por semana'], $canales),
            'calendario'          => [['semana' => 1, 'acciones' => ['Publicar el reel A']]],
            'reparto_presupuesto' => array_map(fn ($c) => ['canal' => $c, 'porcentaje' => (string) round(100 / count($canales), 2)], $canales),
            'kpis'                => [['nombre' => 'Leads', 'meta' => '150']],
            'riesgos'             => ['Poca cobertura en algunas colonias'],
        ];
    }

    protected function guionValido(string $formato = '9:16'): array
    {
        return [
            'titulo'    => '¿Se traba tu serie?',
            'formato'   => $formato,
            'gancho'    => '¿Otra vez se trabó la serie?',
            'escenas'   => [
                ['numero' => 1, 'duracion_s' => 5, 'toma' => 'Mamá frente a la tele', 'visual' => ['tipo' => 'broll', 'categoria_broll' => 'familia'],
                 'texto_pantalla' => '¿Se traba tu serie?', 'voz_en_off' => '¿Otra vez se trabó la serie?', 'musica_sonido' => 'Golpe de bajo'],
                ['numero' => 2, 'duracion_s' => 5, 'toma' => 'Texto grande', 'visual' => ['tipo' => 'texto', 'categoria_broll' => null],
                 'texto_pantalla' => '$599 al mes', 'voz_en_off' => 'Fibra de 500 megas.', 'musica_sonido' => ''],
                ['numero' => 3, 'duracion_s' => 6, 'toma' => 'Cierre con logo', 'visual' => ['tipo' => 'cta', 'categoria_broll' => null],
                 'texto_pantalla' => 'Contrata hoy', 'voz_en_off' => 'Contrata hoy, la instalación es gratis.', 'musica_sonido' => 'Remate'],
            ],
            'musica'    => ['ambiente' => 'uplifting_family'],
            'voz'       => ['id' => 'nova'],
            'cta_final' => ['texto' => 'Contrata hoy'],
            'duracion_total_s' => 16,
        ];
    }

    /** Respuesta de la IA con piezas válidas para un tipo. */
    protected function respuestaPiezas(string $pieza, array $variantes): array
    {
        $contenido = fn (string $v) => match ($pieza) {
            'reel_guion'       => $this->guionValido('9:16'),
            'video_guion'      => $this->guionValido('16:9'),
            'copy_facebook'    => ['texto' => "Texto FB {$v}", 'hashtags' => ['#Fibra'], 'cta' => 'Contrata'],
            'copy_instagram'   => ['texto' => "Texto IG {$v}", 'hashtags' => ['Fibra', 'Internet'], 'cta' => 'Contrata'],
            'variante_anuncio' => ['titular' => "Titular {$v}", 'texto_principal' => 'Fibra 500', 'descripcion' => 'Instalación gratis',
                                   'boton' => 'MAS_INFORMACION', 'formato' => 'video', 'idea_visual' => 'Familia feliz'],
            'mensaje_whatsapp' => ['uso' => ['A' => 'difusion', 'B' => 'seguimiento', 'C' => 'respuesta'][$v], 'texto' => "Hola {$v}"],
        };

        return ['piezas' => array_map(fn ($v) => ['variante' => $v, 'contenido' => $contenido($v)], $variantes)];
    }

    /** Pieza lista creada a mano (sin IA). */
    protected function crearPieza(Campaign $c, string $pieza, string $variante, array $contenido, string $status = 'ready'): GeneratedContent
    {
        $this->contexto()->limpiar();
        $p = new GeneratedContent();
        $p->forceFill([
            'company_id'          => Empresa::PRINCIPAL_ID,
            'type'                => 'copy',
            'source_campaign_id'  => $c->id,
            'pieza'               => $pieza,
            'variante'            => $variante,
            'contenido'           => $contenido,
            'status'              => $status,
            'generation_engine'   => 'ia:prueba',
            'generation_cost_usd' => 0,
            'regeneraciones'      => 0,
        ])->save();

        return $p;
    }

    protected function nombreUnico(): string
    {
        return 'Campaña ' . Str::random(6);
    }
}
