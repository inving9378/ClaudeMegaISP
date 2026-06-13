#!/usr/bin/env php
<?php
/**
 * MegaVoz Asistente IA — AGI Script
 * Atención a clientes (diagnóstico) + captación de leads (ventas)
 *
 * Flujo: Asterisk → AGI → Identifica cliente → Loop STT/GPT/TTS → Cierre o Transferencia
 *
 * Prerequisitos:
 *   - OpenAI API Key en Integration Hub (provider: openai) o OPENAI_API_KEY en .env
 *   - ffmpeg instalado: /usr/bin/ffmpeg
 *   - /tmp escribible por el usuario asterisk
 */

declare(strict_types=1);

define('LARAVEL_START', microtime(true));

$basePath = realpath(__DIR__ . '/../../../');
require $basePath . '/vendor/autoload.php';

$app = require $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Models\IaBotConversation;
use App\Modules\Addons\VoIP\Models\IaBotKnowledgeBase;
use App\Modules\Addons\VoIP\Models\IaBotLead;
use App\Modules\Addons\VoIP\Services\IABotCustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ─────────────────────────────────────────────────────────────────────────────
// AGI helper — comunicación con Asterisk vía stdin/stdout
// ─────────────────────────────────────────────────────────────────────────────
class Agi
{
    public array $env = [];

    public function __construct()
    {
        // Leer variables de entorno que Asterisk envía en la primera fase
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') break;
            if (str_starts_with($line, 'agi_')) {
                [$key, $val] = explode(': ', $line, 2);
                $this->env[$key] = $val;
            }
        }
    }

    /** Envía un comando y lee la respuesta (200 result=X ...) */
    public function cmd(string $command): array
    {
        fwrite(STDOUT, $command . "\n");
        fflush(STDOUT);
        $response = fgets(STDIN);
        if ($response === false) return ['code' => 0, 'result' => -1, 'raw' => ''];
        $response = trim($response);
        preg_match('/^(\d+)\s+result=(-?\d+)\s*(.*)$/', $response, $m);
        return [
            'code'   => (int)($m[1] ?? 0),
            'result' => (int)($m[2] ?? -1),
            'extra'  => trim($m[3] ?? ''),
            'raw'    => $response,
        ];
    }

    public function verbose(string $msg, int $level = 1): void
    {
        $this->cmd("VERBOSE \"{$msg}\" {$level}");
    }

    public function answer(): array
    {
        return $this->cmd('ANSWER');
    }

    public function hangup(): array
    {
        return $this->cmd('HANGUP');
    }

    /**
     * Reproducir archivo de audio (sin extensión).
     * Asterisk busca .ulaw, .gsm, .wav, etc.
     */
    public function streamFile(string $filename, string $escapeDigits = ''): array
    {
        return $this->cmd("STREAM FILE \"{$filename}\" \"{$escapeDigits}\"");
    }

    /**
     * Grabar audio del caller.
     * @param string $filename  ruta sin extensión
     * @param string $format    formato (wav, gsm, ulaw)
     * @param int    $timeout   ms antes de empezar (-1 = sin timeout inicial)
     * @param int    $maxLen    ms máximos de grabación
     * @param int    $silence   ms de silencio para cortar (0 = sin detección)
     */
    public function recordFile(string $filename, string $format = 'wav', int $timeout = 5000, int $maxLen = 15000, int $silence = 2000): array
    {
        $escDigits = '#';
        $silenceArg = $silence > 0 ? " s={$silence}" : '';
        return $this->cmd("RECORD FILE \"{$filename}\" {$format} \"{$escDigits}\" {$maxLen} {$timeout}{$silenceArg}");
    }

    /** Ejecutar aplicación de dialplan */
    public function exec(string $app, string $args = ''): array
    {
        return $this->cmd("EXEC {$app} \"{$args}\"");
    }

    /** Obtener variable de canal */
    public function getVariable(string $var): string
    {
        $r = $this->cmd("GET VARIABLE \"{$var}\"");
        preg_match('/\(([^)]*)\)/', $r['extra'], $m);
        return $m[1] ?? '';
    }

    /** Establecer variable de canal */
    public function setVariable(string $var, string $val): void
    {
        $this->cmd("SET VARIABLE \"{$var}\" \"{$val}\"");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Asistente principal
// ─────────────────────────────────────────────────────────────────────────────
class MegaVozAssistant
{
    private Agi    $agi;
    private string $callId;
    private string $callerNumber;
    private float  $startTime;

    private IaBotConfig $config;
    private string $openaiKey;

    private array $transcript = [];
    private array $customerContext = [];
    private string $conversationType = 'sales_lead';
    private bool $transferred = false;
    private string $transferredTo = '';
    private float $totalCost = 0.0;

    public function __construct(Agi $agi)
    {
        $this->agi          = $agi;
        $this->callId       = $agi->env['agi_uniqueid']  ?? ('call_' . uniqid());
        $this->callerNumber = $agi->env['agi_callerid']  ?? 'unknown';
        $this->startTime    = microtime(true);
        $this->config       = IaBotConfig::current();

        // Resolver API key (Integration Hub → .env)
        $this->openaiKey = $this->resolveOpenAiKey();

        $this->log("Iniciando | Caller: {$this->callerNumber}");
    }

    public function run(): void
    {
        if (! $this->config->enabled) {
            $this->agi->verbose('IA Bot deshabilitado');
            $this->agi->hangup();
            return;
        }

        if (empty($this->openaiKey)) {
            $this->agi->verbose('IA Bot: OpenAI key no configurada — colgando');
            $this->agi->hangup();
            return;
        }

        try {
            $this->agi->answer();
            sleep(1);

            $this->identifyAndGreet();
            $this->conversationLoop();

            $this->saveConversation($this->transferred ? 'transferred' : 'completed');
        } catch (\Throwable $e) {
            $this->log("ERROR: {$e->getMessage()}", true);
            $this->saveConversation('failed');
            $this->agi->hangup();
        }
    }

    // ── Identificación y saludo ───────────────────────────────────────────────

    private function identifyAndGreet(): void
    {
        $svc = new IABotCustomerService();
        $customer = $svc->identifyCustomer($this->callerNumber);

        if ($customer['found']) {
            $this->conversationType = 'customer_support';
            $this->customerContext  = $customer;
            $greeting = str_replace('[nombre]', $customer['name'], $this->config->greeting_customer);
            $this->log("Cliente identificado: {$customer['name']}");
        } else {
            $this->conversationType = 'sales_lead';
            $this->customerContext  = ['phone' => $customer['phone']];
            $greeting = $this->config->greeting_lead;
            $this->log("Nuevo interesado");
        }

        if (! $this->playText($greeting)) {
            $this->log('TTS del saludo inicial falló — finalizando llamada', true);
            throw new \RuntimeException('TTS greeting failed — call terminated');
        }
    }

    // ── Loop principal ────────────────────────────────────────────────────────

    private function conversationLoop(): void
    {
        $turns        = 0;
        $maxTurns     = $this->config->max_turns;
        $silences     = 0;
        $maxDuration  = $this->config->max_duration_seconds;

        while ($turns < $maxTurns) {
            if ((microtime(true) - $this->startTime) >= $maxDuration) {
                $this->playText('Ha superado el tiempo máximo de esta llamada. Muchas gracias por comunicarse con Meganet.');
                $this->saveConversation('timeout');
                $this->agi->hangup();
                return;
            }

            // Grabar turno del usuario
            $tmpBase = "/tmp/megavoz_{$this->callId}_t{$turns}";
            $recResult = $this->agi->recordFile($tmpBase, 'wav', 1000, $this->config->timeout_seconds * 1000 + 2000, 1500);

            $wavFile = "{$tmpBase}.wav";
            if (! file_exists($wavFile) || filesize($wavFile) < 500) {
                $silences++;
                if ($silences >= 2) {
                    $this->playText('Parece que no hay nadie. Gracias por llamar a Meganet. ¡Hasta luego!');
                    break;
                }
                $this->playText('¿Sigues ahí? ¿En qué puedo ayudarte?');
                continue;
            }
            $silences = 0;

            // STT — Whisper
            $userText = $this->transcribe($wavFile);
            @unlink($wavFile);

            if (empty(trim($userText))) {
                $this->playText('No te escuché bien, ¿puedes repetir?');
                continue;
            }

            $this->log("Usuario: {$userText}");
            $this->transcript[] = ['role' => 'user', 'text' => $userText];

            // Detectar transferencia explícita
            if ($this->wantsHuman($userText)) {
                $this->playText('Por supuesto, te comunico con un asesor. Un momento por favor.');
                $this->doTransfer();
                return;
            }

            // GPT-4 respuesta
            $response = $this->chatGpt($userText);
            if (empty($response)) {
                $this->playText('Disculpa, tuve un problema. Te transfiero con un asesor.');
                $this->doTransfer();
                return;
            }

            $this->log("María: {$response}");

            // Detectar marcador de transferencia en respuesta del bot
            if (str_contains($response, '[TRANSFERIR]')) {
                $clean = trim(str_replace('[TRANSFERIR]', '', $response));
                if ($clean) $this->playText($clean);
                $this->doTransfer();
                return;
            }

            $this->transcript[] = ['role' => 'assistant', 'text' => $response];
            $this->playText($response);

            // Detectar despedida
            if ($this->isClosing($response)) {
                break;
            }

            $turns++;
        }

        if ($turns >= $maxTurns) {
            $this->playText('Hemos llegado al límite de esta conversación. Si necesitas más ayuda, no dudes en volver a llamarnos. ¡Hasta luego!');
        }

        $this->agi->hangup();
    }

    // ── OpenAI Whisper STT ────────────────────────────────────────────────────

    private function transcribe(string $wavFile): string
    {
        // Whisper acepta WAV 8kHz directamente, pero prefiere 16kHz+
        // Convertimos a mp3 para reducir tamaño y asegurar compatibilidad
        $mp3File = str_replace('.wav', '.mp3', $wavFile);
        exec("/usr/bin/ffmpeg -y -i {$wavFile} -ar 16000 -ac 1 {$mp3File} 2>/dev/null");

        $audioPath = file_exists($mp3File) ? $mp3File : $wavFile;
        if (! file_exists($audioPath) || filesize($audioPath) < 100) {
            return '';
        }

        $boundary = '----FormBoundary' . bin2hex(random_bytes(8));
        $fileData = file_get_contents($audioPath);
        $mimeType = str_ends_with($audioPath, '.mp3') ? 'audio/mpeg' : 'audio/wav';
        $fileName = basename($audioPath);

        $body = "--{$boundary}\r\n"
              . "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n"
              . "Content-Type: {$mimeType}\r\n\r\n"
              . $fileData . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Disposition: form-data; name=\"model\"\r\n\r\nwhisper-1\r\n"
              . "--{$boundary}\r\n"
              . "Content-Disposition: form-data; name=\"language\"\r\n\r\nes\r\n"
              . "--{$boundary}--\r\n";

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_HTTPHEADER      => [
                "Authorization: Bearer {$this->openaiKey}",
                "Content-Type: multipart/form-data; boundary={$boundary}",
            ],
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 5,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        @unlink($mp3File);

        if ($err) { $this->log("Whisper curl error: {$err}", true); return ''; }

        $data = json_decode($resp, true);
        return trim($data['text'] ?? '');
    }

    // ── GPT-4 Chat ────────────────────────────────────────────────────────────

    private function chatGpt(string $userMessage): string
    {
        $systemPrompt = $this->buildSystemPrompt();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Historial (últimos 8 turnos para no inflar el contexto)
        $history = array_slice($this->transcript, -8);
        foreach ($history as $t) {
            $messages[] = ['role' => $t['role'], 'content' => $t['text']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = json_encode([
            'model'       => 'gpt-4o-mini',
            'messages'    => $messages,
            'temperature' => $this->config->temperature,
            'max_tokens'  => 300,
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_HTTPHEADER      => [
                "Authorization: Bearer {$this->openaiKey}",
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 5,
        ]);

        $resp    = curl_exec($ch);
        $httpGpt = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($err || $httpGpt !== 200) { $this->log("GPT error: code={$httpGpt} err={$err}", true); return ''; }

        $data = json_decode($resp, true);

        // Contabilizar costo aproximado
        $tokensIn  = $data['usage']['prompt_tokens']     ?? 0;
        $tokensOut = $data['usage']['completion_tokens'] ?? 0;
        $this->totalCost += ($tokensIn * 0.00000015) + ($tokensOut * 0.0000006);

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    // ── OpenAI TTS + reproducción ─────────────────────────────────────────────

    private function playText(string $text): bool
    {
        if (empty(trim($text))) return true;

        $tmpMp3  = "/tmp/megavoz_tts_{$this->callId}_" . microtime(true) . ".mp3";
        $tmpUlaw = str_replace('.mp3', '.ulaw', $tmpMp3);

        // Llamar TTS
        $payload = json_encode([
            'model'           => 'tts-1',
            'input'           => $text,
            'voice'           => $this->config->voice,
            'response_format' => 'mp3',
            'speed'           => 1.0,
        ]);

        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_HTTPHEADER      => [
                "Authorization: Bearer {$this->openaiKey}",
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 5,
        ]);

        $audioData = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err       = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || strlen($audioData) < 100) {
            $this->log("TTS error: code={$httpCode} err={$err}", true);
            return false;
        }

        // Costo TTS: $0.015/1K chars
        $this->totalCost += (mb_strlen($text) / 1000) * 0.015;

        file_put_contents($tmpMp3, $audioData);

        // Convertir MP3 → ulaw 8kHz mono (formato nativo Asterisk)
        exec("/usr/bin/ffmpeg -y -i {$tmpMp3} -ar 8000 -ac 1 -f mulaw {$tmpUlaw} 2>/dev/null", $out, $rc);

        @unlink($tmpMp3);

        if ($rc !== 0 || ! file_exists($tmpUlaw) || filesize($tmpUlaw) < 10) {
            $this->log("ffmpeg conversion failed rc={$rc}", true);
            return false;
        }

        // STREAM FILE espera la ruta sin extensión
        $base = substr($tmpUlaw, 0, -5); // quita ".ulaw"
        $this->agi->streamFile($base, '#');

        @unlink($tmpUlaw);
        return true;
    }

    // ── Transferir a agente ───────────────────────────────────────────────────

    private function doTransfer(): void
    {
        $grupo = $this->config->grupo_timbrado_name;
        $this->transferred   = true;
        $this->transferredTo = $grupo ?: '';

        if ($grupo) {
            $this->agi->exec('Dial', "{$grupo},30,tT");
        } else {
            $this->agi->verbose('IA Bot: no hay grupo de timbrado configurado');
        }

        $this->agi->hangup();
    }

    // ── Construcción del system prompt contextualizado ────────────────────────

    private function buildSystemPrompt(): string
    {
        $base = $this->config->system_prompt;

        if ($this->conversationType === 'customer_support') {
            $ctx  = "INFORMACIÓN DEL CLIENTE:\n";
            $ctx .= "  Nombre: {$this->customerContext['name']}\n";
            $ctx .= "  Teléfono: {$this->customerContext['phone']}\n";
            $ctx .= "  Email: " . ($this->customerContext['email'] ?? 'no disponible') . "\n";

            if (! empty($this->customerContext['active_services'])) {
                $ctx .= "  Servicios activos:\n";
                foreach ($this->customerContext['active_services'] as $svc) {
                    $ctx .= "    - {$svc['description']} {$svc['amount']}{$svc['unity']} desde {$svc['start_date']}\n";
                }
            }

            $kb = IaBotKnowledgeBase::all();
            if ($kb->isNotEmpty()) {
                $ctx .= "\nBASE DE CONOCIMIENTO (usa estos pasos cuando el cliente reporte un problema):\n";
                foreach ($kb as $item) {
                    $steps = implode(' → ', array_values($item->solution_steps));
                    $ctx  .= "  [{$item->category}] {$item->problem}: {$steps}\n";
                    if ($item->escalate_if_fails) {
                        $ctx .= "    (Si no resuelve en 3 intentos, sugiere transferencia a asesor)\n";
                    }
                }
            }

            $ctx .= "\nIMPORTANTE: Saluda al cliente por su nombre y personaliza la atención.";
            return $base . "\n\n" . $ctx;
        }

        // sales_lead
        return $base . "\n\nTIPO DE LLAMADA: Nuevo interesado sin cuenta Meganet. "
             . "Objetivo: entender qué servicio necesita, ofrecer planes de internet/VoIP, "
             . "capturar nombre, teléfono y dirección para hacer el seguimiento comercial.";
    }

    // ── Guardar conversación en BD ────────────────────────────────────────────

    private function saveConversation(string $status): void
    {
        try {
            $duration = (int)(microtime(true) - $this->startTime);

            $conv = IaBotConversation::create([
                'call_id'           => $this->callId,
                'phone_number'      => $this->callerNumber,
                'customer_id'       => $this->customerContext['id'] ?? null,
                'customer_name'     => $this->customerContext['name'] ?? null,
                'customer_email'    => $this->customerContext['email'] ?? null,
                'conversation_type' => $this->conversationType,
                'started_at'        => date('Y-m-d H:i:s', (int)$this->startTime),
                'ended_at'          => date('Y-m-d H:i:s'),
                'duration'          => $duration,
                'transcript'        => $this->transcript,
                'resolved'          => false,
                'escalated'         => $this->transferred,
                'escalated_to'      => $this->transferredTo ?: null,
                'transferred_at'    => $this->transferred ? date('Y-m-d H:i:s') : null,
                'cost_usd'          => round($this->totalCost, 4),
                'status'            => $status,
            ]);

            // Extraer datos de lead si es sales_lead
            if ($this->conversationType === 'sales_lead') {
                $leadData = $this->extractLeadData();
                IaBotLead::create([
                    'conversation_id' => $conv->id,
                    'phone'           => $this->callerNumber,
                    'name'            => $leadData['name'],
                    'email'           => $leadData['email'],
                    'address'         => $leadData['address'],
                    'interest_service' => $leadData['interest_service'],
                    'interest_plan'   => $leadData['interest_plan'],
                    'status'          => 'new',
                ]);
            }
        } catch (\Throwable $e) {
            $this->log("Error guardando conversación: {$e->getMessage()}", true);
        }
    }

    /** Extrae datos estructurados de la transcripción vía GPT */
    private function extractLeadData(): array
    {
        if (empty($this->transcript)) {
            return ['name' => null, 'email' => null, 'address' => null, 'interest_service' => null, 'interest_plan' => null];
        }

        $fullTranscript = implode("\n", array_map(
            fn ($t) => strtoupper($t['role']) . ': ' . $t['text'],
            $this->transcript
        ));

        $payload = json_encode([
            'model'       => 'gpt-4o-mini',
            'messages'    => [
                ['role' => 'system', 'content' => 'Extrae datos del lead de esta conversación telefónica. Devuelve JSON con: name, email, address, interest_service (internet/voip/bundle/otro), interest_plan. Usa null si no se mencionó.'],
                ['role' => 'user', 'content' => $fullTranscript],
            ],
            'temperature' => 0,
            'max_tokens'  => 200,
            'response_format' => ['type' => 'json_object'],
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_HTTPHEADER      => ["Authorization: Bearer {$this->openaiKey}", "Content-Type: application/json"],
            CURLOPT_TIMEOUT         => 15,
            CURLOPT_CONNECTTIMEOUT  => 5,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $lead    = json_decode($content, true) ?? [];

        return [
            'name'             => $lead['name']             ?? null,
            'email'            => $lead['email']            ?? null,
            'address'          => $lead['address']          ?? null,
            'interest_service' => $lead['interest_service'] ?? null,
            'interest_plan'    => $lead['interest_plan']    ?? null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function wantsHuman(string $text): bool
    {
        $keywords = ['humano', 'agente', 'persona', 'representante', 'operador', 'asesor', 'hablar con', 'con alguien'];
        $lower    = mb_strtolower($text);
        foreach ($keywords as $k) {
            if (str_contains($lower, $k)) return true;
        }
        return false;
    }

    private function isClosing(string $text): bool
    {
        $keywords = ['hasta luego', 'adiós', 'adios', 'que tengas', 'fue un placer', '¡chao', 'muchas gracias', 'ha sido todo'];
        $lower    = mb_strtolower($text);
        foreach ($keywords as $k) {
            if (str_contains($lower, $k)) return true;
        }
        return false;
    }

    private function resolveOpenAiKey(): string
    {
        try {
            $key = \App\Services\Core\ApiIntegrationService::instance()->getKey('openai');
            if (!empty($key)) {
                return $key;
            }
            $this->log('Integration Hub: key OpenAI vacía o no configurada — usando .env', true);
        } catch (\Throwable $e) {
            $this->log("Integration Hub: error obteniendo key OpenAI — {$e->getMessage()}", true);
        }

        return env('OPENAI_API_KEY', '');
    }

    private function log(string $msg, bool $error = false): void
    {
        $prefix = "[MegaVoz|{$this->callId}]";
        if ($error) {
            Log::channel('asterisk')->error("{$prefix} {$msg}");
        } else {
            Log::channel('asterisk')->info("{$prefix} {$msg}");
        }
        $this->agi->verbose("{$prefix} {$msg}");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Entry point
// ─────────────────────────────────────────────────────────────────────────────
$agi = new Agi();
(new MegaVozAssistant($agi))->run();
