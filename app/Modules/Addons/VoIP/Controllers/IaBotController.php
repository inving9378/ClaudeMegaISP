<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\VoIP\Console\BotVozEscucharCommand;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Models\IaBotKnowledgeBase;
use App\Modules\Addons\VoIP\Models\IaBotLead;
use App\Modules\Addons\VoIP\Services\DialplanGeneratorService;
use App\Modules\Addons\VoIP\Services\IABotCustomerService;
use App\Modules\Addons\VoIP\Services\Voz\ConversacionBotService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class IaBotController extends Controller
{
    /** Las tres funciones de IA que necesita la asistente (Integraciones → Módulos IA). */
    private const FUNCIONES_IA = [
        'voip.bot_voz'       => 'Conversación',
        'voip.transcripcion' => 'Oído',
        'voip.voz'           => 'Voz',
    ];

    public function __construct(private DialplanGeneratorService $dialplan)
    {
    }

    // ── Vista principal ───────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('voip.ia-bot.view');
        return view('addon-voip::ia-bot.index');
    }

    // ── Config ────────────────────────────────────────────────────────────────

    public function getConfig(): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');

        $config = IaBotConfig::current();
        $datos = $config->toArray();
        foreach (['horario_inicio', 'horario_fin'] as $hora) {
            $datos[$hora] = substr((string) $config->{$hora}, 0, 5);
        }
        $datos['tono'] = $config->tono ?: VoiceTtsService::TONO_DEFECTO;
        $datos['velocidad'] = $config->velocidad ?: VoiceTtsService::VELOCIDAD_DEFECTO;
        $datos['lineas'] = $config->lineasEfectivas();

        return response()->json([
            'config'    => $datos,
            'catalogos' => [
                'voces'      => collect(VoiceTtsService::VOCES)->map(fn ($e, $id) => ['id' => $id, 'etiqueta' => $e])->values(),
                'tonos'      => collect(VoiceTtsService::TONOS)->map(fn ($t, $id) => ['id' => $id, 'etiqueta' => $t[0]])->values(),
                'velocidad'  => ['min' => VoiceTtsService::VELOCIDAD_MIN, 'max' => VoiceTtsService::VELOCIDAD_MAX, 'defecto' => VoiceTtsService::VELOCIDAD_DEFECTO],
                'lineas_max' => IaBotConfig::LINEAS_MAX,
                'categorias' => ConversacionBotService::CATEGORIAS,
                'prompt_base' => ConversacionBotService::PROMPT_BASE,
            ],
        ]);
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.config');

        // Las horas llegan como "09:00" o, del round-trip viejo, "09:00:00" (24-sep: por eso
        // fallaba TODO guardado). Se normalizan antes de validar.
        foreach (['horario_inicio', 'horario_fin'] as $campoHorario) {
            $valor = $request->input($campoHorario);
            if (is_string($valor) && strlen($valor) > 5) {
                $request->merge([$campoHorario => substr($valor, 0, 5)]);
            }
        }

        $data = $request->validate([
            'enabled'              => 'boolean',
            'piloto_porcentaje'    => 'integer|min:0|max:100',
            'lineas'               => 'integer|min:1|max:' . IaBotConfig::LINEAS_MAX,
            'horario_inicio'       => 'date_format:H:i',
            'horario_fin'          => 'date_format:H:i|after:horario_inicio',
            'voice'                => 'string|in:' . implode(',', array_keys(VoiceTtsService::VOCES)),
            'tono'                 => 'string|in:' . implode(',', array_keys(VoiceTtsService::TONOS)),
            'velocidad'            => 'numeric|min:' . VoiceTtsService::VELOCIDAD_MIN . '|max:' . VoiceTtsService::VELOCIDAD_MAX,
            'temperature'          => 'numeric|min:0|max:1',
            'max_turns'            => 'integer|min:3|max:30',
            'max_duration_seconds' => 'integer|min:60|max:900',
            'timeout_seconds'      => 'integer|min:5|max:30',
            'greeting_customer'    => 'nullable|string|max:500',
            'greeting_lead'        => 'string|max:500',
            'system_prompt'        => 'nullable|string|max:20000',
        ], [
            'horario_fin.after' => 'La hora de fin debe ser después de la de inicio (no cruza la medianoche).',
            'greeting_lead.max' => 'El saludo puede tener hasta 500 caracteres.',
        ]);

        // system_prompt es NOT NULL: vacío = las instrucciones base.
        if (array_key_exists('system_prompt', $data)) {
            $data['system_prompt'] = (string) $data['system_prompt'];
        }
        if (array_key_exists('greeting_customer', $data)) {
            $data['greeting_customer'] = (string) $data['greeting_customer'];
        }

        $config = IaBotConfig::current();
        $config->update($data);

        // `enabled` decide si el plan de marcado siquiera intenta AudioSocket() (texto estático):
        // hay que regenerarlo. Piloto, horario y líneas los evalúa el servicio en vivo.
        try {
            $this->dialplan->regenerar();
        } catch (\Throwable $e) {
            Log::warning("VoIP: error regenerando dialplan tras guardar ia_bot_config: {$e->getMessage()}");
        }

        return $this->getConfig();
    }

    /**
     * ¿Está lista para contestar? Lo que la pantalla muestra arriba: el servicio, las tres
     * funciones de IA (con su proveedor y modelo, o por qué no sirven), en qué grupos atiende
     * y cómo le fue hoy. Nunca muestra llaves.
     */
    public function estado(): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');

        $host = (string) config('voip.bot_voz.host', '127.0.0.1');
        $port = (int) config('voip.bot_voz.port', 9099);
        $sock = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if ($sock) {
            fclose($sock);
        }

        $funciones = [];
        foreach (self::FUNCIONES_IA as $clave => $nombre) {
            try {
                $c = IA::credencialesPara($clave);
                $problema = trim((string) $c['llave']) === '' && $c['integracion']->encrypted_value
                    ? "La llave de «{$c['proveedor']}» no se puede leer (se guardó con otra APP_KEY). Pégala de nuevo en Integraciones."
                    : null;
                $funciones[] = ['clave' => $clave, 'nombre' => $nombre, 'ok' => $problema === null, 'proveedor' => $c['proveedor'], 'modelo' => $c['modelo'], 'problema' => $problema];
            } catch (IANoConfigurada $e) {
                $funciones[] = ['clave' => $clave, 'nombre' => $nombre, 'ok' => false, 'proveedor' => null, 'modelo' => null, 'problema' => $e->getMessage()];
            } catch (\Throwable $e) {
                $funciones[] = ['clave' => $clave, 'nombre' => $nombre, 'ok' => false, 'proveedor' => null, 'modelo' => null, 'problema' => 'No se pudo leer la asignación.'];
            }
        }

        $hoy = IaBotConversation::where('started_at', '>=', now()->startOfDay())->where('call_id', 'not like', 'test-%');

        return response()->json([
            'servicio'  => ['activo' => (bool) $sock, 'direccion' => "{$host}:{$port}"],
            'funciones' => $funciones,
            'voz_tono'  => VoiceTtsService::soportaTono(collect($funciones)->firstWhere('clave', 'voip.voz')['modelo'] ?? null),
            'grupos'    => GrupoTimbrado::where('activo', true)->where('es_cola', true)->orderBy('nombre')->get(['id', 'nombre'])->values(),
            'hoy'       => [
                'atendidas'   => (clone $hoy)->count(),
                'transferidas' => (clone $hoy)->where('status', 'transferred')->count(),
                'clientes'    => (clone $hoy)->whereNotNull('customer_id')->count(),
                'costo_usd'   => round((float) (clone $hoy)->sum('cost_usd'), 4),
            ],
            'leads_nuevos' => IaBotLead::where('status', 'new')->count(),
            'ultima'       => IaBotConversation::where('call_id', 'not like', 'test-%')->max('started_at'),
        ]);
    }

    /**
     * Probar a la asistente sin llamar: lo que contestaría (con los cambios aún sin guardar) y,
     * si se pide, cómo suena. Opcionalmente como si llamara el número de un cliente. Nada se
     * guarda en conversaciones; cada prueba sí gasta IA (queda en Módulos IA).
     */
    public function probar(Request $request, ConversacionBotService $cerebro, VoiceTtsService $tts, IABotCustomerService $clientes): JsonResponse
    {
        $this->authorize('voip.ia-bot.config');

        $data = $request->validate([
            'texto'                 => 'required|string|max:500',
            'historial'             => 'array|max:80',
            'historial.*.rol'       => 'required|in:user,assistant',
            'historial.*.contenido' => 'required|string|max:2000',
            'numero'                => ['nullable', 'string', 'max:20', 'regex:/^[+0-9 ()\-]*$/'],
            'con_voz'               => 'boolean',
            'config'                => 'array',
            'config.system_prompt'  => 'nullable|string|max:20000',
            'config.temperature'    => 'nullable|numeric|min:0|max:1',
            'config.voice'          => 'nullable|string',
            'config.tono'           => 'nullable|string',
            'config.velocidad'      => 'nullable|numeric',
            'config.greeting_customer' => 'nullable|string|max:500',
            'config.greeting_lead'  => 'nullable|string|max:500',
        ]);

        $config = $this->configConCambios($data['config'] ?? []);
        $cliente = $this->clientePara($data['numero'] ?? null, $clientes);

        // Como en la llamada: la conversación empieza con el saludo (así la IA no vuelve a saludar).
        $historial = $data['historial'] ?? [];
        $saludo = null;
        if ($historial === []) {
            $saludo = BotVozEscucharCommand::saludo($config, $cliente);
            $historial = $saludo !== '' ? [['rol' => 'assistant', 'contenido' => $saludo]] : [];
        }

        $t0 = microtime(true);
        $r = $cerebro->responder($historial, $data['texto'], $cliente, $config);
        $ms = (int) round((microtime(true) - $t0) * 1000);

        $salida = [
            'saludo'     => $saludo,
            'respuesta'  => $r['texto_hablado'],
            'transferir' => $r['transferir'],
            'costo_usd'  => round($r['costo_usd'], 5),
            'ms'         => $ms,
            'error'      => $r['error'] ?? null,
            'cliente'    => $this->clienteParaPantalla($cliente),
        ];

        if (($data['con_voz'] ?? false) && $r['texto_hablado'] !== '') {
            $audio = $tts->muestraMp3($r['texto_hablado'], $config->opcionesVoz());
            $salida['audio'] = $audio['ok'] ? base64_encode($audio['mp3']) : null;
            $salida['error_voz'] = $audio['ok'] ? null : $audio['error'];
        }

        return response()->json($salida);
    }

    /** El saludo (de cliente o de alguien nuevo) con la voz elegida, como MP3. */
    public function escuchar(Request $request, VoiceTtsService $tts)
    {
        $this->authorize('voip.ia-bot.config');

        $data = $request->validate([
            'para'                     => 'required|in:cliente,nuevo',
            'config'                   => 'array',
            'config.greeting_customer' => 'nullable|string|max:500',
            'config.greeting_lead'     => 'nullable|string|max:500',
            'config.voice'             => 'nullable|string',
            'config.tono'              => 'nullable|string',
            'config.velocidad'         => 'nullable|numeric',
        ]);

        $config = $this->configConCambios($data['config'] ?? []);
        $cliente = $data['para'] === 'cliente' ? ['found' => true, 'nombre_pila' => 'Juan'] : null;
        $texto = BotVozEscucharCommand::saludo($config, $cliente);

        $r = $tts->muestraMp3($texto, $config->opcionesVoz());
        if (! $r['ok']) {
            return response()->json(['message' => $r['error']], 422);
        }

        return new Response($r['mp3'], 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'no-store']);
    }

    // ── Conversaciones ────────────────────────────────────────────────────────

    public function conversaciones(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');

        $q = IaBotConversation::latest('started_at')
            ->latest('id')
            ->where('call_id', 'not like', 'test-%')
            ->when($request->type, fn ($q, $t) => $q->where('conversation_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->categoria, fn ($q, $c) => $q->where('problem_category', $c))
            ->when($request->buscar, function ($q, $b) {
                $b = trim((string) $b);
                $q->where(fn ($w) => $w->where('phone_number', 'like', "%{$b}%")->orWhere('customer_name', 'like', "%{$b}%"));
            });

        return response()->json($q->paginate(20)->through(fn (IaBotConversation $c) => [
            'id'             => $c->id,
            'telefono'       => $c->phone_number,
            'cliente_id'     => $c->customer_id,
            'cliente'        => $c->customer_name,
            'tipo'           => $c->conversation_type,
            'estado'         => $c->status,
            'categoria'      => $c->problem_category,
            'resumen'        => data_get($c->lead_data, 'resumen'),
            'resuelta'       => (bool) $c->resolved,
            'inicio'         => $c->started_at?->toIso8601String(),
            'duracion'       => $c->duration,
            'costo_usd'      => (float) $c->cost_usd,
            'turnos'         => collect(is_array($c->transcript) ? $c->transcript : [])
                ->map(fn ($t) => [
                    'quien' => ($t['rol'] ?? $t['role'] ?? '') === 'user' ? 'cliente' : 'asistente',
                    'texto' => (string) ($t['contenido'] ?? $t['content'] ?? $t['text'] ?? ''),
                ])->values(),
        ]));
    }

    // ── Leads ─────────────────────────────────────────────────────────────────

    public function leads(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.leads');

        $q = IaBotLead::with('conversation:id,started_at,phone_number,lead_data')
            ->latest()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));

        return response()->json($q->paginate(20));
    }

    public function updateLead(Request $request, IaBotLead $lead): JsonResponse
    {
        $this->authorize('voip.ia-bot.leads');

        $data = $request->validate([
            'status'              => 'in:new,contacted,interested,converted,rejected',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'notes'               => 'nullable|string|max:5000',
        ]);

        $lead->update($data);

        return response()->json(['success' => true, 'data' => $lead->fresh()]);
    }

    // ── Base de conocimiento ──────────────────────────────────────────────────

    public function kbIndex(): JsonResponse
    {
        $this->authorize('voip.ia-bot.view');
        return response()->json(['data' => IaBotKnowledgeBase::orderBy('category')->orderBy('problem')->get()]);
    }

    public function kbStore(Request $request): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');

        $kb = IaBotKnowledgeBase::create($this->datosKb($request, true));

        return response()->json(['success' => true, 'data' => $kb], 201);
    }

    public function kbUpdate(Request $request, IaBotKnowledgeBase $kb): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');

        $kb->update($this->datosKb($request, false));

        return response()->json(['success' => true, 'data' => $kb->fresh()]);
    }

    public function kbDestroy(IaBotKnowledgeBase $kb): JsonResponse
    {
        $this->authorize('voip.ia-bot.kb');
        $kb->delete();
        return response()->json(['success' => true]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** Los pasos llegan como lista; se guardan como lista sin vacíos (antes {step1: …}). */
    private function datosKb(Request $request, bool $alta): array
    {
        $requerido = $alta ? 'required' : 'sometimes';
        $data = $request->validate([
            'category'          => "{$requerido}|string|max:100",
            'problem'           => "{$requerido}|string|max:255",
            'keywords'          => 'nullable|string|max:500',
            'solution_steps'    => "{$requerido}|array|min:1|max:20",
            'solution_steps.*'  => 'nullable|string|max:500',
            'escalate_if_fails' => 'boolean',
        ], [
            'solution_steps.required' => 'Escribe al menos un paso.',
            'solution_steps.min'      => 'Escribe al menos un paso.',
        ]);

        if (array_key_exists('solution_steps', $data)) {
            $pasos = array_values(array_filter(array_map(fn ($p) => trim((string) $p), $data['solution_steps']), fn ($p) => $p !== ''));
            if ($pasos === []) {
                abort(response()->json(['message' => 'Escribe al menos un paso.', 'errors' => ['solution_steps' => ['Escribe al menos un paso.']]], 422));
            }
            $data['solution_steps'] = $pasos;
        }

        return $data;
    }

    /** La configuración guardada con los cambios de la pantalla encima, sin guardarlos. */
    private function configConCambios(array $cambios): IaBotConfig
    {
        $config = clone IaBotConfig::current();
        foreach (['system_prompt', 'temperature', 'voice', 'tono', 'velocidad', 'greeting_customer', 'greeting_lead'] as $campo) {
            if (array_key_exists($campo, $cambios) && $cambios[$campo] !== null) {
                $config->{$campo} = $cambios[$campo];
            }
        }

        return $config;
    }

    private function clientePara(?string $numero, IABotCustomerService $clientes): ?array
    {
        if (trim((string) $numero) === '') {
            return null;
        }
        $cliente = $clientes->identifyCustomer($numero);
        if (empty($cliente['found'])) {
            return ['found' => false];
        }

        return $cliente + $clientes->contextoParaBot((int) $cliente['id']);
    }

    /** Lo que la pantalla de prueba enseña del cliente encontrado (sin datos de más). */
    private function clienteParaPantalla(?array $cliente): ?array
    {
        if ($cliente === null) {
            return null;
        }
        if (empty($cliente['found'])) {
            return ['encontrado' => false];
        }

        return [
            'encontrado' => true,
            'id'         => $cliente['id'],
            'nombre'     => $cliente['name'],
            'estado'     => $cliente['estado'] ?? null,
            'saldo'      => $cliente['saldo'] ?? 0,
        ];
    }
}
