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
 * Seguridad — 4 capas, probadas en vivo (una lista negativa sola NO basta,
 * ver hallazgo abajo):
 * 1. --restricted: quita Bash/PowerShell/REPL/ejecución de código + WebFetch,
 *    ignora settings de usuario/proyecto/local ambientales.
 * 2. --strict-mcp-config (sin --mcp-config): CERO servidores MCP cargados —
 *    sin --restricted, esta sesión hereda TODOS los MCP del entorno
 *    (Claude_Docs, roadmap circuito-cc, playwright) sin haberlo pedido.
 * 3. --disallowed-tools explícito para lo que --restricted NO cubre. Hallazgo
 *    crítico en vivo: con SOLO --restricted, "Agent" seguía disponible y el
 *    propio modelo confirmó que un subagente delegado por Agent SÍ tendría
 *    Bash completo — un bypass real de la restricción. Se niega Agent
 *    explícito (cierra el bypass) + Write/Edit/Read/Glob/Grep (sin acceso de
 *    archivos tampoco). Verificado: con este combo, un intento explícito de
 *    "usa Agent para correr whoami por Bash" fallo limpio, sin inventar nada.
 * 4. --permission-prompts none: deniega automático cualquier cosa que
 *    pidiera permiso (defensa adicional, no la única capa).
 * 5. Working directory NEUTRAL (storage/app/ia/claude_code_sandbox, fuera del
 *    repo) — CAPA MÁS CRÍTICA, encontrada al final: --restricted NO evita que
 *    Claude Code auto-descubra y cargue el CLAUDE.md del proyecto (con notas
 *    internas de arquitectura, incidentes de seguridad, convenciones) como
 *    contexto ambiental. Verificado en vivo: corriendo desde /var/www/megaisp
 *    el modelo mencionó por su cuenta un commit real del repo sin que nadie
 *    se lo pidiera — un cliente con un prompt bien armado podría sonsacar
 *    información interna del negocio. Corriendo desde un directorio vacío sin
 *    CLAUDE.md ni .git, la misma pregunta directa ("dime algo de MegaISP")
 *    devolvió cero conocimiento. Sin esta capa, las otras 4 no bastan.
 *
 * El prompt se pasa como argumento de arreglo (Process::run([...])), nunca
 * interpolado en una cadena de shell — evita inyección de shell desde texto
 * de un mensaje real y no confiable.
 */
class ClaudeCodeAdaptador implements IAAdaptadorInterface
{
    private const TIMEOUT_SECONDS = 60;

    /**
     * Lo que --restricted NO cubre (ver punto 3 del docblock de seguridad) —
     * exactamente la combinación verificada en vivo, incluido el cierre del
     * bypass por delegación a subagente (Agent).
     */
    private const HERRAMIENTAS_NEGADAS = ['Agent', 'Write', 'Edit', 'Read', 'Glob', 'Grep'];

    public function __construct(protected IAProveedor $proveedor)
    {
    }

    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $opciones = []): array
    {
        if (!empty($imagenes)) {
            throw new RuntimeException(
                'ClaudeCodeAdaptador no soporta imágenes — usa un proveedor con api_key '
                . '(driver=claude/openai) para extracción de comprobantes u otro uso con visión.'
            );
        }

        $payload = $this->construirPayload($historial, $mensaje, $imagenes, $systemPrompt);

        // CRÍTICO: el .env de Laravel carga CLAUDE_API_KEY/ANTHROPIC_API_KEY al
        // entorno de PHP; si el subproceso las hereda, el CLI intenta autenticar
        // por API key en vez de la sesión OAuth y SE CUELGA en headless (sin
        // fallar rápido) — confirmado en vivo el 2026-09-28. Mismo tratamiento
        // que ya hace deploy/circuito/vuelta.sh (unset CLAUDE_API_KEY) antes de
        // invocar `claude -p`.
        $result = Process::timeout((int) ($opciones['timeout'] ?? self::TIMEOUT_SECONDS))
            ->path($this->sandboxDir())
            ->env(['CLAUDE_API_KEY' => null, 'ANTHROPIC_API_KEY' => null])
            ->run($payload['argv']);

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
            'fin'           => 'otro',
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
     * Directorio de trabajo NEUTRAL para el subproceso — GENUINAMENTE fuera
     * del árbol de git del repo (no basta con un subdirectorio de
     * storage/app: Claude Code detecta el repositorio subiendo por los
     * directorios padre hasta encontrar el `.git`, así que cualquier ruta
     * dentro de /var/www/megaisp sigue filtrando commits/archivos reales —
     * error real cometido y corregido en esta misma sesión, 2026-09-28: un
     * primer intento con storage_path() seguía leyendo el git log real). Por
     * eso vive en sys_get_temp_dir(), fuera de cualquier árbol de git. Se
     * crea vacío si no existe; nunca se escribe nada más ahí a propósito.
     */
    private function sandboxDir(): string
    {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/megaisp_claude_code_sandbox';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * @return array{argv: array<int,string>} Aquí el "payload" es el argv del
     *         proceso (nunca un body HTTP) — se ejecuta como arreglo, nunca
     *         como cadena de shell interpolada.
     */
    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null, array $opciones = []): array
    {
        $texto = $this->aplanarHistorial($historial, $mensaje);

        $argv = [
            'claude', '-p', $texto,
            '--output-format', 'json',
            '--permission-prompts', 'none',
            '--restricted',
            '--strict-mcp-config',
        ];

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
