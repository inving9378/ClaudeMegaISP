<?php

namespace App\Services\Asistente;

use App\Models\Asistente\Accion;
use App\Models\Asistente\Conversacion;
use App\Models\Asistente\Mensaje;
use App\Models\User;
use App\Modules\Addons\IA\Services\IA;
use App\Services\Asistente\Herramientas\Herramienta;
use Illuminate\Support\Facades\Log;

/**
 * JARVIS — el asistente flotante (robotsito). Orquesta cada mensaje:
 *   1. carga el hilo del usuario (memoria persistente) y su contexto,
 *   2. conversa con la IA asignada en Integraciones → Módulos IA (cualquier proveedor), dándole solo
 *      las herramientas que el usuario puede usar,
 *   3. ejecuta las consultas, deja los cambios esperando confirmación,
 *   4. guarda todo y compacta la memoria cuando hace falta.
 */
class AsistenteService
{
    public const CLAVE_IA = 'asistente.chat_flotante';

    private const AFIRMACION = '/^\s*(s[ií]|s[ií],?\s*(hazlo|adelante|confirmo|dale|por favor|claro)|hazlo|dale|adelante|confirmo|confirmado|ok(ay)?|va|vale|de acuerdo|correcto|procede|perfecto|claro)\s*[.!]*\s*$/iu';

    private const NEGACION = '/^\s*(no|nel|cancela(r|lo)?|mejor no|no lo hagas|olv[ií]dalo|det[eé]nte|alto)\s*[.!]*\s*$/iu';

    public function __construct(
        private ConocimientoAsistente $conocimiento,
        private RegistroHerramientas $registro,
        private AccionesAsistente $acciones,
        private MemoriaAsistente $memoria,
    ) {
    }

    public function conversacionActiva(User $user): Conversacion
    {
        return Conversacion::where('user_id', $user->id)->where('activa', true)->latest('id')->first()
            ?? Conversacion::create(['user_id' => $user->id, 'activa' => true]);
    }

    /** "Empezar tema nuevo": cierra el hilo actual. Los recuerdos del usuario se conservan. */
    public function nuevaConversacion(User $user): Conversacion
    {
        $actual = $this->conversacionActiva($user);
        if (!$actual->mensajes()->exists()) {
            return $actual;
        }
        Accion::where('user_id', $user->id)->where('estado', Accion::PENDIENTE)
            ->update(['estado' => Accion::CANCELADA, 'resuelta_at' => now()]);
        $actual->update(['activa' => false]);

        return Conversacion::create(['user_id' => $user->id, 'activa' => true]);
    }

    /** Lo que se pinta al abrir el chat: la plática completa (también lo ya compactado). */
    public function paraPantalla(User $user): array
    {
        $conversacion = $this->conversacionActiva($user);
        $mensajes = $conversacion->mensajes()->whereIn('rol', ['user', 'assistant'])
            ->whereNotNull('contenido')->where('contenido', '!=', '')
            ->orderByDesc('id')->limit($this->conocimiento->memoria('mensajes_en_pantalla', 80))
            ->get(['rol', 'contenido', 'created_at'])->reverse()->values()
            ->map(fn ($m) => ['role' => $m->rol, 'content' => $m->contenido, 'timestamp' => $m->created_at?->toIso8601String()]);

        return ['messages' => $mensajes->all(), 'accion_pendiente' => $this->accionParaPantalla($this->acciones->pendienteDe($user, $conversacion))];
    }

    public function responder(User $user, string $texto, ?string $pantalla): array
    {
        $conversacion = $this->conversacionActiva($user);
        $texto = trim($texto);

        // El "sí" / "no" a un cambio pendiente lo resuelve el servidor, no la IA.
        $pendiente = $this->acciones->pendienteDe($user, $conversacion);
        if ($pendiente && mb_strlen($texto) <= 40 && (preg_match(self::AFIRMACION, $texto) || preg_match(self::NEGACION, $texto))) {
            $this->guardar($conversacion, 'user', $texto, ['pantalla' => $pantalla]);

            return preg_match(self::AFIRMACION, $texto)
                ? $this->resultadoDeConfirmacion($user, $conversacion, $pendiente)
                : $this->resultadoDeCancelacion($user, $conversacion, $pendiente);
        }

        $this->guardar($conversacion, 'user', $texto, ['pantalla' => $pantalla]);
        if (!$conversacion->titulo) {
            $conversacion->update(['titulo' => mb_substr($texto, 0, 120)]);
        }

        $disponibles = $this->registro->disponiblesPara($user);
        $esquemas = array_values(array_map(fn (Herramienta $h) => $h->esquema(), $disponibles));
        $system = $this->systemPrompt($user, $pantalla, $conversacion);
        $turnos = $this->memoria->historial($conversacion);
        $opciones = ['max_tokens' => $this->conocimiento->limite('max_tokens_respuesta', 1200)];

        $accionPendiente = null;
        $final = null;
        $tokens = null;

        for ($vuelta = 0; $vuelta < $this->conocimiento->limite('max_vueltas_herramientas', 6); $vuelta++) {
            $r = IA::conversar(self::CLAVE_IA, $turnos, $system, $esquemas, $opciones);
            $tokens = isset($r['tokens_input']) ? (int) $r['tokens_input'] : $tokens;

            if (($r['fin'] ?? '') !== 'herramientas' || empty($r['llamadas'])) {
                $final = trim((string) ($r['texto'] ?? ''));
                break;
            }

            $this->guardar($conversacion, 'assistant', (string) ($r['texto'] ?? ''), [
                'llamadas' => $r['llamadas'], 'tokens_input' => $r['tokens_input'] ?? null, 'tokens_output' => $r['tokens_output'] ?? null,
            ]);
            $turnos[] = ['rol' => 'assistant', 'contenido' => (string) ($r['texto'] ?? ''), 'llamadas' => $r['llamadas']];

            foreach ($r['llamadas'] as $llamada) {
                $resultado = $this->usarHerramienta($user, $conversacion, $disponibles, (string) $llamada['nombre'], (array) ($llamada['argumentos'] ?? []));
                if (($resultado['estado'] ?? null) === 'pendiente_confirmacion') {
                    $accionPendiente = ['id' => $resultado['accion_id'], 'resumen' => $resultado['resumen']];
                }
                $json = mb_substr(json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    0, $this->conocimiento->memoria('max_caracteres_resultado_herramienta', 4000));

                $this->guardar($conversacion, 'herramienta', $json, ['herramienta_llamada_id' => $llamada['id'], 'herramienta' => $llamada['nombre']]);
                $turnos[] = ['rol' => 'herramienta', 'id' => (string) $llamada['id'], 'nombre' => (string) $llamada['nombre'], 'resultado' => $json];
            }
        }

        if ($final === null) {
            $final = 'Me tomó demasiados pasos y no alcancé a terminar. ¿Me lo pides de forma más concreta?';
        } elseif ($final === '') {
            $final = 'No tengo una respuesta para eso en este momento.';
        }
        $final = self::enlacesInternos($final);

        $this->guardar($conversacion, 'assistant', $final, ['tokens_input' => $tokens]);
        $conversacion->update(['tokens_ultima_vuelta' => $tokens]);

        try {
            $this->memoria->compactarSiHaceFalta($conversacion, $tokens);
        } catch (\Throwable $e) {
            Log::warning('[JARVIS] falló el autocompactado', ['conversacion' => $conversacion->id, 'error' => $e->getMessage()]);
        }

        return ['mensaje' => $final, 'accion_pendiente' => $accionPendiente];
    }

    /** Confirmación con el botón. Se registra como mensaje del usuario para que la plática siga coherente. */
    public function confirmarAccion(User $user, Accion $accion): array
    {
        $conversacion = $this->conversacionActiva($user);
        $this->guardar($conversacion, 'user', '(Confirmé el cambio con el botón)');

        return $this->resultadoDeConfirmacion($user, $conversacion, $accion);
    }

    public function cancelarAccion(User $user, Accion $accion): array
    {
        $conversacion = $this->conversacionActiva($user);
        $this->guardar($conversacion, 'user', '(Cancelé el cambio con el botón)');

        return $this->resultadoDeCancelacion($user, $conversacion, $accion);
    }

    private function resultadoDeConfirmacion(User $user, Conversacion $conversacion, Accion $accion): array
    {
        $r = $this->acciones->confirmar($user, $accion);
        $mensaje = isset($r['error'])
            ? $r['error']
            : trim(($r['mensaje'] ?? 'Listo, quedó hecho.') . (!empty($r['enlace']) ? " [Ver aquí]({$r['enlace']})" : ''));
        $this->guardar($conversacion, 'assistant', $mensaje);

        return ['mensaje' => $mensaje, 'accion_pendiente' => null, 'ok' => !isset($r['error'])];
    }

    private function resultadoDeCancelacion(User $user, Conversacion $conversacion, Accion $accion): array
    {
        $this->acciones->cancelar($user, $accion);
        $mensaje = 'Va, no hice ningún cambio.';
        $this->guardar($conversacion, 'assistant', $mensaje);

        return ['mensaje' => $mensaje, 'accion_pendiente' => null];
    }

    private function usarHerramienta(User $user, Conversacion $conversacion, array $disponibles, string $nombre, array $args): array
    {
        $herramienta = $disponibles[$nombre] ?? null;
        if (!$herramienta || !PermisosAsistente::puede($user, $herramienta->permiso())) {
            return ['error' => 'Con este usuario no tengo acceso a esa función.'];
        }

        try {
            if ($herramienta->esCambio()) {
                return $this->conocimiento->modoDe($nombre, true) === 'permitida'
                    ? $this->acciones->ejecutarDirecto($user, $herramienta, $args, $conversacion)
                    : $this->acciones->preparar($user, $herramienta, $args, $conversacion);
            }

            return $herramienta->ejecutar($user, $args);
        } catch (\Throwable $e) {
            Log::warning('[JARVIS] falló una herramienta', ['herramienta' => $nombre, 'user' => $user->id, 'error' => $e->getMessage()]);

            return ['error' => 'No pude consultar eso en este momento.'];
        }
    }

    /** Parte fija primero (instrucciones + mapa, igual en cada mensaje) y luego lo de esta plática. */
    private function systemPrompt(User $user, ?string $pantalla, Conversacion $conversacion): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', ($user->name ?? '') . ' ' . ($user->father_last_name ?? '')));
        $roles = implode(', ', $user->getRoleNames()->all()) ?: 'sin rol';
        $ahora = now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm');

        $partes = [
            $this->conocimiento->arranqueFijo(),
            "# Esta conversación\n- Hablas con: {$nombre} (puesto o rol en el sistema: {$roles}).\n- Fecha y hora: {$ahora}.\n"
                . '- Pantalla donde está ahora: ' . ($pantalla ?: 'desconocida') . '.',
            $this->memoria->bloqueRecuerdos($user),
        ];
        if (trim((string) $conversacion->resumen) !== '') {
            $partes[] = "# Resumen de lo que ya platicaron antes\n" . trim($conversacion->resumen);
        }

        return implode("\n\n", array_filter($partes));
    }

    /**
     * Los enlaces de JARVIS son siempre rutas internas del sistema. Si la IA les pega un dominio
     * (inventado o no), se le quita: [texto](https://lo-que-sea/cliente/editar/1) → [texto](/cliente/editar/1).
     */
    public static function enlacesInternos(string $texto): string
    {
        return preg_replace('#\]\(\s*(?:https?:)?//[^/\s)]+(/[^)\s]*)?\s*\)#i', ']($1)', $texto) ?? $texto;
    }

    private function accionParaPantalla(?Accion $accion): ?array
    {
        return $accion ? ['id' => $accion->id, 'resumen' => $accion->resumen] : null;
    }

    private function guardar(Conversacion $conversacion, string $rol, string $contenido, array $extra = []): Mensaje
    {
        $mensaje = $conversacion->mensajes()->create(array_merge(['rol' => $rol, 'contenido' => $contenido], $extra));
        $conversacion->update(['ultimo_mensaje_at' => now()]);

        return $mensaje;
    }
}
