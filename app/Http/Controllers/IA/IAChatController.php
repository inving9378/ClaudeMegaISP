<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\Asistente\Accion;
use App\Models\Asistente\Adjunto;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use App\Services\Asistente\Adjuntos\AdjuntoRechazado;
use App\Services\Asistente\AdjuntosAsistente;
use App\Services\Asistente\AsistenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * JARVIS — el asistente flotante (robotsito, IaChatFloat.vue).
 *
 * Memoria persistente por usuario, instrucciones y guía del sistema en resources/asistente/,
 * herramientas con los permisos del usuario y cambios que solo se aplican cuando el usuario los
 * confirma. Toda la lógica vive en App\Services\Asistente; aquí solo entra y sale HTTP.
 * La IA (integración + modelo) se elige en Integraciones → Módulos IA (clave asistente.chat_flotante).
 */
class IAChatController extends Controller
{
    public function __construct(
        private ModuleRegistry $registry,
        private AsistenteService $asistente,
        private AdjuntosAsistente $adjuntos,
    ) {
    }

    /**
     * Sugerencias para los chips de bienvenida, derivadas de `example_intents` de los módulos
     * activos (una por módulo, máximo 6).
     */
    public function suggestions(): JsonResponse
    {
        $picked = [];
        foreach ($this->registry->getAiContext() as $mod) {
            $intent = ($mod['example_intents'] ?? [])[0] ?? null;
            if ($intent !== null) {
                $picked[] = ['text' => $intent, 'module' => $mod['_module'] ?? null];
            }
            if (count($picked) >= 6) {
                break;
            }
        }

        return response()->json(['success' => true, 'suggestions' => $picked]);
    }

    /** Historial del hilo actual del usuario (se pinta al abrir el chat) y las reglas de los archivos. */
    public function conversacion(Request $request): JsonResponse
    {
        $a = $this->adjuntos->ajustes();

        return response()->json(['success' => true] + $this->asistente->paraPantalla($request->user()) + [
            'archivos' => ['max_mb' => $a['max_mb'], 'max_mb_hoja' => $a['max_mb_hoja'], 'horas_de_vida' => $a['horas_de_vida'],
                'max_por_mensaje' => $a['max_por_mensaje'], 'extensiones' => $a['extensiones']],
        ]);
    }

    public function nueva(Request $request): JsonResponse
    {
        $this->asistente->nuevaConversacion($request->user());

        return response()->json(['success' => true]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'    => 'nullable|string|max:8000|required_without:adjuntos',
            'context'    => 'nullable|string|max:500',
            'adjuntos'   => 'nullable|array|max:20',
            'adjuntos.*' => 'integer',
        ]);

        try {
            $r = $this->asistente->responder($request->user(), (string) ($data['message'] ?? ''), mb_substr((string) ($data['context'] ?? ''), 0, 255) ?: null, $data['adjuntos'] ?? []);

            return response()->json([
                'success'          => true,
                'message'          => $r['mensaje'],
                'accion_pendiente' => $r['accion_pendiente'] ?? null,
            ]);
        } catch (AdjuntoRechazado $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        } catch (IANoConfigurada $e) {
            return response()->json(['success' => false, 'error' => 'JARVIS no tiene una IA asignada todavía. Un administrador debe elegirla en Integraciones → Módulos IA.']);
        } catch (\Throwable $e) {
            Log::error('[JARVIS] fallo al responder: ' . $e->getMessage(), ['user' => $request->user()?->id]);

            return response()->json(['success' => false, 'error' => 'No pude responder en este momento. Inténtalo de nuevo en un momento.']);
        }
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $accion = Accion::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $r = $this->asistente->confirmarAccion($request->user(), $accion);

        return response()->json(['success' => true, 'message' => $r['mensaje'], 'ok' => $r['ok'] ?? false]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $accion = Accion::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $r = $this->asistente->cancelarAccion($request->user(), $accion);

        return response()->json(['success' => true, 'message' => $r['mensaje']]);
    }

    /** Una parte de un archivo (ver AdjuntosAsistente::recibirParte). En la última responde el archivo ya leído. */
    public function subirAdjunto(Request $request): JsonResponse
    {
        $d = $request->validate([
            'subida' => 'required|uuid',
            'indice' => 'required|integer|min:0|max:5000',
            'total'  => 'required|integer|min:1|max:5000',
            'nombre' => 'required|string|max:255',
            'tamano' => 'required|integer|min:1',
            'parte'  => 'required|file',
        ]);

        try {
            $a = $this->adjuntos->recibirParte($request->user(), $d, $request->file('parte'));
        } catch (AdjuntoRechazado $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'listo' => $a->estado === Adjunto::LISTO, 'adjunto' => $a->paraPantalla()]);
    }

    /** Quitar un archivo antes de mandarlo (o uno ya mandado): se borra de inmediato. */
    public function quitarAdjunto(Request $request, int $id): JsonResponse
    {
        $a = Adjunto::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        if ($a->borrado_at === null) {
            $this->adjuntos->borrar($a);
        }

        return response()->json(['success' => true]);
    }

    /**
     * El archivo, solo para su dueño. Fotos, PDF, video y audio se ven en el navegador; lo demás
     * (y todo con ?descargar) se descarga, nunca se interpreta como página.
     */
    public function verAdjunto(Request $request, int $id)
    {
        $a = Adjunto::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($a->disponible(), 410, 'Ese archivo ya se borró.');

        if ($request->boolean('miniatura') && is_file($a->ruta('miniatura.jpg'))) {
            return response()->file($a->ruta('miniatura.jpg'), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
        }
        abort_unless(is_file($a->original()), 410, 'Ese archivo ya se borró.');

        $enLinea = !$request->boolean('descargar') && in_array($a->tipo, ['imagen', 'pdf', 'video', 'audio'], true)
            && !in_array($a->extension, ['heic', 'heif'], true) && !str_contains((string) $a->mime, 'svg');
        if (!$enLinea) {
            return response()->download($a->original(), $a->nombre, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response()->file($a->original(), [
            'Content-Type'           => $a->mime ?: 'application/octet-stream',
            'Content-Disposition'    => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('inline', $a->nombre, preg_replace('/[^A-Za-z0-9._ -]+/', '_', \Illuminate\Support\Str::ascii($a->nombre)) ?: 'archivo'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
    }

    public function descargarAdjunto(Request $request, int $id)
    {
        $request->merge(['descargar' => 1]);

        return $this->verAdjunto($request, $id);
    }
}
