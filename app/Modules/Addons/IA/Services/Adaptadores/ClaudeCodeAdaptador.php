<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Adaptador que usa el CLI "Claude Code" (sesión OAuth ya autenticada en este
 * servidor, la misma que corre el Circuito CC) en vez de pegarle a la API con
 * una key medida por token. Decisión de Irving (2026-09-28): en PRODUCCIÓN se
 * usa un proveedor normal con api_key real (driver=claude/openai); este driver
 * nuevo ('claude_code') es la alternativa explícita para dev/pruebas, así no
 * se gasta crédito de la API mientras se valida algo — solo hay que activar
 * el proveedor que corresponda en /ia/configuracion, sin tocar código.
 *
 * ⚠️ TRES LIMITACIONES REALES, medidas en vivo — leer antes de usarlo en algo
 * con volumen real:
 * 1. Cada invocación es un proceso NUEVO de Claude Code, sin memoria de la
 *    anterior: carga su propio system prompt interno (~145K tokens medidos
 *    en una llamada de prueba) y lo vuelve a cachear cada vez. Eso son ~2.5s
 *    de latencia mínima por mensaje, incluso para una respuesta trivial.
 * 2. Esta sesión comparte la MISMA cuota OAuth que usa el propio Circuito CC
 *    para trabajar la Hoja de Ruta (ver CLAUDE.md, "cuota OAuth compartida").
 *    Tráfico real de un bot de cara al cliente competiría por esa cuota con
 *    el propio circuito — por eso NO es apto para producción con volumen.
 * 3. NO soporta imágenes (comprobantes de pago) — solo texto. Un intento con
 *    $imagenes no vacío lanza excepción clara en vez de fallar en silencio o
 *    ignorar la imagen.
 *
 * Seguridad: se invoca SIEMPRE con TODAS las herramientas negadas
 * (--disallowed-tools) + --permission-prompts none (deniega automático
 * cualquier cosa que pidiera permiso) — un mensaje de un cliente NUNCA debe
 * poder ejecutar nada en el servidor. El prompt se pasa como argumento de
 * arreglo (Process::run([...])), nunca interpolado en una cadena de shell —
 * evita inyección de shell desde texto de un mensaje real y no confiable.
 */
class ClaudeCodeAdaptador implements IAAdaptadorInterface
{
    private const TIMEOUT_SECONDS = 60;

    /** Herramientas negadas explícitamente (defensa en profundidad, además de --permission-prompts none). */
    private const HERRAMIENTAS_NEGADAS = [
        'Bash', 'Read', 'Write', 'Edit', 'NotebookEdit', 'WebFetch', 'WebSearch',
        'Agent', 'Skill', 'Artifact', 'ArtifactComments', 'ArtifactData', 'Workflow',
    ];

    public function __construct(protected IAProveedor $proveedor)
    {
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null): array
    {
        if (!empty($imagenes)) {
            throw new RuntimeException(
                'ClaudeCodeAdaptador no soporta imágenes — usa un proveedor con api_key '
                . '(driver=claude/openai) para extracción de comprobantes u otro uso con visión.'
            );
        }

        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt);

        $result = Process::timeout(self::TIMEOUT_SECONDS)->run($payload['argv']);

        if (!$result->successful()) {
            throw new RuntimeException(
                'Claude Code CLI error (exit ' . $result->exitCode() . '): ' . $result->errorOutput()
            );
        }

        $json = json_decode($result->output(), true);
        if (!is_array($json)) {
            throw new RuntimeException('Claude Code CLI: salida no interpretable como JSON.');
        }
        if (($json['is_error'] ?? false) === true) {
            throw new RuntimeException('Claude Code CLI reportó error: ' . ($json['result'] ?? 'sin detalle'));
        }

        return [
            'texto'         => $this->parsearRespuesta($json),
            'tokens_input'  => data_get($json, 'usage.input_tokens'),
            'tokens_output' => data_get($json, 'usage.output_tokens'),
            'raw'           => $json,
        ];
    }

    public function probarConexion(): bool
    {
        try {
            $this->enviarMensaje([], 'Responde solo con: OK', []);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{argv: array<int,string>} Aquí el "payload" es el argv del
     *         proceso (nunca un body HTTP) — se ejecuta como arreglo, nunca
     *         como cadena de shell interpolada.
     */
    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null): array
    {
        $texto = $this->aplanarHistorial($historial, $mensaje);

        $argv = ['claude', '-p', $texto, '--output-format', 'json', '--permission-prompts', 'none'];

        if ($this->proveedor->modelo_default) {
            $argv[] = '--model';
            $argv[] = $this->proveedor->modelo_default;
        }
        if ($systemPrompt) {
            $argv[] = '--system-prompt';
            $argv[] = $systemPrompt;
        }

        $argv[] = '--disallowed-tools';
        $argv[] = implode(',', self::HERRAMIENTAS_NEGADAS);

        return ['argv' => $argv];
    }

    public function parsearRespuesta(array $respuesta): string
    {
        return (string) ($respuesta['result'] ?? '');
    }

    /**
     * El CLI recibe UN solo prompt de texto, no un arreglo de turnos — aplana
     * el historial + mensaje actual en un único bloque legible.
     */
    private function aplanarHistorial(array $historial, string $mensaje): string
    {
        if (empty($historial)) {
            return $mensaje;
        }

        $lineas = [];
        foreach ($historial as $h) {
            $rol       = ($h['rol'] ?? 'user') === 'assistant' ? 'Asistente' : 'Usuario';
            $lineas[]  = "[{$rol}]: " . ($h['contenido'] ?? '');
        }
        $lineas[] = '[Usuario]: ' . $mensaje;

        return implode("\n", $lineas);
    }
}
