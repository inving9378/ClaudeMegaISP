<?php

namespace App\Services\Asistente\Entrenamiento;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Junta, del propio sistema, el material con el que se escriben las guías de JARVIS:
 * - la documentación de cada módulo (docs/modulos/*.md),
 * - las pantallas reales del menú lateral, tal como las ve un usuario con todos los permisos,
 * - las pantallas de alta: sus campos (field_modules) y sus reglas (FormRequest o el store()),
 * - los títulos del manual del módulo.
 * Nada de esto se le manda a JARVIS en crudo: lo lee la IA que redacta las guías.
 */
class FuentesSistema
{
    private ?array $pantallas = null;

    private ?array $rutasGet = null;

    private ?array $segmentos = null;

    public function __construct(private User $usuarioMenu)
    {
    }

    /** @return array<string,string> slug => ruta del .md */
    public function documentos(): array
    {
        $docs = [];
        foreach (glob(base_path('docs/modulos/*.md')) ?: [] as $f) {
            $docs[basename($f, '.md')] = $f;
        }
        ksort($docs);

        return $docs;
    }

    /**
     * Pantallas del menú lateral: [seccion, etiqueta, ruta]. Se arma renderizando la barra real
     * (Blade con sus @if de permisos) con un usuario que lo ve todo.
     */
    public function pantallas(): array
    {
        if ($this->pantallas !== null) {
            return $this->pantallas;
        }
        $anterior = auth()->user();
        auth()->setUser($this->usuarioMenu);
        try {
            $html = view('core-layout::sidebar')->render();
        } finally {
            $anterior ? auth()->setUser($anterior) : auth()->forgetUser();
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xp = new \DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//a[@href]') as $a) {
            $ruta = $this->ruta($a->getAttribute('href'));
            $etiqueta = self::limpio($a->textContent);
            if ($ruta === null || $etiqueta === '') {
                continue;
            }
            // Sección = el enlace "padre" del <ul> que contiene a este enlace (Clientes › Crear).
            $seccion = '';
            for ($n = $a->parentNode; $n && $n->nodeName !== 'body'; $n = $n->parentNode) {
                if ($n->nodeName === 'ul' && $n->previousSibling) {
                    $prev = $n->previousSibling;
                    while ($prev && $prev->nodeType !== XML_ELEMENT_NODE) {
                        $prev = $prev->previousSibling;
                    }
                    if ($prev && $prev->nodeName === 'a') {
                        $seccion = self::limpio($prev->textContent) . ($seccion !== '' ? ' › ' . $seccion : '');
                    }
                }
            }
            $out[$ruta] ??= ['seccion' => $seccion, 'etiqueta' => $etiqueta, 'ruta' => $ruta];
        }

        return $this->pantallas = array_values($out);
    }

    /** Rutas GET sin parámetros que existen (para validar los enlaces de las guías). */
    public function rutasGet(): array
    {
        if ($this->rutasGet !== null) {
            return $this->rutasGet;
        }
        $rutas = [];
        foreach (Route::getRoutes() as $r) {
            if (in_array('GET', $r->methods(), true) && !str_contains($r->uri(), '{')) {
                $rutas['/' . ltrim($r->uri(), '/')] = true;
            }
        }

        return $this->rutasGet = $rutas;
    }

    /**
     * Pantallas de alta del sistema (POST a store/crear) con sus campos y la fuente de sus reglas.
     *
     * @return array<int, array{modulo:?string, pantalla:?string, envia:string, campos:array, reglas:string}>
     */
    public function formularios(): array
    {
        $porControlador = [];
        foreach (Route::getRoutes() as $r) {
            $accion = $r->getActionName();
            if (!str_contains($accion, '@')) {
                continue;
            }
            [$clase, $metodo] = explode('@', $accion);
            if (in_array('GET', $r->methods(), true) && in_array($metodo, ['create', 'crear'], true) && !str_contains($r->uri(), '{')) {
                $porControlador[$clase]['pantalla'] = '/' . ltrim($r->uri(), '/');
            }
            if (in_array('POST', $r->methods(), true) && in_array($metodo, ['store', 'crear'], true)) {
                $porControlador[$clase]['envia'] ??= '/' . ltrim($r->uri(), '/');
                $porControlador[$clase]['metodo'] = $metodo;
            }
        }

        $out = [];
        foreach ($porControlador as $clase => $info) {
            if (empty($info['envia']) || !class_exists($clase) || !method_exists($clase, $info['metodo'])) {
                continue;
            }
            $ref = new \ReflectionMethod($clase, $info['metodo']);
            $tipo = ($ref->getParameters()[0] ?? null)?->getType()?->getName();
            $formRequest = $tipo && is_subclass_of($tipo, FormRequest::class) ? $tipo : null;
            $reglas = ($formRequest ? self::fuente(new \ReflectionMethod($formRequest, 'rules')) . "\n" : '') . self::fuente($ref, 2500);
            $campos = $this->camposDe($clase);

            $out[] = [
                'modulo'     => $this->moduloDe($clase, $info['pantalla'] ?? $info['envia']),
                'pantalla'   => $info['pantalla'] ?? null,
                'envia'      => $info['envia'],
                'campos'     => $campos,
                'requisitos' => $this->requisitos($campos, $this->obligatoriosDe($formRequest, $info['envia'], $reglas)),
                'reglas'     => $reglas,
            ];
        }

        return $out;
    }

    /**
     * Qué pide un alta, ya en etiquetas de pantalla: lo obligatorio, lo opcional y lo que el
     * formulario llena solo (usuario/contraseña generados, valores que ya vienen puestos).
     * Se calcula aquí y no lo adivina la IA porque es justo lo que JARVIS le va a pedir al usuario.
     *
     * @param array{obligatorios:string[], segun_el_caso:string[], conocidas:bool} $reglas
     */
    private function requisitos(array $campos, array $reglas): array
    {
        $auto = $opc = $obl = $caso = [];
        $vistos = [];
        foreach ($campos as $c) {
            $campo = $c['campo'];
            $vistos[$campo] = true;
            $etq = self::etiqueta($campo, $c['etiqueta'] ?? '');
            if (in_array((int) ($c['tipo'] ?? 0), [8, 10], true)) {
                $auto[] = "{$etq} (se genera sola)";
            } elseif (isset($c['default'])) {
                $auto[] = "{$etq} (ya viene puesto: " . self::valorLegible($c['default'], $c['mapa'] ?? null, (int) ($c['tipo'] ?? 0)) . ')';
            } elseif (in_array($campo, $reglas['obligatorios'], true)) {
                $obl[] = $etq . (!empty($c['opciones']) ? ' (' . implode(' / ', $c['opciones']) . ')' : '');
            } elseif (in_array($campo, $reglas['segun_el_caso'], true)) {
                $caso[] = $etq;
            } else {
                $opc[] = $etq;
            }
        }
        // Obligatorios de las reglas que el formulario no declara como campo (formularios hechos a mano).
        foreach ($reglas['obligatorios'] as $campo) {
            if (!isset($vistos[$campo]) && !str_contains($campo, '.')) {
                $obl[] = self::etiqueta($campo, '');
            }
        }

        return [
            'reglas_conocidas'    => $reglas['conocidas'],
            'obligatorios'        => array_values(array_unique($obl)),
            'segun_el_caso'       => array_values(array_unique($caso)),
            'opcionales'          => array_values(array_unique($opc)),
            'se_llenan_solos'     => array_values(array_unique($auto)),
        ];
    }

    /**
     * Campos obligatorios según las reglas reales: las del FormRequest (se ejecuta rules()) y las
     * que el store() valida en línea ($request->validate([...]) / Validator::make).
     *
     * @return array{obligatorios:string[], segun_el_caso:string[], conocidas:bool}
     */
    private function obligatoriosDe(?string $formRequest, string $uri, string $fuente): array
    {
        $reglas = [];
        if ($formRequest) {
            try {
                $req = \App\Services\Asistente\Herramientas\Formulario::peticion($formRequest, $uri, [], $this->usuarioMenu);
                $reglas = \App\Services\Asistente\Herramientas\Formulario::comoPeticionActual($req, fn () => app()->call([$req, 'rules']));
            } catch (\Throwable) {
                $reglas = [];
            }
        }
        // Lo que se valida dentro del código del alta (o del FormRequest si rules() no se pudo correr).
        if (preg_match_all('/[\'"]([A-Za-z0-9_.*]+)[\'"]\s*=>\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")/', $fuente, $m, PREG_SET_ORDER)) {
            foreach ($m as $par) {
                if (preg_match('/required|nullable|string|numeric|integer|email|exists|unique|max:|min:|date|boolean|in:/', $par[2])) {
                    $reglas[$par[1]] ??= $par[2];
                }
            }
        }

        $obl = $caso = [];
        foreach ($reglas as $campo => $regla) {
            $texto = is_array($regla) ? implode('|', array_map(fn ($x) => is_string($x) ? $x : '', $regla)) : (string) $regla;
            if (preg_match('/(?<![a-z_])required(?![a-z_])/', $texto)) {
                $obl[] = (string) $campo;
            } elseif (preg_match('/required_(if|with|unless|without)/', $texto)) {
                $caso[] = (string) $campo;
            }
        }

        return ['obligatorios' => $obl, 'segun_el_caso' => $caso, 'conocidas' => $reglas !== []];
    }

    /** Etiqueta que ve el usuario; si la guardada no sirve (vacía o un nombre técnico), una legible. */
    private static function etiqueta(string $campo, string $etiqueta): string
    {
        $conocidas = [
            'colony_id' => 'Colonia', 'municipality_id' => 'Municipio', 'state_id' => 'Estado', 'zip' => 'Código postal',
            'name' => 'Nombre', 'father_last_name' => 'Apellido paterno', 'mother_last_name' => 'Apellido materno',
            'email' => 'Correo', 'phone' => 'Teléfono', 'phone2' => 'Teléfono 2', 'nif_pasaport' => 'RFC/CURP',
            'customer_lead' => 'Cliente', 'topic' => 'Tema', 'priority' => 'Prioridad', 'description' => 'Descripción',
            'title' => 'Título', 'price' => 'Precio', 'amount' => 'Monto', 'date' => 'Fecha', 'comment' => 'Comentario',
        ];
        if ($etiqueta !== '' && !str_contains($etiqueta, '\\') && !preg_match('/^[a-z0-9_]+$/', $etiqueta)) {
            return $etiqueta;
        }

        return $conocidas[$campo] ?? ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $campo)));
    }

    private static function valorLegible(string $valor, ?array $mapa, int $tipo): string
    {
        if ($mapa && isset($mapa[$valor]) && $mapa[$valor] !== '') {
            return $mapa[$valor];
        }
        if ($tipo === 16 || in_array($valor, ['true', 'false'], true)) {
            return in_array($valor, ['1', 'true'], true) ? 'sí' : 'no';
        }

        return match ($valor) {
            'now'                => 'hoy',
            'user_authenticated' => 'quien lo captura',
            default              => $tipo === 22 && $mapa === null ? 'la opción de siempre' : $valor,
        };
    }

    /** Texto del manual del módulo (versión más reciente), ya sin HTML. */
    public function manual(string $slug, int $max = 6000): string
    {
        $claves = [$slug, "addon-{$slug}", "core-{$slug}"];
        $version = DB::table('manual_sections')->whereIn('module_slug', $claves)->max('version');
        if ($version === null) {
            return '';
        }
        $texto = DB::table('manual_sections')->whereIn('module_slug', $claves)->where('version', $version)
            ->orderBy('id')->get(['title', 'content'])
            ->map(fn ($s) => '## ' . self::limpio((string) $s->title) . "\n" . trim(strip_tags((string) $s->content)))
            ->implode("\n\n");

        return mb_substr($texto, 0, $max);
    }

    /**
     * A qué documento (docs/modulos/<slug>.md) pertenece una pantalla o un controlador:
     * 1) el módulo del controlador que la atiende (App\Modules\Core|Addons\<Nombre>),
     * 2) el mapa fijo de resources/asistente/entrenamiento.json (controladores viejos),
     * 3) el documento que más menciona el primer tramo de la ruta.
     */
    public function moduloDe(?string $clase, ?string $ruta): ?string
    {
        $docs = $this->documentos();
        $clase ??= $ruta ? $this->controladorDe($ruta) : null;
        if ($clase && preg_match('#^App\\\\Modules\\\\(?:Core|Addons)\\\\([A-Za-z]+)\\\\#', $clase, $m) && isset($docs[strtolower($m[1])])) {
            return strtolower($m[1]);
        }
        $seg = $ruta ? strtolower(explode('/', trim(explode('?', $ruta)[0], '/'))[0] ?? '') : '';
        $fijo = $this->config()['segmentos'][$seg] ?? null;
        if ($fijo && isset($docs[$fijo])) {
            return $fijo;
        }

        return $seg !== '' ? ($this->segmentos()[$seg] ?? null) : null;
    }

    public function config(): array
    {
        static $config = null;

        return $config ??= (json_decode((string) @file_get_contents(resource_path('asistente/entrenamiento.json')), true) ?: []);
    }

    private function controladorDe(string $ruta): ?string
    {
        try {
            $r = Route::getRoutes()->match(\Illuminate\Http\Request::create(explode('?', $ruta)[0], 'GET'));
            $accion = $r->getActionName();

            return str_contains($accion, '@') ? explode('@', $accion)[0] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** primer segmento de URL => documento que más lo menciona. */
    private function segmentos(): array
    {
        if ($this->segmentos !== null) {
            return $this->segmentos;
        }
        $segs = [];
        foreach (array_keys($this->rutasGet()) as $ruta) {
            $s = strtolower(explode('/', trim($ruta, '/'))[0] ?? '');
            if ($s !== '' && preg_match('/^[a-z0-9-]+$/', $s)) {
                $segs[$s] = true;
            }
        }
        $textos = array_map(fn ($f) => (string) file_get_contents($f), $this->documentos());
        $mapa = [];
        foreach (array_keys($segs) as $s) {
            $mejor = null;
            $max = 0;
            foreach ($textos as $slug => $txt) {
                $n = preg_match_all('#/' . preg_quote($s, '#') . '(?=[/`\s)*\'"]|$)#', $txt);
                if ($n > $max) {
                    [$mejor, $max] = [$slug, $n];
                }
            }
            if ($mejor) {
                $mapa[$s] = $mejor;
            }
        }

        return $this->segmentos = $mapa;
    }

    /** Campos del formulario según field_modules del módulo que usa el controlador. */
    private function camposDe(string $clase): array
    {
        try {
            $ctrl = app($clase);
            $ref = new \ReflectionObject($ctrl);
            $data = $ref->hasProperty('data') ? $ref->getProperty('data')->getValue($ctrl) : null;
            $modulo = is_array($data) ? ($data['module'] ?? null) : null;
        } catch (\Throwable) {
            $modulo = null;
        }
        if (!$modulo) {
            return [];
        }
        $nombres = [$modulo];
        // Los altas de cliente y prospecto reparten sus campos en sub-módulos (ClientMainInformation...).
        $extra = ['client' => ['ClientMainInformation', 'ClientAdditionalInformation'], 'crm' => ['CrmMainInformation', 'CrmLeadInformation']];
        $nombres = array_merge($nombres, $extra[strtolower($modulo)] ?? []);

        return DB::table('field_modules as f')->join('modules as m', 'm.id', '=', 'f.module_id')
            ->whereIn('m.name', $nombres)->where('f.include', 1)
            ->orderBy('f.position')->get(['f.name', 'f.label', 'f.type', 'f.options', 'f.default_value'])
            ->map(function ($f) {
                $opciones = json_decode((string) $f->options, true);
                $lista = is_array($opciones) && !array_is_list($opciones) && count($opciones) <= 15
                    ? array_values(array_map(fn ($v) => self::limpio(is_scalar($v) ? (string) $v : ''), $opciones)) : null;

                return array_filter([
                    'campo'    => $f->name,
                    'etiqueta' => self::limpio((string) $f->label),
                    'tipo'     => (int) $f->type,
                    'opciones' => $lista ? array_values(array_filter($lista)) : null,
                    'mapa'     => $lista ? array_map(fn ($v) => self::limpio(is_scalar($v) ? (string) $v : ''), $opciones) : null,
                    'default'  => $f->default_value !== null && $f->default_value !== '' ? trim((string) $f->default_value, '"') : null,
                ], fn ($v) => $v !== null && $v !== '' && $v !== []);
            })
            // Un mismo campo puede venir de dos sub-formularios: se queda el que trae valor por defecto.
            ->groupBy('campo')->map(fn ($g) => $g->first(fn ($c) => isset($c['default'])) ?? $g->first())
            ->values()->all();
    }

    private function ruta(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || $href[0] === '#' || preg_match('#^(javascript|mailto|tel):#i', $href)) {
            return null;
        }
        $path = parse_url($href, PHP_URL_PATH) ?: '/';
        $query = parse_url($href, PHP_URL_QUERY);

        return '/' . ltrim($path, '/') . ($query ? '?' . $query : '');
    }

    private static function fuente(\ReflectionMethod $m, int $max = 2000): string
    {
        $archivo = $m->getFileName();
        if (!$archivo || !is_file($archivo)) {
            return '';
        }
        $lineas = array_slice(file($archivo), $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1);

        return mb_substr(implode('', $lineas), 0, $max);
    }

    private static function limpio(string $t): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
