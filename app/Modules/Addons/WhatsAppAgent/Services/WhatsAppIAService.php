<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\IA\Models\IAProveedor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppIAService
{
    private string $defaultModel = 'claude-sonnet-4-20250514';

    private function getClaudeProveedor(): IAProveedor
    {
        $proveedor = IAProveedor::where('driver', 'claude')
            ->where('activo', true)
            ->orderByDesc('id')
            ->first();

        if (!$proveedor) {
            throw new RuntimeException('No hay IAProveedor activo con driver=claude en ia_proveedores.');
        }

        return $proveedor;
    }

    /**
     * Analiza el mensaje entrante y genera asistencia completa.
     *
     * @param string $incomingMessage     Texto del último mensaje entrante
     * @param array  $conversationHistory Lista de ['direction'=>'in|out','body'=>'..']
     * @param array  $clientContext       Datos del cliente (name, plan, status, etc.)
     * @param string $tone                friendly | formal | brief
     */
    public function assist(
        string $incomingMessage,
        array  $conversationHistory,
        array  $clientContext,
        string $tone = 'friendly'
    ): array {
        $toneInstructions = match ($tone) {
            'formal' => 'Responde de manera formal y profesional.',
            'brief'  => 'Responde de forma muy breve y directa, máximo 2 oraciones.',
            default  => 'Responde de manera amigable y cercana, usando el nombre del cliente cuando lo sepas.',
        };

        $clientInfo = $this->formatClientContext($clientContext);
        $history    = $this->formatHistory($conversationHistory);

        $prompt = <<<PROMPT
Eres un asistente de atención al cliente para MegaNet, una empresa ISP en México.
Responde ÚNICAMENTE con JSON válido, sin texto adicional ni bloques markdown.

INFORMACIÓN DEL CLIENTE:
{$clientInfo}

HISTORIAL RECIENTE DE LA CONVERSACIÓN:
{$history}

MENSAJE ENTRANTE DEL CLIENTE:
"{$incomingMessage}"

TONO: {$toneInstructions}

Genera el siguiente JSON exacto:
{
  "intent": {
    "primary": "tipo_intencion",
    "primary_label": "Etiqueta legible en español",
    "confidence": 0.94,
    "secondary": "tipo_intencion_2",
    "secondary_label": "Etiqueta 2 en español",
    "secondary_confidence": 0.61
  },
  "draft": "Respuesta completa lista para enviar al cliente",
  "quick_replies": [
    "Opción rápida 1 (máx 5 palabras)",
    "Opción rápida 2",
    "Opción rápida 3"
  ],
  "suggested_actions": [
    {
      "label": "Acción corta",
      "detail": "Descripción con datos concretos del cliente",
      "type": "offer|ticket|discount|info|escalate"
    }
  ],
  "tone_used": "{$tone}",
  "extracted_data": {
    "nombre": null,
    "apellido_paterno": null,
    "apellido_materno": null,
    "email": null,
    "phone2": null,
    "calle": null,
    "numero_ext": null,
    "numero_int": null,
    "colonia_texto": null,
    "municipio_texto": null,
    "estado_texto": null,
    "cp": null,
    "referencia_origen": null
  }
}

Tipos de intención válidos: payment_inquiry, upgrade_plan, service_issue,
disconnection_request, price_inquiry, complaint, compliment, general_inquiry,
reconnection_request, schedule_visit, invoice_dispute, installation_request.

EXTRACCIÓN DE DATOS (extracted_data):
- SOLO cuando intent.primary sea installation_request o schedule_visit, intenta
  extraer del mensaje (y/o del historial) los datos del cliente para crear un
  prospecto en el CRM.
- Para CUALQUIER otra intención, deja extracted_data con TODOS los campos en null.
- Reglas estrictas:
  · NO inventes datos. Si el cliente no lo mencionó, deja null.
  · "nombre" = primer nombre (ej. "Juan Pedro"). Si el cliente sólo dijo "soy Juan",
    "nombre"="Juan", apellidos null.
  · "calle" SIN número (el número va en numero_ext / numero_int).
  · "cp" debe ser 5 dígitos exactos; sino null.
  · "colonia_texto" / "municipio_texto" / "estado_texto" = texto libre tal como
    el cliente lo dijo, sin normalizar. La resolución a IDs la hace el backend.
  · "referencia_origen" = cómo se enteró ("amigo", "Facebook", "instagram", etc.).
- Si el cliente expresa interés en instalación PERO aún no dio datos suficientes,
  la draft debe pedirle UN dato a la vez (no abrumar):
  1) primero nombre completo,
  2) luego dirección (calle + número),
  3) luego colonia + CP + municipio + estado,
  4) opcionalmente email y cómo se enteró.
  Y todos los datos que SÍ haya dado en este o mensajes previos ya van en extracted_data.
PROMPT;

        $proveedor = $this->getClaudeProveedor();
        $endpoint  = $proveedor->endpoint_url ?: 'https://api.anthropic.com/v1/messages';
        $model     = $proveedor->modelo_default ?: $this->defaultModel;

        $response = Http::withHeaders([
            'x-api-key'         => $proveedor->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(20)->post($endpoint, [
            'model'      => $model,
            'max_tokens' => 1024,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) {
            Log::error('WhatsAppIA Claude error', ['body' => $response->body()]);
            return $this->fallbackAssist($incomingMessage, $tone);
        }

        $text  = (string) $response->json('content.0.text', '{}');
        $clean = trim(preg_replace('/```json|```/', '', $text));
        $data  = json_decode($clean, true);

        return is_array($data) ? $data : $this->fallbackAssist($incomingMessage, $tone);
    }

    private function fallbackAssist(string $message, string $tone): array
    {
        return [
            'intent' => [
                'primary'              => 'general_inquiry',
                'primary_label'        => 'Consulta general',
                'confidence'           => 0.5,
                'secondary'            => null,
                'secondary_label'      => null,
                'secondary_confidence' => 0,
            ],
            'draft'             => 'Hola, gracias por contactarnos. ¿En qué podemos ayudarte?',
            'quick_replies'     => ['Con gusto te ayudo', 'Dame un momento', 'Claro, ya reviso'],
            'suggested_actions' => [],
            'tone_used'         => $tone,
            'extracted_data'    => $this->emptyExtractedData(),
            'fallback'          => true,
        ];
    }

    /**
     * Forma canónica de extracted_data — todas las claves esperadas en null.
     * Útil para fallback y para que código consumidor pueda hacer ?? con
     * confianza sin chequear existencia de cada key.
     */
    public function emptyExtractedData(): array
    {
        return [
            'nombre'            => null,
            'apellido_paterno'  => null,
            'apellido_materno'  => null,
            'email'             => null,
            'phone2'            => null,
            'calle'             => null,
            'numero_ext'        => null,
            'numero_int'        => null,
            'colonia_texto'     => null,
            'municipio_texto'   => null,
            'estado_texto'      => null,
            'cp'                => null,
            'referencia_origen' => null,
        ];
    }

    private function formatClientContext(array $ctx): string
    {
        if (empty($ctx)) {
            return 'No se encontró cliente asociado a este número.';
        }

        return implode("\n", array_filter([
            "- Nombre: "          . ($ctx['name']          ?? 'Desconocido'),
            "- Plan actual: "     . ($ctx['plan']          ?? 'N/A'),
            "- Estado: "          . ($ctx['status']        ?? 'N/A'),
            "- Último pago: "     . ($ctx['last_payment']  ?? 'Sin registro'),
            "- Antigüedad: "      . ($ctx['seniority']     ?? 'N/A'),
            "- Tickets abiertos: ". ($ctx['open_tickets']  ?? '0'),
            "- Deuda pendiente: " . ($ctx['balance']       ?? '$0'),
        ]));
    }

    private function formatHistory(array $messages): string
    {
        if (empty($messages)) {
            return 'Sin historial previo.';
        }

        return collect($messages)
            ->take(-8)
            ->map(function ($msg) {
                $dir  = ($msg['direction'] ?? 'in') === 'in' ? 'Cliente' : 'Agente';
                $body = $msg['body'] ?? '';
                return "[{$dir}]: {$body}";
            })
            ->implode("\n");
    }
}
