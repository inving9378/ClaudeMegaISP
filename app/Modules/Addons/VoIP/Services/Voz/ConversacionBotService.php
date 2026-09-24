<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\IA\Services\IAPricingService;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Models\IaBotKnowledgeBase;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — el cerebro de "María". UN turno = texto transcrito del que
 * llama entra, respuesta de texto (+ marca de transferencia) sale. Ni sabe ni
 * le importa de audio — eso lo resuelve AudioSocketServer con
 * VoiceSttService/VoiceTtsService alrededor de esta clase.
 *
 * Reusa el adaptador de IA que YA existe (App\Modules\Addons\IA — el mismo
 * que usa Jarvis) — nunca un cliente HTTP propio (regla de "servicios
 * compartidos únicos" del proyecto).
 */
class ConversacionBotService
{
    /** El propio system_prompt pide este marcador — no se inventa aquí. */
    private const MARCADOR_TRANSFERIR = '[TRANSFERIR]';

    public function __construct(private IAPricingService $pricing = new IAPricingService())
    {
    }

    /**
     * @return array{texto_hablado: string, transferir: bool, costo_usd: float}
     */
    public function turno(IaBotConversation $conversacion, string $textoUsuario): array
    {
        $config    = IaBotConfig::current();
        $proveedor = $this->resolverProveedor();

        if (! $proveedor) {
            Log::error('ConversacionBotService: no hay proveedor de IA activo (ia_proveedores).');
            return [
                'texto_hablado' => 'Disculpa, en este momento no puedo continuar. Te transfiero con un agente.',
                'transferir'    => true,
                'costo_usd'     => 0.0,
            ];
        }

        $historial = $this->historialDesdeTranscript($conversacion);

        $systemPrompt = $config->system_prompt . "\n\n" . $this->bloqueBaseConocimiento();

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
            $resultado = $adaptador->enviarMensaje($historial, $textoUsuario, [], $systemPrompt);
        } catch (\Throwable $e) {
            Log::warning('ConversacionBotService: falla del adaptador de IA — ' . $e->getMessage());
            return [
                'texto_hablado' => 'Disculpa, tuve un problema técnico. Te transfiero con un agente.',
                'transferir'    => true,
                'costo_usd'     => 0.0,
            ];
        }

        $textoCrudo = trim($resultado['texto'] ?? '');
        $transferir = str_contains($textoCrudo, self::MARCADOR_TRANSFERIR);
        $textoHablado = trim(str_replace(self::MARCADOR_TRANSFERIR, '', $textoCrudo));

        $costoUsd = $this->pricing->calcularCosto(
            (string) $proveedor->modelo_default,
            (int) ($resultado['tokens_input'] ?? 0),
            (int) ($resultado['tokens_output'] ?? 0)
        );

        $this->registrarTurnoEnTranscript($conversacion, $textoUsuario, $textoHablado);

        if ($transferir) {
            $conversacion->escalated = true;
        }
        $conversacion->cost_usd = (float) $conversacion->cost_usd + $costoUsd;
        $conversacion->save();

        return [
            'texto_hablado' => $textoHablado,
            'transferir'    => $transferir,
            'costo_usd'     => $costoUsd,
        ];
    }

    /**
     * Mismo criterio que JarvisChatService::resolverProveedor() — Claude
     * primero, cualquier proveedor activo como respaldo. No se crea un
     * proveedor nuevo para el bot de voz: usa el que YA está configurado.
     */
    private function resolverProveedor(): ?IAProveedor
    {
        return IAProveedor::query()->where('driver', 'claude')->where('activo', true)->orderBy('id')->first()
            ?? IAProveedor::query()->where('activo', true)->orderBy('id')->first();
    }

    private function historialDesdeTranscript(IaBotConversation $conversacion): array
    {
        $transcript = $conversacion->transcript ?? [];
        // Ya viene en la forma {rol, contenido} que exige IAAdaptadorInterface
        // — se guarda así desde el primer turno, sin transformar aquí.
        return is_array($transcript) ? $transcript : [];
    }

    private function registrarTurnoEnTranscript(IaBotConversation $conversacion, string $mensajeUsuario, string $respuesta): void
    {
        $transcript = $conversacion->transcript ?? [];
        if (! is_array($transcript)) {
            $transcript = [];
        }
        $transcript[] = ['rol' => 'user', 'contenido' => $mensajeUsuario];
        $transcript[] = ['rol' => 'assistant', 'contenido' => $respuesta];
        $conversacion->transcript = $transcript;
    }

    /**
     * La base de conocimiento son pocas filas (sembradas: internet/voip/
     * facturación/cobertura) — cabe entera como contexto, sin necesitar
     * búsqueda semántica (RAG) para este volumen.
     */
    private function bloqueBaseConocimiento(): string
    {
        $filas = IaBotKnowledgeBase::orderBy('category')->get();
        if ($filas->isEmpty()) {
            return '';
        }

        $lineas = ["Base de conocimiento (úsala para guiar la conversación, en tus propias palabras):"];
        foreach ($filas as $kb) {
            $pasos = is_array($kb->solution_steps) ? implode(' → ', $kb->solution_steps) : '';
            $lineas[] = "- [{$kb->category}] {$kb->problem}: {$pasos}";
        }

        return implode("\n", $lineas);
    }
}
