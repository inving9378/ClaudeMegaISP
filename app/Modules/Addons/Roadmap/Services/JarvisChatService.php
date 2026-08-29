<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Models\JarvisMensaje;
use RuntimeException;

/**
 * Item #806 (Jarvis Parte 3b) — el chat donde Irving conversa el brief de una sugerencia de
 * Jarvis (#805) antes de convertirla en item de la Hoja de Ruta. Reusa el proveedor de IA YA
 * configurado en el módulo IA (regla "SERVICIOS COMPARTIDOS ÚNICOS" de CLAUDE.md: la IA vive
 * solo ahí, `IAAdaptadorFactory`/`ia_proveedores`; aquí solo se consume el adaptador — sin
 * cliente HTTP propio ni key aparte) — decisión q3 del brief aprobado.
 */
class JarvisChatService
{
    public function __construct(private JarvisSugerenciasService $sugerencias)
    {
    }

    /** Candidatos vivos del detector (#805), cada uno con su clave estable + hilo si ya existe. */
    public function sugerenciasConHilo(): array
    {
        $res = $this->sugerencias->detectar();

        $claves = array_map(
            static fn (array $c) => JarvisConversacion::claveSugerencia($c['categoria'], $c['texto']),
            $res['candidatos']
        );

        $existentes = $claves === []
            ? collect()
            : JarvisConversacion::query()->whereIn('sugerencia_clave', $claves)->get()->keyBy('sugerencia_clave');

        $candidatos = [];
        foreach ($res['candidatos'] as $i => $c) {
            $clave = $claves[$i];
            $hilo  = $existentes->get($clave);
            $candidatos[] = [
                'clave'           => $clave,
                'categoria'       => $c['categoria'],
                'texto'           => $c['texto'],
                'citas'           => $c['citas'],
                'conversacion_id' => $hilo?->id,
                'estado'          => $hilo?->estado,
                'mensajes'        => $hilo ? $hilo->mensajes()->count() : 0,
            ];
        }

        return ['candidatos' => $candidatos, 'generado_at' => $res['generado_at'], 'commit' => $res['commit']];
    }

    public function obtenerOCrear(string $clave, ?string $categoria, ?string $texto, array $citas): JarvisConversacion
    {
        return JarvisConversacion::firstOrCreate(
            ['sugerencia_clave' => $clave],
            [
                'categoria'        => $categoria,
                'texto_sugerencia' => $texto,
                'citas'            => $citas,
                'estado'           => 'abierta',
                'created_by'       => auth()->id(),
            ]
        );
    }

    /**
     * Persiste el mensaje de Irving, pide respuesta al proveedor de IA activo y persiste la
     * respuesta. Devuelve ambos mensajes.
     *
     * @return array{user: JarvisMensaje, assistant: JarvisMensaje}
     */
    public function enviarMensaje(JarvisConversacion $conversacion, string $mensaje): array
    {
        $mensajeUsuario = JarvisMensaje::create([
            'jarvis_conversacion_id' => $conversacion->id,
            'rol'        => 'irving',
            'contenido'  => $mensaje,
            'created_by' => auth()->id(),
        ]);

        $proveedor = $this->proveedorActivo();

        $historial = $conversacion->mensajes()
            ->where('id', '!=', $mensajeUsuario->id)
            ->get()
            ->map(fn (JarvisMensaje $m) => [
                'rol'       => $m->rol === 'jarvis' ? 'assistant' : 'user',
                'contenido' => $m->contenido,
            ])->all();

        $adaptador = IAAdaptadorFactory::crear($proveedor);
        $resultado = $adaptador->enviarMensaje($historial, $mensaje, [], $this->systemPrompt($conversacion));

        $mensajeAsistente = JarvisMensaje::create([
            'jarvis_conversacion_id' => $conversacion->id,
            'rol'       => 'jarvis',
            'contenido' => $resultado['texto'],
        ]);

        return ['user' => $mensajeUsuario, 'assistant' => $mensajeAsistente];
    }

    public function vincularItem(JarvisConversacion $conversacion, int $itemId): JarvisConversacion
    {
        $conversacion->update(['item_id' => $itemId, 'estado' => 'convertida']);

        return $conversacion;
    }

    /** Sonnet por default vía el proveedor ya configurado en /ia/configuracion (q3 del brief). */
    private function proveedorActivo(): IAProveedor
    {
        $proveedor = IAProveedor::query()->where('driver', 'claude')->where('activo', true)->orderBy('id')->first()
            ?? IAProveedor::query()->where('activo', true)->orderBy('id')->first();

        if (! $proveedor) {
            throw new RuntimeException('No hay un proveedor de IA activo configurado (ver /ia/configuracion).');
        }

        return $proveedor;
    }

    private function systemPrompt(JarvisConversacion $conversacion): string
    {
        $citas = collect($conversacion->citas ?? [])
            ->map(fn ($c) => match ($c['tipo'] ?? null) {
                'archivo_linea' => ($c['archivo'] ?? '?') . ':' . ($c['linea'] ?? '?'),
                'item_roadmap'  => 'roadmap_items#' . ($c['id'] ?? '?'),
                default         => json_encode($c, JSON_UNESCAPED_UNICODE),
            })->implode(', ');

        return 'Eres JARVIS, el asistente del Circuito de Mejora Continua de MegaISP. Estás '
            . 'conversando con Irving (dueño del sistema) sobre UNA sugerencia que tú mismo '
            . "detectaste (categoría: {$conversacion->categoria}). Hallazgo original: "
            . "\"{$conversacion->texto_sugerencia}\". Citas verificables: {$citas}. "
            . 'Tu tarea: ayudar a Irving a decidir si esto vale la pena convertirlo en un item '
            . 'de la Hoja de Ruta y, si sí, redactar un TÍTULO corto y un PROMPT claro '
            . '(instrucciones para quien lo ejecute) que él pueda copiar tal cual con el botón '
            . '"Generar item del roadmap". Sé breve y concreto, en español. Si Irving decide que '
            . 'no vale la pena, dilo sin insistir — no fuerces la conversión.';
    }
}
