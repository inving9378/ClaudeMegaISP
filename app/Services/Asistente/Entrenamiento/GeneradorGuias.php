<?php

namespace App\Services\Asistente\Entrenamiento;

use App\Modules\Addons\IA\Services\IA;
use App\Services\Asistente\AsistenteService;
use App\Services\Asistente\RegistroHerramientas;

/**
 * Escribe, con la IA asignada en Integraciones, una guía por módulo en lenguaje de usuario
 * (resources/asistente/guia/modulos/<slug>.md) y el índice que JARVIS lee en cada mensaje
 * (guia/_temas.md). Lo que la IA escribe se revisa antes de guardarlo: solo se dejan enlaces a
 * pantallas que existen y se reintenta una vez si se le colaron palabras técnicas.
 */
class GeneradorGuias
{
    public const CLAVE_IA = 'asistente.entrenamiento';

    /** Cambiarla obliga a regenerar todas las guías aunque su material no haya cambiado. */
    private const VERSION_PROMPT = 'v3';

    private array $config;

    private ?array $formularios = null;

    public function __construct(private FuentesSistema $fuentes, private RegistroHerramientas $registro)
    {
        $this->config = $fuentes->config();
    }

    public function carpeta(): string
    {
        return resource_path('asistente/guia/modulos');
    }

    /** @return string[] slugs de los módulos con guía (docs/modulos menos los excluidos). */
    public function modulos(): array
    {
        return array_values(array_diff(array_keys($this->fuentes->documentos()), $this->config['excluir'] ?? []));
    }

    /** El material que se le da a la IA para un módulo (también sirve para --ver). */
    public function material(string $slug): array
    {
        $doc = (string) @file_get_contents($this->fuentes->documentos()[$slug] ?? '');
        $pantallas = array_values(array_filter($this->fuentes->pantallas(), fn ($p) => $this->fuentes->moduloDe(null, $p['ruta']) === $slug));
        $altas = array_values(array_filter($this->formularios(), fn ($f) => $f['modulo'] === $slug));
        usort($altas, fn ($a, $b) => (int) empty($a['pantalla']) <=> (int) empty($b['pantalla']));

        return [
            'documentacion' => mb_substr($doc, 0, $this->config['max_caracteres_documentacion'] ?? 18000),
            'manual'        => $this->fuentes->manual($slug, $this->config['max_caracteres_manual'] ?? 5000),
            'pantallas'     => $pantallas,
            'altas'         => $altas,
            'nota'          => $this->config['notas'][$slug] ?? '',
            'guia_equipo'   => $this->guiaDelEquipo($slug),
        ];
    }

    /**
     * Genera (o salta, si su material no cambió) la guía de un módulo.
     *
     * @return array{estado:string, detalle:string}
     */
    public function generar(string $slug, bool $forzar = false): array
    {
        $material = $this->material($slug);
        $huella = sha1(self::VERSION_PROMPT . json_encode($material));
        $archivo = $this->carpeta() . "/{$slug}.md";
        if (!$forzar && is_file($archivo) && str_contains((string) file_get_contents($archivo), "huella {$huella}")) {
            return ['estado' => 'igual', 'detalle' => 'sin cambios en su material'];
        }

        $clave = IA::configurada(self::CLAVE_IA) ? self::CLAVE_IA : AsistenteService::CLAVE_IA;
        $prompt = $this->prompt($slug, $material);
        $guia = null;
        $tecnicas = [];
        for ($intento = 1; $intento <= 2; $intento++) {
            $r = IA::enviar($clave, $prompt, [], $this->system(), [], ['max_tokens' => 2500]);
            $guia = $this->limpiar((string) ($r['texto'] ?? ''), $material['pantallas']);
            $tecnicas = $this->palabrasTecnicas($guia);
            if (!$tecnicas && str_starts_with($guia, '# ')) {
                break;
            }
            $prompt .= "\n\nTu versión anterior tenía problemas"
                . ($tecnicas ? ' (palabras técnicas: ' . implode(', ', $tecnicas) . ')' : '')
                . (!str_starts_with($guia, '# ') ? ' (no empezaba con "# " y el nombre)' : '')
                . '. Vuelve a escribir la guía completa corrigiéndolo.';
        }
        if ($guia === null || !str_starts_with($guia, '# ')) {
            return ['estado' => 'error', 'detalle' => 'la IA no devolvió una guía con el formato pedido'];
        }

        if (!is_dir($this->carpeta())) {
            mkdir($this->carpeta(), 0775, true);
        }
        $cabecera = "<!-- Generada por `php artisan asistente:aprender {$slug}` desde docs/modulos/{$slug}.md, el menú y las "
            . "pantallas de alta · huella {$huella} · " . now()->toDateString() . ". No editar a mano: corregir la "
            . "documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->\n";
        file_put_contents($archivo, $cabecera . $guia . "\n");

        return ['estado' => 'nueva', 'detalle' => mb_strlen($guia) . ' caracteres'
            . ($tecnicas ? ' · revisar palabras técnicas: ' . implode(', ', $tecnicas) : '')];
    }

    /** Índice de temas que va en el arranque de cada mensaje de JARVIS. */
    public function escribirIndice(): int
    {
        $lineas = [
            '# Guías por módulo',
            '',
            'Para dudas de un módulo, pide su guía con `consultar_guia` (tema `modulos/<nombre>`). Cada guía trae',
            'sus pantallas con enlace, cómo se hacen las tareas y qué datos pide cada alta. Si existe una guía revisada',
            'por el equipo del mismo tema (clientes, pagos-y-facturacion, tickets, red-y-equipos), esa manda.',
            '',
        ];
        $n = 0;
        foreach (glob($this->carpeta() . '/*.md') ?: [] as $f) {
            $texto = (string) file_get_contents($f);
            $titulo = preg_match('/^# (.+)$/m', $texto, $m) ? trim($m[1]) : basename($f, '.md');
            $resumen = preg_match('/\*\*En pocas palabras:\*\*\s*(.+)$/m', $texto, $m) ? trim($m[1]) : '';
            $lineas[] = '- `modulos/' . basename($f, '.md') . "` — **{$titulo}**" . ($resumen !== '' ? ": {$resumen}" : '');
            $n++;
        }
        file_put_contents(resource_path('asistente/guia/_temas.md'), implode("\n", $lineas) . "\n");

        return $n;
    }

    /** La guía revisada a mano que corresponde a este módulo, si hay (entrenamiento.json → guias_del_equipo). */
    private function guiaDelEquipo(string $slug): string
    {
        $nombre = $this->config['guias_del_equipo'][$slug] ?? null;
        $archivo = $nombre && preg_match('/^[a-z0-9-]+$/', $nombre) ? resource_path("asistente/guia/{$nombre}.md") : null;

        return $archivo && is_file($archivo) ? trim((string) file_get_contents($archivo)) : '';
    }

    private function formularios(): array
    {
        return $this->formularios ??= $this->fuentes->formularios();
    }

    private function system(): string
    {
        $max = $this->config['max_caracteres_guia'] ?? 5000;

        return <<<TXT
Escribes la guía interna de JARVIS, el asistente del sistema MegaISP de Meganet (proveedor de internet en México).
La guía es para que JARVIS ayude al personal: mostrador, ventas, técnicos, cobranza y administración.

Reglas:
- Español de México, claro y directo. Habla de pantallas, botones, clientes, pagos; nunca de cómo está programado.
- PROHIBIDO lenguaje técnico: no menciones controladores, tablas, base de datos, modelos, código, archivos, rutas de
  programación, nombres de clases, permisos con guiones bajos, JSON, API, webhooks, colas ni nombres de campos internos
  (usa la etiqueta que ve el usuario: "Apellido paterno", no "father_last_name").
- Solo escribe lo que dice el material. Si algo no está claro, no lo pongas. Nada de suposiciones.
- "Cómo se hace": SOLO pasos que el material describe con claridad (en qué pantalla, qué botón). Si el material no dice
  los pasos de una tarea, escribe únicamente "Se hace en [Pantalla](/ruta)." sin inventar botones, campos ni orden.
  Inventar un paso es el peor error posible: el personal lo va a seguir al pie de la letra.
- Enlaces: SOLO los de la lista PANTALLAS, copiados exactos, con la forma [Etiqueta](/ruta). Nunca inventes una
  dirección ni le agregues dominio.
- En "Qué datos pide" COPIA las listas de ALTAS tal cual vienen (obligatorios, opcionales, se llenan solos): no agregues,
  no quites ni cambies campos de lista. Ponle a cada alta un título claro (Alta de cliente, Nuevo ticket...). Si los
  opcionales son muchos, deja los 8 más útiles y termina con "entre otros". Si no hay altas en el material, omite la sección.
- Máximo {$max} caracteres. Omite las secciones que no apliquen.

Formato exacto (Markdown):
# <Nombre del módulo como lo diría el personal>
**En pocas palabras:** <una sola frase>

## Para qué sirve
<2 a 4 viñetas>

## Pantallas
- [Etiqueta](/ruta) — <qué se hace ahí>

## Cómo se hace
### <Tarea común>
1. <paso>

## Qué datos pide
### <Alta de ...> ([Etiqueta de la pantalla](/ruta) si está en PANTALLAS)
- **Obligatorios:** <de la lista>
- **Opcionales:** <de la lista>
- **Se llenan solos:** <de la lista>

## Quién lo usa
<1 o 2 frases>

## Con JARVIS
<qué puede hacer JARVIS en este módulo según la lista de capacidades; si ninguna aplica: "Por ahora JARVIS te explica
cómo hacerlo y te da el enlace a la pantalla.">
TXT;
    }

    private function prompt(string $slug, array $m): string
    {
        $pantallas = $m['pantallas']
            ? implode("\n", array_map(fn ($p) => '- ' . trim(($p['seccion'] !== '' ? $p['seccion'] . ' › ' : '') . $p['etiqueta']) . " — {$p['ruta']}", $m['pantallas']))
            : '(ninguna en el menú; no pongas sección Pantallas)';

        $altas = '';
        $max = $this->config['max_caracteres_altas'] ?? 12000;
        foreach ($m['altas'] as $a) {
            $r = $a['requisitos'];
            $lista = fn (array $xs) => $xs ? implode(', ', $xs) : '(ninguno)';
            $bloque = $a['pantalla']
                ? "### Alta en la pantalla {$a['pantalla']}\n"
                : "### Alta que se hace DENTRO de otra pantalla (no tiene pantalla propia: no le pongas enlace)\n";
            if ($r['reglas_conocidas']) {
                $bloque .= "- Obligatorios: {$lista($r['obligatorios'])}\n"
                    . ($r['segun_el_caso'] ? "- Obligatorios según el caso: {$lista($r['segun_el_caso'])}\n" : '')
                    . "- Opcionales: {$lista($r['opcionales'])}\n";
            } else {
                $bloque .= "- La pantalla no marca qué es obligatorio. Campos: {$lista(array_merge($r['obligatorios'], $r['opcionales']))}\n";
            }
            $bloque .= ($r['se_llenan_solos'] ? "- Se llenan solos: {$lista($r['se_llenan_solos'])}\n" : '') . "\n";
            if (mb_strlen($altas) + mb_strlen($bloque) > $max) {
                break;
            }
            $altas .= $bloque;
        }

        $capacidades = implode("\n", array_map(
            fn ($h) => "- {$h->nombre()}: {$h->descripcion()}",
            array_values($this->registro->todas())
        ));

        return "MÓDULO: {$slug}\n\n"
            . "=== DOCUMENTACIÓN DEL MÓDULO (técnica: tradúcela a lenguaje de usuario) ===\n{$m['documentacion']}\n\n"
            . ($m['manual'] !== '' ? "=== MANUAL DEL MÓDULO ===\n{$m['manual']}\n\n" : '')
            . "=== PANTALLAS (únicos enlaces permitidos) ===\n{$pantallas}\n\n"
            . ($altas !== '' ? "=== ALTAS DE ESTE MÓDULO ===\n{$altas}" : '')
            . ($m['guia_equipo'] !== '' ? "=== GUÍA REVISADA POR EL EQUIPO (es la verdad: si la documentación dice otra cosa, manda esta) ===\n{$m['guia_equipo']}\n\n" : '')
            . ($m['nota'] !== '' ? "=== NOTA DEL EQUIPO (es verdad, inclúyela) ===\n{$m['nota']}\n\n" : '')
            . "=== CAPACIDADES ACTUALES DE JARVIS ===\n{$capacidades}\n\n"
            . 'Escribe la guía de este módulo con el formato indicado.';
    }

    /** Quita cercos de código, deja solo enlaces a pantallas reales y sin dominio. */
    private function limpiar(string $texto, array $pantallas): string
    {
        $texto = trim(preg_replace('/^```(?:markdown|md)?\s*|\s*```$/i', '', trim($texto)));
        // Restos de la plantilla: "### <Alta de ingreso>" → "### Alta de ingreso".
        $texto = preg_replace('/^(#{1,4}\s*)<([^>\n]+)>/m', '$1$2', $texto) ?? $texto;
        $permitidas = array_fill_keys(array_column($this->fuentes->pantallas(), 'ruta'), true) + $this->fuentes->rutasGet();

        $texto = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use ($permitidas) {
            $ruta = preg_replace('#^https?://[^/]+#i', '', trim($m[2]));
            $ruta = '/' . ltrim($ruta, '/');
            $soloRuta = explode('?', $ruta)[0];

            return (isset($permitidas[$ruta]) || isset($permitidas[$soloRuta])) && !str_starts_with($soloRuta, '/api/')
                ? "[{$m[1]}]({$ruta})"
                : $m[1];
        }, $texto) ?? $texto;

        return trim(preg_replace('#https?://[^\s)]+#i', '', $texto) ?? $texto);
    }

    private function palabrasTecnicas(string $texto): array
    {
        $encontradas = [];
        $minus = mb_strtolower($texto);
        foreach ($this->config['palabras_tecnicas'] ?? [] as $p) {
            $p = mb_strtolower($p);
            $hay = preg_match('/[^\p{L} ]/u', $p)
                ? str_contains($minus, $p)
                : preg_match('/(?<!\p{L})' . preg_quote($p, '/') . '(?!\p{L})/u', $minus);
            if ($hay) {
                $encontradas[] = $p;
            }
        }

        return $encontradas;
    }
}
