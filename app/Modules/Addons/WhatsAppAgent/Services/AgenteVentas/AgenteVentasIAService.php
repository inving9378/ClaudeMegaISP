<?php

namespace App\Modules\Addons\WhatsAppAgent\Services\AgenteVentas;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cerebro AISLADO del Agente de Ventas (IA).
 *
 * Deliberadamente SEPARADO de {@see \App\Modules\Addons\WhatsAppAgent\Services\WhatsAppIAService}
 * (el bot genérico de Soporte/Cobranza/Atención/Ventas): prompt propio,
 * contrato JSON propio, sin código compartido — para que ajustar uno nunca
 * "cruce cables" con el otro (decisión explícita de Irving, 2026-09-28).
 *
 * El ÚNICO punto en común con el resto del sistema es la IA asignada en
 * Integraciones → Módulos IA (clave whatsapp.ventas, vía IA::enviar): nunca guarda
 * su propia API key ni le pega a un proveedor por su cuenta (regla "servicios
 * compartidos únicos" de CLAUDE.md).
 *
 * Sin IA asignada o con error devuelve fallback() (confianza 0): el listener NO
 * lo envía al prospecto y el borrador queda para un humano.
 */
class AgenteVentasIAService
{
    private const CLAVE_IA = 'whatsapp.ventas';

    /**
     * Analiza el mensaje entrante del PROSPECTO y devuelve intención + borrador
     * + datos capturados + señal de "listo para agendar instalación".
     *
     * @param string      $incomingMessage     Texto del último mensaje entrante
     * @param array       $conversationHistory [['direction'=>'in|out','body'=>'..'], ...]
     * @param array       $collectedData       Datos YA recopilados de este prospecto en turnos previos
     * @param string|null $disponibilidadInfo  Texto ya formateado con los próximos días y si tienen
     *                                         cupo o no (lo arma el motor de disponibilidad, Tramo 3).
     *                                         null/omitido = "(sin información de disponibilidad todavía)".
     */
    public function responder(
        string  $incomingMessage,
        array   $conversationHistory,
        array   $collectedData = [],
        ?string $disponibilidadInfo = null
    ): array {
        $historial     = $this->formatHistorial($conversationHistory);
        $collectedInfo = $this->formatCollectedData($collectedData);
        $planesInfo    = $this->formatPlanesDisponibles();
        $hoy           = now()->locale('es')->translatedFormat('l j \d\e F \d\e Y, H:i');
        $disponibilidad = $disponibilidadInfo ?? '(sin información de disponibilidad todavía)';

        $mensaje = <<<MSG
FECHA Y HORA ACTUALES: {$hoy} (úsala para resolver "mañana", "el viernes", "en la tarde", etc. a una fecha/hora concretas).

DATOS YA RECOPILADOS DE ESTE PROSPECTO (NO volver a pedir estos):
{$collectedInfo}

PLANES DISPONIBLES (usar SOLO estos, NUNCA inventar precios/velocidades):
{$planesInfo}

DISPONIBILIDAD DE INSTALACIÓN (días con cupo / sin cupo — usa SOLO esta información, nunca inventes disponibilidad):
{$disponibilidad}

MENSAJE ACTUAL DEL PROSPECTO:
"{$incomingMessage}"

Responde ÚNICAMENTE con el JSON exacto indicado en las instrucciones de sistema, sin texto adicional ni bloques markdown.
MSG;

        try {
            $resultado = IA::enviar(self::CLAVE_IA, $mensaje, [], $this->systemPrompt(), $historial, ['json' => true]);

            $data = IA::json((string) ($resultado['texto'] ?? ''));

            return is_array($data) ? $this->normalizar($data) : $this->fallback();
        } catch (IANoConfigurada $e) {
            Log::info('AgenteVentas: sin IA asignada, queda para un humano', ['motivo' => $e->getMessage()]);
            return $this->fallback();
        } catch (\Throwable $e) {
            Log::warning('AgenteVentas: fallo llamando a la IA', ['error' => $e->getMessage()]);
            return $this->fallback();
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Eres el Agente de Ventas de MegaNet, un ISP mexicano. Hablas por WhatsApp con
PROSPECTOS (personas que AÚN NO son clientes) que quieren contratar internet.
No eres el bot de soporte/cobranza — nunca atiendes fallas de servicio ni
cobros; si el prospecto describe eso, es señal de que ya es cliente (ver
intención "ya_es_cliente" abajo).

TONO — SIN EXCEPCIÓN: sé siempre amable, cordial y profesional, incluso si el
prospecto es cortante, insiste mucho o se muestra grosero. Nunca discutas ni
respondas con sarcasmo.

TU OBJETIVO, en este orden estricto:
1. Si el prospecto solo pregunta información (planes, precios, cobertura,
   "qué servicios tienen"), RESPONDE la información usando SOLO la lista de
   PLANES DISPONIBLES y, en el mismo mensaje, pregúntale si le interesa
   contratar alguno. NO pidas ningún dato personal todavía — intent="cotizacion".
2. SOLO cuando el prospecto confirme explícitamente que quiere contratar (dijo
   que sí, que le interesa un plan, "quiero contratar", etc.), empieza a pedir
   UN dato a la vez (no abrumar) hasta completar: nombre completo, calle y
   número, colonia, código postal, municipio, estado, email (opcional pero
   pregúntalo), y cómo se enteró de MegaNet (referencia_origen).
3. Cuando esos datos mínimos (nombre, calle+número, colonia o CP, municipio,
   estado) YA estén completos (revisa "DATOS YA RECOPILADOS" + lo nuevo de
   este mensaje), pregunta qué día y hora prefiere para la instalación.
   IMPORTANTE: si el prospecto confirma interés Y da todos esos datos en el
   MISMO mensaje (de un jalón), NO le vuelvas a preguntar si quiere proceder
   — pasa directo a preguntar la fecha de instalación en ese mismo draft.
4. Cuando el prospecto dé un día/hora, compáralo contra "DISPONIBILIDAD DE
   INSTALACIÓN": si ese día SÍ tiene cupo, confirma y marca
   "registro.listo_para_agendar"=true con la fecha/hora resuelta en
   "registro.fecha_iso" (formato "YYYY-MM-DD HH:MM:SS", asume horario de
   oficina 09:00-18:00 si solo dice "en la mañana/tarde", nunca antes de HOY).
   Si ese día NO tiene cupo (o no hay información de disponibilidad todavía),
   NO marques listo_para_agendar — en su lugar, el draft debe decirlo con
   amabilidad y proponer 2-3 alternativas de la lista de días CON cupo. Nunca
   inventes disponibilidad que no esté en esa lista.

RESPONDE ÚNICAMENTE CON JSON VÁLIDO, sin texto adicional ni bloques markdown,
con esta forma exacta:
{
  "intent": "cotizacion|interes_contratar|dando_datos|listo_agendar|ya_es_cliente|objecion|consulta_general|no_interesado",
  "confidence": 0.94,
  "draft": "Respuesta completa lista para enviar al prospecto",
  "quick_replies": ["Opción rápida 1 (máx 5 palabras)", "Opción rápida 2", "Opción rápida 3"],
  "datos_prospecto": {
    "nombre": null, "apellido_paterno": null, "apellido_materno": null,
    "email": null, "phone2": null, "calle": null, "numero_ext": null,
    "numero_int": null, "colonia_texto": null, "municipio_texto": null,
    "estado_texto": null, "cp": null, "referencia_origen": null
  },
  "registro": { "listo_para_agendar": false, "fecha_iso": null }
}

INTENCIONES:
- cotizacion: pregunta precio/velocidad/cobertura sin haber confirmado contratar.
- interes_contratar: acaba de confirmar que quiere contratar, aún sin datos.
- dando_datos: está en medio de dar sus datos para el registro.
- listo_agendar: ya confirmó día/hora de instalación Y ese día tiene cupo.
- ya_es_cliente: menciona que YA tiene servicio con nosotros, una falla, o un
  cobro — NO es un prospecto nuevo. El draft debe decir que lo vas a canalizar
  con el equipo correspondiente y NO intentar venderle de nuevo.
- objecion: duda/objeción para contratar (precio, ya tiene otro proveedor, etc.)
  — responde con empatía, sin presionar ni inventar descuentos que no existen.
- consulta_general: saludo o pregunta general sin relación a venta.
- no_interesado: declina explícitamente.

REGLAS ESTRICTAS DE "datos_prospecto":
- NUNCA los llenes mientras el intent sea "cotizacion" (antes de que confirme
  interés en contratar) — deja todos los campos en null en ese caso, aunque el
  prospecto haya mencionado su nombre de pasada.
- NO inventes datos. Si el prospecto no lo mencionó (en este mensaje o antes),
  deja null. Solo reporta aquí los campos NUEVOS de ESTE mensaje — el backend
  ya conserva los previos.
- "calle" SIN número (el número va en numero_ext/numero_int).
- "cp" debe ser 5 dígitos exactos; si no, null.
- "colonia_texto"/"municipio_texto"/"estado_texto" = texto libre tal cual lo
  dijo el prospecto, sin normalizar — el backend resuelve los IDs.
- Todo dato ya listado en "DATOS YA RECOPILADOS" del mensaje de usuario NUNCA
  se vuelve a pedir en el draft, aunque el prospecto lo repita.

CRÍTICO — USO DE "PLANES DISPONIBLES":
- Cita el plan por su nombre exacto y el precio tal como aparece en la lista.
- Si pide un plan que no está en la lista, acláralo y ofrece el más cercano.
- Si la lista dice "(no hay planes cargados…)", responde que un asesor
  informará los planes disponibles — NO inventes.
PROMPT;
    }

    /**
     * Asegura que las claves esperadas existan siempre, con confianza en el
     * consumidor sin tener que checar existencia de cada una.
     */
    private function normalizar(array $data): array
    {
        $data['intent']          = $data['intent'] ?? 'consulta_general';
        $data['confidence']      = (float) ($data['confidence'] ?? 0.5);
        $data['draft']           = (string) ($data['draft'] ?? '');
        $data['quick_replies']   = is_array($data['quick_replies'] ?? null) ? $data['quick_replies'] : [];
        $data['datos_prospecto'] = is_array($data['datos_prospecto'] ?? null) ? $data['datos_prospecto'] : $this->emptyDatosProspecto();
        $data['registro']        = [
            'listo_para_agendar' => (bool) ($data['registro']['listo_para_agendar'] ?? false),
            'fecha_iso'          => $data['registro']['fecha_iso'] ?? null,
        ];

        return $data;
    }

    public function emptyDatosProspecto(): array
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

    private function fallback(): array
    {
        return [
            'intent'          => 'consulta_general',
            'confidence'      => 0.0,
            'draft'           => 'Hola, gracias por escribir a MegaNet. Un asesor te contactará en breve.',
            'quick_replies'   => [],
            'datos_prospecto' => $this->emptyDatosProspecto(),
            'registro'        => ['listo_para_agendar' => false, 'fecha_iso' => null],
            'fallback'        => true,
        ];
    }

    /** Últimos 8 turnos, en el formato {rol, contenido} que espera IAAdaptadorInterface. */
    private function formatHistorial(array $messages): array
    {
        return collect($messages)
            ->take(-8)
            ->map(fn ($m) => [
                'rol'       => ($m['direction'] ?? 'in') === 'out' ? 'assistant' : 'user',
                'contenido' => (string) ($m['body'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function formatCollectedData(array $data): string
    {
        $clean = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if (empty($clean)) {
            return '(ningún dato recopilado aún)';
        }
        return json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Planes vendibles desde `internets`. Copia deliberada (no import) del
     * mismo criterio ya validado en WhatsAppIAService::formatPlanesDisponibles
     * — misma fuente de verdad (tabla internets), pero sin acoplar los dos
     * cerebros entre sí. Cache propia (clave distinta) para no pisar la del
     * bot viejo ni depender de su ciclo de vida.
     */
    private function formatPlanesDisponibles(): string
    {
        try {
            $planes = Cache::remember(
                'whatsapp.agente_ventas.planes_disponibles',
                now()->addMinutes(5),
                function () {
                    return DB::table('internets')
                        ->where('price', '>', 0)
                        ->orderBy('price')
                        ->get(['title', 'service_name', 'price', 'download_speed', 'upload_speed', 'tax_include'])
                        ->map(function ($p) {
                            $entry = [
                                'nombre'       => $p->title,
                                'precio_mxn'   => (float) $p->price,
                                'iva_incluido' => (bool) $p->tax_include,
                            ];
                            if ((int) $p->download_speed > 0) {
                                $entry['mbps_descarga'] = (int) $p->download_speed > 1000
                                    ? round($p->download_speed / 1024, 1)
                                    : (int) $p->download_speed;
                            }
                            if ((int) $p->upload_speed > 0) {
                                $entry['mbps_subida'] = (int) $p->upload_speed > 1000
                                    ? round($p->upload_speed / 1024, 1)
                                    : (int) $p->upload_speed;
                            }
                            return $entry;
                        })
                        ->all();
                }
            );
        } catch (\Throwable $e) {
            Log::warning('AgenteVentas: no se pudieron cargar planes', ['error' => $e->getMessage()]);
            return '(no hay planes cargados — responder "un asesor te informará sobre nuestros planes")';
        }

        if (empty($planes)) {
            return '(no hay planes cargados — responder "un asesor te informará sobre nuestros planes")';
        }

        return json_encode($planes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
