<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAMensaje;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\Contexto\SesionTrabajoService;
use App\Modules\Addons\IA\Services\ContextoProyectoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class IAProveedorService
{
    public function __construct(
        protected ?ContextoProyectoService $contexto = null,
        protected ?SesionTrabajoService $sesionTrabajo = null,
        protected ?IAPricingService $pricing = null,
        protected ?MemoriaService $memoria = null,
    ) {
    }

    /**
     * Cada cuántos mensajes (user + assistant combinados) se dispara la
     * extracción automática de hechos hacia ia_memoria_proyecto.
     */
    public const EXTRAER_MEMORIA_CADA = 10;

    /** Tamaño máximo por imagen: 5 MB. */
    public const MAX_BYTES_IMAGEN = 5 * 1024 * 1024;

    /** Máximo de imágenes por mensaje. */
    public const MAX_IMAGENES_POR_MENSAJE = 20;

    /** MIME aceptados. */
    public const MIMES_VALIDOS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * Envía el mensaje del usuario al proveedor de la conversación,
     * persiste tanto el mensaje del usuario como la respuesta del
     * asistente y retorna ambos modelos.
     *
     * @param array<int, array{mime:string, data:string}> $imagenes
     * @return array{user: IAMensaje, assistant: IAMensaje}
     */
    public function enviarMensaje(IAConversacion $conversacion, string $mensaje, array $imagenes = []): array
    {
        $proveedor = $conversacion->proveedor;
        if (!$proveedor) {
            throw new RuntimeException('La conversación no tiene un proveedor de IA asignado.');
        }
        if (!$proveedor->activo) {
            throw new RuntimeException('El proveedor está inactivo.');
        }

        $imagenes = $this->validarImagenes($imagenes, (bool) $proveedor->soporta_imagenes);

        $historial = $this->construirHistorial($conversacion);

        $mensajeUser = IAMensaje::create([
            'ia_conversacion_id' => $conversacion->id,
            'rol' => 'user',
            'contenido' => $mensaje,
            'imagenes' => $this->resumirImagenesParaPersistir($imagenes),
            'created_by' => auth()->id(),
        ]);

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);

            // System prompt auto-inyectado con todo el contexto del proyecto.
            $systemPrompt = $this->contexto?->buildSystemPrompt(auth()->id());

            $resultado = $adaptador->enviarMensaje($historial, $mensaje, $imagenes, $systemPrompt);

            // Registra la sesión de trabajo (abre si no hay una abierta).
            if ($this->sesionTrabajo) {
                $sesion = $this->sesionTrabajo->abrirSiHaceFalta(auth()->id(), $proveedor->nombre);
                $this->sesionTrabajo->registrarPrompt($sesion, Str::limit($mensaje, 200));
            }

            $mensajeAssistant = IAMensaje::create([
                'ia_conversacion_id' => $conversacion->id,
                'rol' => 'assistant',
                'contenido' => $resultado['texto'],
                'tokens_input' => $resultado['tokens_input'] ?? null,
                'tokens_output' => $resultado['tokens_output'] ?? null,
                'metadata' => ['modelo' => $proveedor->modelo_default],
                'created_by' => auth()->id(),
            ]);

            $tokensIn = (int) ($resultado['tokens_input'] ?? 0);
            $tokensOut = (int) ($resultado['tokens_output'] ?? 0);
            if ($this->pricing && ($tokensIn + $tokensOut) > 0) {
                $this->pricing->registrarUso($conversacion, $mensajeAssistant, $proveedor, $tokensIn, $tokensOut, 'ia');
            }

            $conversacion->update([
                'ultimo_mensaje_at' => Carbon::now(),
                'modelo' => $proveedor->modelo_default,
                'updated_by' => auth()->id(),
            ]);

            // Marcar el proveedor como conectado si la llamada fue exitosa.
            $proveedor->update([
                'estado' => 'conectado',
                'ultimo_error' => null,
                'probado_at' => Carbon::now(),
            ]);

            // Auto-título: si el título sigue siendo el placeholder, usa los primeros 50 chars del mensaje del usuario.
            if (Str::startsWith($conversacion->titulo, 'Nuevo chat')) {
                $conversacion->update([
                    'titulo' => Str::limit(trim($mensaje), 50, '...') ?: 'Nuevo chat',
                ]);
            }

            // Extracción automática de hechos a memoria persistente del proyecto.
            // Se dispara cada N mensajes para no penalizar cada respuesta de chat.
            // Falla silenciosa: la memoria es best-effort, no debe romper la conversación.
            if ($this->memoria) {
                try {
                    $totalMensajes = IAMensaje::where('ia_conversacion_id', $conversacion->id)->count();
                    if ($totalMensajes > 0 && $totalMensajes % self::EXTRAER_MEMORIA_CADA === 0) {
                        $this->memoria->extraerHechos($conversacion->fresh());
                    }
                } catch (\Throwable $e) {
                    // Ya logueado dentro de MemoriaService::extraerHechos.
                }
            }

            return ['user' => $mensajeUser, 'assistant' => $mensajeAssistant];
        } catch (\Throwable $e) {
            $proveedor->update([
                'estado' => 'error',
                'ultimo_error' => Str::limit($e->getMessage(), 500),
            ]);
            throw $e;
        }
    }

    public function probarProveedor(IAProveedor $proveedor): bool
    {
        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
            $ok = $adaptador->probarConexion();
            $proveedor->update([
                'estado' => $ok ? 'conectado' : 'error',
                'ultimo_error' => $ok ? null : 'Falló prueba de conexión',
                'probado_at' => Carbon::now(),
            ]);
            return $ok;
        } catch (\Throwable $e) {
            $proveedor->update([
                'estado' => 'error',
                'ultimo_error' => Str::limit($e->getMessage(), 500),
                'probado_at' => Carbon::now(),
            ]);
            return false;
        }
    }

    /**
     * Convierte los mensajes históricos al formato neutro que esperan los adaptadores.
     */
    protected function construirHistorial(IAConversacion $conversacion): array
    {
        return $conversacion->mensajes()
            ->get()
            ->map(fn (IAMensaje $m) => [
                'rol' => $m->rol,
                'contenido' => $m->contenido,
                'imagenes' => [], // las imágenes históricas no se reenvían (consumirían mucho contexto)
            ])
            ->all();
    }

    /**
     * Valida tamaño, mime, cantidad y formato base64 de imágenes.
     *
     * @param array<int, array{mime:string, data:string}> $imagenes
     * @return array<int, array{mime:string, data:string, bytes:int}>
     */
    protected function validarImagenes(array $imagenes, bool $soportaImagenes): array
    {
        if (empty($imagenes)) {
            return [];
        }
        if (!$soportaImagenes) {
            throw new RuntimeException('El proveedor seleccionado no soporta imágenes.');
        }
        if (count($imagenes) > self::MAX_IMAGENES_POR_MENSAJE) {
            throw new RuntimeException('Máximo ' . self::MAX_IMAGENES_POR_MENSAJE . ' imágenes por mensaje.');
        }

        $resultado = [];
        foreach ($imagenes as $i => $img) {
            $mime = $img['mime'] ?? null;
            $data = $img['data'] ?? null;
            if (!$mime || !$data) {
                throw new RuntimeException("Imagen #{$i}: faltan campos mime/data.");
            }
            if (!in_array($mime, self::MIMES_VALIDOS, true)) {
                throw new RuntimeException("Imagen #{$i}: formato no soportado ({$mime}).");
            }
            // Strip data URL prefix si viene incluido.
            if (str_starts_with($data, 'data:')) {
                $data = preg_replace('#^data:[^;]+;base64,#', '', $data);
            }
            $binario = base64_decode($data, true);
            if ($binario === false) {
                throw new RuntimeException("Imagen #{$i}: base64 inválido.");
            }
            $bytes = strlen($binario);
            if ($bytes > self::MAX_BYTES_IMAGEN) {
                throw new RuntimeException("Imagen #{$i}: supera el límite de 5MB.");
            }
            $resultado[] = ['mime' => $mime, 'data' => $data, 'bytes' => $bytes];
        }
        return $resultado;
    }

    /**
     * No guardamos el base64 entero en BD para no inflar la tabla.
     * Solo metadatos: cantidad, tamaño y mime.
     */
    protected function resumirImagenesParaPersistir(array $imagenes): array
    {
        return array_map(fn ($img) => [
            'mime' => $img['mime'],
            'bytes' => $img['bytes'] ?? null,
        ], $imagenes);
    }
}
