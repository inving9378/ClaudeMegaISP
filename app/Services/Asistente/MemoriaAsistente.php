<?php

namespace App\Services\Asistente;

use App\Models\Asistente\Conversacion;
use App\Models\Asistente\Mensaje;
use App\Models\Asistente\Recuerdo;
use App\Models\User;
use App\Modules\Addons\IA\Services\IA;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Memoria persistente de JARVIS (en base de datos).
 *
 * - Corto plazo: los mensajes del hilo que aún no se compactan se mandan completos a la IA.
 * - Autocompactado: cuando el último mensaje ocupó más del umbral de memoria.json (medido con el
 *   conteo real que devuelve la IA) o hay demasiados mensajes, lo viejo se resume en
 *   `conversacion.resumen` y se marca `compactado`. Nada se borra; solo deja de mandarse a la IA.
 * - Largo plazo: al compactar se extraen recuerdos del usuario (preferencias y contexto, nunca
 *   datos que cambian). Son por usuario y nadie más los ve.
 */
class MemoriaAsistente
{
    /** Clave propia para resumir (Integraciones → Módulos IA); si no tiene IA, usa la del chat. */
    public const CLAVE_IA = 'asistente.memoria';

    public function __construct(private ConocimientoAsistente $conocimiento)
    {
    }

    /** Turnos en formato neutro de IA::conversar, coherentes (cada llamada con su resultado). */
    public function historial(Conversacion $conversacion): array
    {
        $max = $this->conocimiento->memoria('max_mensajes_sin_compactar', 60);
        $maxResultado = $this->conocimiento->memoria('max_caracteres_resultado_herramienta', 4000);
        $maxTexto = $this->conocimiento->memoria('max_caracteres_mensaje', 6000);

        $mensajes = $conversacion->mensajes()->where('compactado', false)
            ->orderByDesc('id')->limit($max)->get()->reverse()->values();

        // La ventana debe arrancar en un mensaje del usuario (nunca a media consulta).
        while ($mensajes->isNotEmpty() && $mensajes->first()->rol !== 'user') {
            $mensajes->shift();
        }

        $idsConResultado = $mensajes->where('rol', 'herramienta')->pluck('herramienta_llamada_id')->filter()->flip();
        $llamadasValidas = [];

        $turnos = [];
        $anterior = null;
        foreach ($mensajes as $m) {
            if ($m->rol === 'herramienta') {
                if (!isset($llamadasValidas[$m->herramienta_llamada_id])) {
                    continue; // resultado sin su llamada: se omite
                }
                $turnos[] = [
                    'rol'       => 'herramienta',
                    'id'        => (string) $m->herramienta_llamada_id,
                    'nombre'    => (string) $m->herramienta,
                    'resultado' => mb_substr((string) $m->contenido, 0, $maxResultado),
                ];
            } elseif ($m->rol === 'assistant') {
                $turno = ['rol' => 'assistant', 'contenido' => mb_substr((string) $m->contenido, 0, $maxTexto)];
                $llamadas = (array) ($m->llamadas ?? []);
                $completas = $llamadas && collect($llamadas)->every(fn ($l) => isset($idsConResultado[$l['id'] ?? '']));
                if ($completas) {
                    $turno['llamadas'] = $llamadas;
                    foreach ($llamadas as $l) {
                        $llamadasValidas[$l['id']] = true;
                    }
                } elseif ($turno['contenido'] === '') {
                    continue; // llamada que quedó a medias y sin texto: no aporta
                }
                $turnos[] = $turno;
            } else {
                // Dos mensajes del usuario seguidos (o uno tras resultados) → un turno puente.
                if (in_array($anterior, ['user', 'herramienta'], true)) {
                    $turnos[] = ['rol' => 'assistant', 'contenido' => '(sin respuesta)'];
                }
                $turnos[] = ['rol' => 'user', 'contenido' => mb_substr((string) $m->contenido, 0, $maxTexto)];
            }
            $anterior = end($turnos)['rol'] ?? null;
        }

        return $turnos;
    }

    public function bloqueRecuerdos(User $user): string
    {
        $recuerdos = Recuerdo::where('user_id', $user->id)->activos()->latest('id')
            ->limit($this->conocimiento->memoria('max_recuerdos', 40))->pluck('contenido');

        return $recuerdos->isEmpty() ? '' : "# Lo que recuerdas de este usuario\n- " . $recuerdos->reverse()->implode("\n- ");
    }

    public function compactarSiHaceFalta(Conversacion $conversacion, ?int $tokensInput): bool
    {
        $presupuesto = $this->conocimiento->memoria('presupuesto_tokens', 16000);
        $porcentaje = $this->conocimiento->memoria('compactar_al_porcentaje', 70);
        $vivos = $conversacion->mensajes()->where('compactado', false)->orderBy('id')->get(['id', 'rol']);

        $excedeTokens = ($tokensInput ?? 0) >= intdiv($presupuesto * $porcentaje, 100);
        $excedeMensajes = $vivos->count() > $this->conocimiento->memoria('max_mensajes_sin_compactar', 60);

        return ($excedeTokens || $excedeMensajes) ? $this->compactar($conversacion, $vivos) : false;
    }

    public function compactar(Conversacion $conversacion, ?Collection $vivos = null): bool
    {
        $vivos ??= $conversacion->mensajes()->where('compactado', false)->orderBy('id')->get(['id', 'rol']);
        $recientes = $this->conocimiento->memoria('mensajes_recientes', 12);
        if ($vivos->count() <= $recientes) {
            return false;
        }

        // Se conservan completos los últimos N mensajes, empezando en uno del usuario.
        $corte = $vivos->count() - $recientes;
        while ($corte > 0 && $vivos[$corte]->rol !== 'user') {
            $corte--;
        }
        if ($corte <= 0) {
            return false;
        }

        $viejos = $conversacion->mensajes()->where('compactado', false)
            ->where('id', '<', $vivos[$corte]->id)->orderBy('id')->get();

        $r = $this->resumir((string) $conversacion->resumen, $this->transcripcion($viejos));
        if ($r === null) {
            return false;
        }

        DB::transaction(function () use ($conversacion, $viejos, $r) {
            $conversacion->update(['resumen' => $r['resumen'], 'compactaciones' => $conversacion->compactaciones + 1]);
            Mensaje::whereIn('id', $viejos->pluck('id'))->update(['compactado' => true]);
            $this->guardarRecuerdos((int) $conversacion->user_id, $r['recuerdos'], $conversacion->id);
        });

        return true;
    }

    private function transcripcion(Collection $mensajes): string
    {
        $lineas = [];
        foreach ($mensajes as $m) {
            if ($m->rol === 'user') {
                $lineas[] = 'Usuario: ' . mb_substr((string) $m->contenido, 0, 1500);
            } elseif ($m->rol === 'assistant') {
                if (trim((string) $m->contenido) !== '') {
                    $lineas[] = 'JARVIS: ' . mb_substr((string) $m->contenido, 0, 1500);
                }
                foreach ((array) ($m->llamadas ?? []) as $l) {
                    $lineas[] = '(JARVIS consultó ' . ($l['nombre'] ?? '?') . ' ' . json_encode($l['argumentos'] ?? [], JSON_UNESCAPED_UNICODE) . ')';
                }
            } else {
                $lineas[] = '(Resultado de ' . $m->herramienta . ': ' . mb_substr((string) $m->contenido, 0, 400) . ')';
            }
        }

        return mb_substr(implode("\n", $lineas), -40000);
    }

    private function resumir(string $previo, string $transcripcion): ?array
    {
        $system = 'Eres la memoria de JARVIS, el asistente del sistema MegaISP. Respondes SOLO con un objeto JSON '
            . '{"resumen": string, "recuerdos": [string]} y nada más.';

        $prompt = "Actualiza el resumen de la conversación entre un usuario y JARVIS.\n\n"
            . "Resumen anterior (puede estar vacío):\n" . ($previo !== '' ? $previo : '(vacío)') . "\n\n"
            . "Mensajes nuevos a incorporar:\n{$transcripcion}\n\n"
            . "Reglas:\n"
            . "- \"resumen\": en español, máximo 25 renglones. Qué pidió el usuario, qué se respondió, qué cambios se hicieron, "
            . "qué quedó pendiente y los clientes de los que se habló (nombre y número). Aclara que los datos de clientes "
            . "(saldos, estados) pueden haber cambiado desde entonces.\n"
            . "- \"recuerdos\": de 0 a 5 frases cortas con datos DURADEROS del usuario: su puesto, en qué trabaja, cómo le gusta "
            . "que le contesten, tareas que repite. NUNCA saldos, montos, estados de clientes ni contraseñas. Si no hay nada nuevo, [].";

        $clave = IA::configurada(self::CLAVE_IA) ? self::CLAVE_IA : AsistenteService::CLAVE_IA;

        try {
            $r = IA::enviar($clave, $prompt, [], $system, [], ['json' => true, 'max_tokens' => 1500]);
        } catch (\Throwable $e) {
            Log::warning('[JARVIS] no se pudo compactar la conversación', ['error' => $e->getMessage()]);

            return null;
        }

        $json = IA::json((string) ($r['texto'] ?? ''));
        if (!is_array($json) || trim((string) ($json['resumen'] ?? '')) === '') {
            return null;
        }

        return [
            'resumen'   => mb_substr(trim((string) $json['resumen']), 0, 8000),
            'recuerdos' => array_slice(array_values(array_filter((array) ($json['recuerdos'] ?? []), fn ($x) => is_string($x) && trim($x) !== '')), 0, 5),
        ];
    }

    private function guardarRecuerdos(int $userId, array $recuerdos, int $conversacionId): void
    {
        $actuales = Recuerdo::where('user_id', $userId)->activos()->pluck('contenido')
            ->map(fn ($c) => mb_strtolower(trim($c)))->flip();

        foreach ($recuerdos as $texto) {
            $texto = trim(mb_substr($texto, 0, 500));
            if (!isset($actuales[mb_strtolower($texto)])) {
                Recuerdo::create(['user_id' => $userId, 'contenido' => $texto, 'origen' => 'auto', 'conversacion_id' => $conversacionId]);
            }
        }

        // Tope: se apagan los automáticos más viejos (los que el usuario pidió se respetan).
        $max = $this->conocimiento->memoria('max_recuerdos', 40);
        $sobran = Recuerdo::where('user_id', $userId)->activos()->count() - $max;
        if ($sobran > 0) {
            Recuerdo::where('user_id', $userId)->activos()->where('origen', 'auto')
                ->orderBy('id')->limit($sobran)->update(['activo' => false]);
        }
    }
}
