<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\IA\Services\IAPricingService;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Models\IaBotKnowledgeBase;
use App\Modules\Addons\VoIP\Models\IaBotLead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MegaVoz Fase 6 — el cerebro de la asistente. UN turno = texto transcrito del que llama
 * entra, respuesta de texto (+ marca de transferencia) sale. No sabe de audio: eso lo
 * resuelve AudioSocketServer con VoiceSttService/VoiceTtsService alrededor de esta clase.
 *
 * La IA (integración + modelo) se decide en Integraciones → Módulos IA (clave voip.bot_voz,
 * vía IA::enviar), nunca un cliente HTTP propio. Sin IA asignada o con error, avisa y
 * transfiere con una persona.
 *
 * 27-nov: las instrucciones llevan además las reglas de una llamada (respuestas cortas, una
 * pregunta a la vez), quién llama si el número es de un cliente (estado, plan, saldo) y la
 * fecha. La temperatura de la pantalla por fin se usa. Al colgar, analizar() deja el
 * resumen y, si era alguien interesado en contratar, el lead.
 */
class ConversacionBotService
{
    /** El propio system_prompt pide este marcador — no se inventa aquí. */
    public const MARCADOR_TRANSFERIR = '[TRANSFERIR]';

    /** Tope de la respuesta. Con modelos de razonamiento incluye lo que «piensa»: no bajarlo de más. */
    private const MAX_TOKENS = 700;

    public const CATEGORIAS = [
        'internet'  => 'Falla de internet',
        'pagos'     => 'Pagos y saldo',
        'ventas'    => 'Contratar o cambiar plan',
        'telefonia' => 'Telefonía',
        'cobertura' => 'Cobertura',
        'otro'      => 'Otro',
    ];

    /** Instrucciones por si la pantalla se dejó vacía. */
    public const PROMPT_BASE = "Eres la asistente virtual de Meganet Telecomunicaciones, proveedor de internet por fibra óptica en México.\n"
        . "Atiendes a clientes (fallas, pagos, saldo) y a personas interesadas en contratar.\n"
        . 'Sé amable, clara y breve. Si no sabes algo, dilo y transfiere con una persona.';

    public const REGLAS_LLAMADA = "REGLAS DE ESTA LLAMADA (síguelas siempre y no las menciones):\n"
        . "- Estás hablando por teléfono: lo que escribes se convierte en voz. Contesta con una o dos oraciones cortas y naturales, de máximo 40 palabras.\n"
        . "- Nada de listas, viñetas, asteriscos, emojis ni enlaces. Escribe como se habla.\n"
        . "- Haz una sola pregunta a la vez y espera la respuesta.\n"
        . "- Escribe las cantidades como «350 pesos» (sin centavos si son cero). Una referencia de pago díctala despacio, por partes.\n"
        . "- Si no entendiste, pide amablemente que lo repita. Nunca inventes datos, precios, fechas ni promesas.\n"
        . "- Si la persona pide hablar con alguien, está molesta, necesita una visita técnica o no puedes resolverlo, despídete en una frase corta y termina tu respuesta con [TRANSFERIR].";

    /**
     * Cuánto «piensa» el modelo, de más rápido a menos. 'none' contesta medio segundo antes
     * (medido con gpt-6-luna: 1.2–1.5 s contra 1.8–2.0 s, misma respuesta), pero no todos lo
     * aceptan: gpt-5 pide 'low' y otros (gpt-5-chat) ninguno. Lo que rechace un modelo se
     * recuerda en caché por modelo: cada llamada es un proceso nuevo y no debe volver a fallar.
     */
    private const RAZONAMIENTO = ['none', 'low', null];

    public function __construct(private IAPricingService $pricing = new IAPricingService())
    {
    }

    /**
     * Un turno de la llamada: responde y lo deja en la conversación.
     *
     * @return array{texto_hablado: string, transferir: bool, costo_usd: float}
     */
    public function turno(IaBotConversation $conversacion, string $textoUsuario, ?array $cliente = null): array
    {
        $historial = is_array($conversacion->transcript) ? $conversacion->transcript : [];

        $respuesta = $this->responder($historial, $textoUsuario, $cliente);

        $this->registrarTurnoEnTranscript($conversacion, $textoUsuario, $respuesta['texto_hablado']);

        if ($respuesta['transferir']) {
            $conversacion->escalated = true;
            $conversacion->transferred_at = now();
        }
        $conversacion->cost_usd = (float) $conversacion->cost_usd + $respuesta['costo_usd'];
        $conversacion->save();

        return $respuesta;
    }

    /**
     * La respuesta, sin guardar nada (la pantalla de prueba la usa igual que la llamada).
     * El historial va en la forma {rol, contenido} de IAAdaptadorInterface.
     *
     * @return array{texto_hablado: string, transferir: bool, costo_usd: float, modelo?: string, error?: string}
     */
    public function responder(array $historial, string $textoUsuario, ?array $cliente = null, ?IaBotConfig $config = null): array
    {
        $config ??= IaBotConfig::current();

        // El saludo ya se dijo: va en las instrucciones y no como primer turno de la conversación
        // (algunas IA, como Claude o Gemini, rechazan un historial que empieza con la asistente).
        $saludo = null;
        if (($historial[0]['rol'] ?? null) === 'assistant') {
            $saludo = (string) ($historial[0]['contenido'] ?? '');
            $historial = array_slice($historial, 1);
        }
        $instrucciones = $this->instrucciones($config, $cliente, $saludo);
        $llaveEsfuerzo = 'megavoz.razonamiento.' . md5((string) ($this->modeloConversacion() ?? ''));

        try {
            $resultado = null;
            $nivel = (int) Cache::get($llaveEsfuerzo, 0);
            while ($resultado === null) {
                try {
                    // Sin reintentos: en una llamada es mejor transferir que hacer esperar.
                    $resultado = IA::enviar('voip.bot_voz', $textoUsuario, [], $instrucciones, $historial, [
                        'reintentos'   => 0,
                        'timeout'      => 20,
                        'max_tokens'   => self::MAX_TOKENS,
                        'temperatura'  => (float) $config->temperature,
                        'razonamiento' => self::RAZONAMIENTO[$nivel] ?? null,
                    ]);
                } catch (IANoConfigurada $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    if ($nivel >= count(self::RAZONAMIENTO) - 1 || ! str_contains(mb_strtolower($e->getMessage()), 'reasoning')) {
                        throw $e;
                    }
                    Cache::put($llaveEsfuerzo, ++$nivel, now()->addDays(7));
                }
            }
        } catch (IANoConfigurada $e) {
            Log::error('ConversacionBotService: el bot de voz no tiene IA asignada — ' . $e->getMessage());
            return [
                'texto_hablado' => 'Disculpa, en este momento no puedo continuar. Te comunico con una persona.',
                'transferir'    => true,
                'costo_usd'     => 0.0,
                'error'         => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::warning('ConversacionBotService: falla del adaptador de IA — ' . $e->getMessage());
            return [
                'texto_hablado' => 'Disculpa, tuve un problema técnico. Te comunico con una persona.',
                'transferir'    => true,
                'costo_usd'     => 0.0,
                'error'         => $e->getMessage(),
            ];
        }

        $textoCrudo = trim((string) ($resultado['texto'] ?? ''));
        $transferir = str_contains($textoCrudo, self::MARCADOR_TRANSFERIR);
        $textoHablado = self::limpiarParaVoz(str_replace(self::MARCADOR_TRANSFERIR, '', $textoCrudo));

        // Respuesta vacía (p. ej. el modelo gastó el tope pensando): mejor pedir que repita que callar.
        if ($textoHablado === '' && ! $transferir) {
            $textoHablado = 'Perdón, ¿me lo puedes repetir?';
        }

        return [
            'texto_hablado' => $textoHablado,
            'transferir'    => $transferir,
            'costo_usd'     => $this->costo($resultado),
            'modelo'        => (string) ($resultado['modelo'] ?? ''),
        ];
    }

    /** Lo que recibe la IA como instrucciones: las de la pantalla + reglas + quién llama + base de conocimiento. */
    public function instrucciones(IaBotConfig $config, ?array $cliente, ?string $saludo = null): string
    {
        $propias = trim((string) $config->system_prompt);
        $saludo = trim((string) $saludo);

        return implode("\n\n", array_filter([
            $propias !== '' ? $propias : self::PROMPT_BASE,
            self::REGLAS_LLAMADA,
            'Hoy es ' . Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm') . ' (hora del centro de México).',
            $saludo !== '' ? "Al contestar ya dijiste: «{$saludo}». No vuelvas a saludar." : '',
            self::bloqueCliente($cliente),
            $this->bloqueBaseConocimiento(),
        ]));
    }

    private function modeloConversacion(): ?string
    {
        try {
            return IA::proveedorPara('voip.bot_voz')->modelo_default;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Quién llama, para que no pida lo que ya se sabe. $cliente es lo de
     * IABotCustomerService::identifyCustomer() + contextoParaBot(); null = número desconocido.
     */
    public static function bloqueCliente(?array $cliente): string
    {
        if (! $cliente || empty($cliente['found'])) {
            return "QUIÉN LLAMA: un número que no está registrado (alguien interesado en contratar, o un cliente que llama de otro teléfono).\n"
                . "- Si dice ser cliente: pídele su nombre completo y transfiérelo para que una persona lo atienda con sus datos.\n"
                . '- Si le interesa contratar: pregúntale su nombre, su colonia o dirección y qué le interesa, de una pregunta a la vez.';
        }

        $lineas = ['QUIÉN LLAMA: un cliente de Meganet, identificado por su número de teléfono.'];
        $pila = trim((string) ($cliente['nombre_pila'] ?? ''));
        $lineas[] = '- Nombre: ' . ($cliente['name'] ?? 'sin nombre') . ($pila !== '' ? " (dile {$pila})" : '') . '.';

        $estado = (string) ($cliente['estado'] ?? '');
        $lineas[] = '- Servicio: ' . match ($estado) {
            'Activo'    => 'activo.',
            'Bloqueado' => 'suspendido (bloqueado), normalmente por falta de pago; se reactiva al pagar.',
            'Inactivo'  => 'inactivo (dado de baja).',
            ''          => 'sin estado registrado.',
            default     => mb_strtolower($estado) . '.',
        };

        foreach ((array) ($cliente['servicios'] ?? []) as $s) {
            $precio = (float) ($s['precio'] ?? 0);
            $lineas[] = '- Plan: ' . $s['plan'] . ($precio > 0 ? ' (' . self::pesos($precio) . ' al mes)' : '') . '.';
        }

        $saldo = (float) ($cliente['saldo'] ?? 0);
        $lineas[] = '- Saldo: ' . match (true) {
            $saldo < -0.009 => 'debe ' . self::pesos(abs($saldo)) . '.',
            $saldo > 0.009  => 'tiene ' . self::pesos($saldo) . ' a favor.',
            default         => 'está al corriente (no debe nada).',
        };

        if (! empty($cliente['fecha_pago'])) {
            $lineas[] = '- Fecha límite de pago: ' . self::fechaHablada($cliente['fecha_pago']) . '.';
        }
        if (! empty($cliente['fecha_corte'])) {
            $lineas[] = '- Fecha de corte: ' . self::fechaHablada($cliente['fecha_corte']) . '.';
        }
        if (! empty($cliente['referencia'])) {
            $lineas[] = '- Referencia para pagar en banco o tienda: ' . $cliente['referencia'] . '.';
        }

        $lineas[] = 'No le pidas datos que ya están aquí ni se los leas todos de golpe: úsalos para contestar lo que pregunte. '
            . 'Si dice que no es el titular, no le des el saldo ni datos de la cuenta.';

        return implode("\n", $lineas);
    }

    /** «1234.5» → «1,234.50 pesos»; «350» → «350 pesos». */
    public static function pesos(float $monto): string
    {
        $entero = abs($monto - round($monto)) < 0.005;

        return number_format($monto, $entero ? 0 : 2) . ' pesos';
    }

    /** «2026-11-30» → «30 de noviembre» (con el año si no es el actual). */
    public static function fechaHablada(string $fecha): string
    {
        try {
            $f = Carbon::parse($fecha)->locale('es');
        } catch (\Throwable) {
            return $fecha;
        }

        return $f->isoFormat($f->year === Carbon::now()->year ? 'D [de] MMMM' : 'D [de] MMMM [de] YYYY');
    }

    /** Quita lo que no se puede decir en voz alta: markdown, viñetas, emojis, espacios de más. */
    public static function limpiarParaVoz(string $texto): string
    {
        $texto = preg_replace('/https?:\/\/\S+/u', '', $texto);
        $texto = preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/mu', '', $texto);
        $texto = str_replace(['**', '__', '`', '#'], '', $texto);
        $texto = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $texto);

        return trim(preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Al terminar la llamada: resumen, de qué se trató, si se resolvió y, si quien llamó no es
     * cliente y le interesa contratar, el lead (lo que antes nadie guardaba: la pestaña Leads
     * siempre estaba vacía). Corre con la llamada ya colgada: no hace esperar a nadie.
     */
    public function analizar(IaBotConversation $conversacion): void
    {
        $turnos = is_array($conversacion->transcript) ? $conversacion->transcript : [];
        $delCliente = array_filter($turnos, fn ($t) => ($t['rol'] ?? '') === 'user');
        if ($delCliente === []) {
            return;
        }

        $dialogo = implode("\n", array_map(
            fn ($t) => (($t['rol'] ?? '') === 'user' ? 'Cliente: ' : 'Asistente: ') . ($t['contenido'] ?? ''),
            $turnos
        ));

        $system = "Analizas llamadas de atención de Meganet Telecomunicaciones (internet por fibra). Devuelve SOLO un objeto JSON, sin texto adicional:\n"
            . '{"resumen": "qué pidió y en qué quedó, máximo 25 palabras", "categoria": "' . implode('|', array_keys(self::CATEGORIAS)) . '", '
            . '"resuelto": true o false, "lead": {"interesado": true o false, "nombre": "", "direccion": "", "email": "", "servicio": "", "plan": ""}}' . "\n"
            . 'En "lead" pon solo lo que la persona dijo; "interesado" es true únicamente si quiere contratar o cotizar un servicio.';

        try {
            $r = IA::enviar('voip.bot_voz', $dialogo, [], $system, [], [
                'reintentos'   => 1,
                'timeout'      => 40,
                'max_tokens'   => 900,
                'temperatura'  => 0.0,
                'razonamiento' => 'low',
            ]);
        } catch (\Throwable $e) {
            Log::warning("ConversacionBotService: no se pudo analizar la conversación #{$conversacion->id} — {$e->getMessage()}");
            return;
        }

        $datos = IA::json((string) ($r['texto'] ?? ''));
        if (! is_array($datos)) {
            return;
        }

        $analisis = self::normalizarAnalisis($datos);

        $conversacion->problem_category = $analisis['categoria'];
        $conversacion->resolved = $analisis['resuelto'] && ! $conversacion->escalated;
        $conversacion->lead_data = ['resumen' => $analisis['resumen'], 'lead' => $analisis['lead']];
        $conversacion->cost_usd = (float) $conversacion->cost_usd + $this->costo($r);
        $conversacion->save();

        $lead = $analisis['lead'];
        if ($conversacion->customer_id || ! $lead['interesado']) {
            return;
        }

        IaBotLead::firstOrCreate(['conversation_id' => $conversacion->id], [
            'phone'            => $conversacion->phone_number,
            'name'             => $lead['nombre'] ?: null,
            'email'            => $lead['email'] ?: null,
            'address'          => $lead['direccion'] ?: null,
            'interest_service' => $lead['servicio'] ?: null,
            'interest_plan'    => $lead['plan'] ?: null,
            'status'           => 'new',
            'notes'            => $analisis['resumen'] ?: null,
        ]);
    }

    /**
     * Lo que devolvió la IA, con tipos y largos seguros para la base.
     *
     * @return array{resumen: string, categoria: string, resuelto: bool, lead: array{interesado: bool, nombre: string, direccion: string, email: string, servicio: string, plan: string}}
     */
    public static function normalizarAnalisis(array $datos): array
    {
        $texto = fn ($v, int $max) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $lead = is_array($datos['lead'] ?? null) ? $datos['lead'] : [];
        $categoria = (string) ($datos['categoria'] ?? '');
        $email = $texto($lead['email'] ?? '', 255);

        return [
            'resumen'   => $texto($datos['resumen'] ?? '', 500),
            'categoria' => array_key_exists($categoria, self::CATEGORIAS) ? $categoria : 'otro',
            'resuelto'  => filter_var($datos['resuelto'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'lead'      => [
                'interesado' => filter_var($lead['interesado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'nombre'     => $texto($lead['nombre'] ?? '', 255),
                'direccion'  => $texto($lead['direccion'] ?? '', 500),
                'email'      => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                'servicio'   => $texto($lead['servicio'] ?? '', 100),
                'plan'       => $texto($lead['plan'] ?? '', 100),
            ],
        ];
    }

    private function costo(array $resultado): float
    {
        return $this->pricing->calcularCosto(
            (string) ($resultado['modelo'] ?? ''),
            (int) ($resultado['tokens_input'] ?? 0),
            (int) ($resultado['tokens_output'] ?? 0)
        );
    }

    private function registrarTurnoEnTranscript(IaBotConversation $conversacion, string $mensajeUsuario, string $respuesta): void
    {
        $transcript = is_array($conversacion->transcript) ? $conversacion->transcript : [];
        $transcript[] = ['rol' => 'user', 'contenido' => $mensajeUsuario];
        $transcript[] = ['rol' => 'assistant', 'contenido' => $respuesta];
        $conversacion->transcript = $transcript;
    }

    /**
     * La base de conocimiento son pocas filas: cabe entera como contexto, sin búsqueda
     * semántica (RAG) para este volumen.
     */
    private function bloqueBaseConocimiento(): string
    {
        $filas = IaBotKnowledgeBase::orderBy('category')->get();
        if ($filas->isEmpty()) {
            return '';
        }

        $lineas = ['BASE DE CONOCIMIENTO (guía la conversación con esto, en tus propias palabras y paso por paso; si lo marca, transfiere cuando no se resuelva):'];
        foreach ($filas as $kb) {
            $pasos = is_array($kb->solution_steps) ? implode(' → ', array_filter(array_map('trim', $kb->solution_steps))) : '';
            $lineas[] = "- [{$kb->category}] {$kb->problem}: {$pasos}" . ($kb->escalate_if_fails ? ' (si no se resuelve, transfiere)' : '');
        }

        return implode("\n", $lineas);
    }
}
