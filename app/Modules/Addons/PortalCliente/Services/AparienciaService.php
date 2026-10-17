<?php

namespace App\Modules\Addons\PortalCliente\Services;

use App\Models\User;
use App\Modules\Addons\PortalCliente\Models\AparienciaImagen;
use App\Modules\Addons\PortalCliente\Models\AparienciaVersion;
use App\Modules\Addons\PortalCliente\Support\AparienciaCatalogo;
use App\Modules\Addons\PortalCliente\Support\LogoEmpresa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuración → Portal Cliente: flujo Editar → Guardar borrador → Vista previa → Publicar.
 *
 * - Lo publicado se sirve desde caché y solo cambia al publicar/restaurar-y-publicar; el borrador
 *   nunca llega a las pantallas públicas.
 * - Candado optimista: cada escritura del borrador manda la `revision` que cargó; si otro
 *   administrador escribió antes, AparienciaConflictoException (HTTP 409) en vez de pisarlo.
 * - Toda la configuración pasa por validar(): solo claves conocidas, textos planos (sin
 *   caracteres de control), números acotados, colores #RRGGBB, iconos/colores del catálogo e
 *   imágenes que existan y sean del tipo correcto.
 */
class AparienciaService
{
    private const CACHE_PUBLICADA = 'portal_cliente:apariencia:publicada';

    /** Opacidad base del velo sobre la fotografía con el degradado en su valor por defecto (80). */
    private const VELO_ESCRITORIO = [0.94, 0.80, 0.50, 0.70];
    private const VELO_MOVIL = [0.82, 0.93, 0.97];

    /** @var array<string, AparienciaImagen|null> */
    private array $imagenesCargadas = [];

    public function __construct(private AparienciaImagenService $imagenes)
    {
    }

    // ── Lectura ─────────────────────────────────────────────────────────────

    /** Configuración publicada (o la de fábrica si aún no hay ninguna). Nunca truena el login. */
    public function configPublicada(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_PUBLICADA, function () {
                $publicada = AparienciaVersion::where('estado', AparienciaVersion::PUBLICADA)->first();

                return $this->completar($publicada?->config ?? []);
            });
        } catch (\Throwable $e) {
            Log::warning('[PortalCliente/apariencia] Sin configuración publicada legible, se usa la de fábrica: ' . $e->getMessage());

            return AparienciaCatalogo::defaults();
        }
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICADA);
    }

    public function publicada(): ?AparienciaVersion
    {
        return AparienciaVersion::where('estado', AparienciaVersion::PUBLICADA)->first();
    }

    public function borrador(): ?AparienciaVersion
    {
        return AparienciaVersion::where('estado', AparienciaVersion::BORRADOR)->first();
    }

    /** Rellena con los valores de fábrica lo que falte (versiones viejas o configuración vacía). */
    public function completar(array $config): array
    {
        $defaults = AparienciaCatalogo::defaults();
        $completa = array_replace_recursive($defaults, Arr::except($config, ['tarjetas']));

        $porClave = [];
        foreach ($defaults['tarjetas'] as $tarjeta) {
            $porClave[$tarjeta['clave']] = $tarjeta;
        }
        $tarjetas = [];
        foreach ($config['tarjetas'] ?? [] as $tarjeta) {
            $clave = $tarjeta['clave'] ?? null;
            if (isset($porClave[$clave])) {
                $tarjetas[] = array_replace($porClave[$clave], $tarjeta);
                unset($porClave[$clave]);
            }
        }
        $completa['tarjetas'] = array_merge($tarjetas, array_values($porClave));

        return $completa;
    }

    /** UUIDs de imagen que referencia una configuración. */
    public function uuidsDe(array $config): array
    {
        return array_values(array_filter([
            $config['fondo']['escritorio'] ?? null,
            $config['fondo']['movil'] ?? null,
            $config['fondo']['respaldo'] ?? null,
            $config['identidad']['logo'] ?? null,
        ]));
    }

    // ── Validación ──────────────────────────────────────────────────────────

    /** Valida y normaliza una configuración que llega del panel. Lanza ValidationException. */
    public function validar(array $entrada): array
    {
        $porcentaje = ['required', 'integer', 'between:0,100'];
        $texto = fn (int $max, bool $requerido = false) => [$requerido ? 'required' : 'nullable', 'string', "max:{$max}"];
        $booleano = ['required', 'boolean'];
        $imagen = fn (string $tipo) => ['nullable', 'uuid', function ($atributo, $valor, $falla) use ($tipo) {
            if (! AparienciaImagen::where('uuid', $valor)->where('tipo', $tipo)->exists()) {
                $falla('La imagen seleccionada ya no existe; vuelve a subirla.');
            }
        }];

        $reglas = [
            'config'                              => ['required', 'array'],
            'config.fondo.escritorio'             => $imagen('escritorio'),
            'config.fondo.movil'                  => $imagen('movil'),
            'config.fondo.respaldo'               => $imagen('respaldo'),
            'config.fondo.posicion_x'             => $porcentaje,
            'config.fondo.posicion_y'             => $porcentaje,
            'config.fondo.degradado'              => $porcentaje,
            'config.fondo.oscurecer'              => ['required', 'integer', 'between:0,80'],
            'config.fondo.opacidad_imagen'        => ['required', 'integer', 'between:20,100'],
            'config.identidad.logo'               => $imagen('logo'),
            'config.identidad.titulo'             => $texto(60, true),
            'config.identidad.subtitulo'          => $texto(80),
            'config.identidad.eslogan'            => $texto(60),
            'config.identidad.mostrar_eslogan'    => $booleano,
            'config.identidad.tarjetas.opacidad'  => ['required', 'integer', 'between:20,95'],
            'config.identidad.tarjetas.radio'     => ['required', 'integer', 'between:6,28'],
            'config.identidad.formulario.opacidad'   => ['required', 'integer', 'between:55,98'],
            'config.identidad.formulario.desenfoque' => ['required', 'integer', 'between:0,30'],
            'config.identidad.formulario.radio'      => ['required', 'integer', 'between:8,32'],
            'config.mensajes.titulo'              => $texto(90),
            'config.mensajes.destacado'           => $texto(40),
            'config.mensajes.descripcion'         => $texto(240),
            'config.mensajes.mostrar_titulo'      => $booleano,
            'config.mensajes.mostrar_descripcion' => $booleano,
            'config.mensajes.tamano'              => ['required', Rule::in(['compacto', 'normal', 'grande'])],
            'config.mensajes.alineacion'          => ['required', Rule::in(['izquierda', 'centro'])],
            'config.tarjetas'                     => ['required', 'array', 'size:' . count(AparienciaCatalogo::TARJETAS)],
            'config.tarjetas.*.clave'             => ['required', 'distinct', Rule::in(AparienciaCatalogo::TARJETAS)],
            'config.tarjetas.*.titulo'            => $texto(40, true),
            'config.tarjetas.*.descripcion'       => $texto(140),
            'config.tarjetas.*.icono'             => ['required', Rule::in(AparienciaCatalogo::claveIconos())],
            'config.tarjetas.*.color'             => ['required', Rule::in(AparienciaCatalogo::claveColores())],
            'config.tarjetas.*.activa'            => $booleano,
            'config.pantallas.login.mostrar_identidad'     => $booleano,
            'config.pantallas.login.mostrar_tarjetas'      => $booleano,
        ];
        foreach (AparienciaCatalogo::COLORES_CONFIG as $color) {
            $reglas["config.identidad.colores.{$color}"] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }
        foreach (['registro', 'recuperar'] as $pantalla) {
            $reglas["config.pantallas.{$pantalla}.titulo"] = $texto(60, true);
            $reglas["config.pantallas.{$pantalla}.subtitulo"] = $texto(80);
            $reglas["config.pantallas.{$pantalla}.mostrar_identidad"] = $booleano;
            $reglas["config.pantallas.{$pantalla}.mostrar_tarjetas"] = $booleano;
        }

        $validador = Validator::make(['config' => $entrada], $reglas, [
            'regex'    => 'El color debe tener el formato #RRGGBB.',
            'size'     => 'Deben venir las :size tarjetas.',
            'distinct' => 'Hay tarjetas repetidas.',
        ]);
        $validador->after(function ($v) use ($entrada) {
            if (($entrada['mensajes']['mostrar_titulo'] ?? false) && trim((string) ($entrada['mensajes']['titulo'] ?? '')) === '') {
                $v->errors()->add('config.mensajes.titulo', 'Escribe el título principal o desactiva su visibilidad.');
            }
        });

        // validate() devuelve SOLO las claves con regla: cualquier clave desconocida se descarta.
        return $this->normalizar($validador->validate()['config']);
    }

    private function normalizar(array $c): array
    {
        $txt = fn ($valor) => $this->textoPlano($valor);
        $int = fn ($valor) => (int) $valor;
        $bool = fn ($valor) => filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        $img = fn ($valor) => $valor ? (string) $valor : null;

        $colores = [];
        foreach (AparienciaCatalogo::COLORES_CONFIG as $color) {
            $colores[$color] = strtoupper($c['identidad']['colores'][$color]);
        }

        $tarjetas = [];
        foreach ($c['tarjetas'] as $t) {
            $tarjetas[] = [
                'clave'       => $t['clave'],
                'titulo'      => $txt($t['titulo']),
                'descripcion' => $txt($t['descripcion'] ?? ''),
                'icono'       => $t['icono'],
                'color'       => $t['color'],
                'activa'      => $bool($t['activa']),
            ];
        }

        $pantallas = [
            'login' => [
                'mostrar_identidad' => $bool($c['pantallas']['login']['mostrar_identidad']),
                'mostrar_tarjetas'  => $bool($c['pantallas']['login']['mostrar_tarjetas']),
            ],
        ];
        foreach (['registro', 'recuperar'] as $p) {
            $pantallas[$p] = [
                'titulo'            => $txt($c['pantallas'][$p]['titulo']),
                'subtitulo'         => $txt($c['pantallas'][$p]['subtitulo'] ?? ''),
                'mostrar_identidad' => $bool($c['pantallas'][$p]['mostrar_identidad']),
                'mostrar_tarjetas'  => $bool($c['pantallas'][$p]['mostrar_tarjetas']),
            ];
        }

        return [
            'fondo' => [
                'escritorio'      => $img($c['fondo']['escritorio'] ?? null),
                'movil'           => $img($c['fondo']['movil'] ?? null),
                'respaldo'        => $img($c['fondo']['respaldo'] ?? null),
                'posicion_x'      => $int($c['fondo']['posicion_x']),
                'posicion_y'      => $int($c['fondo']['posicion_y']),
                'degradado'       => $int($c['fondo']['degradado']),
                'oscurecer'       => $int($c['fondo']['oscurecer']),
                'opacidad_imagen' => $int($c['fondo']['opacidad_imagen']),
            ],
            'identidad' => [
                'logo'            => $img($c['identidad']['logo'] ?? null),
                'titulo'          => $txt($c['identidad']['titulo']),
                'subtitulo'       => $txt($c['identidad']['subtitulo'] ?? ''),
                'eslogan'         => $txt($c['identidad']['eslogan'] ?? ''),
                'mostrar_eslogan' => $bool($c['identidad']['mostrar_eslogan']),
                'colores'         => $colores,
                'tarjetas'        => [
                    'opacidad' => $int($c['identidad']['tarjetas']['opacidad']),
                    'radio'    => $int($c['identidad']['tarjetas']['radio']),
                ],
                'formulario'      => [
                    'opacidad'   => $int($c['identidad']['formulario']['opacidad']),
                    'desenfoque' => $int($c['identidad']['formulario']['desenfoque']),
                    'radio'      => $int($c['identidad']['formulario']['radio']),
                ],
            ],
            'mensajes' => [
                'titulo'              => $txt($c['mensajes']['titulo'] ?? ''),
                'destacado'           => $txt($c['mensajes']['destacado'] ?? ''),
                'descripcion'         => $txt($c['mensajes']['descripcion'] ?? ''),
                'mostrar_titulo'      => $bool($c['mensajes']['mostrar_titulo']),
                'mostrar_descripcion' => $bool($c['mensajes']['mostrar_descripcion']),
                'tamano'              => $c['mensajes']['tamano'],
                'alineacion'          => $c['mensajes']['alineacion'],
            ],
            'tarjetas'  => $tarjetas,
            'pantallas' => $pantallas,
        ];
    }

    /** Texto plano de una línea: sin caracteres de control ni marcas de dirección (bidi). */
    private function textoPlano($valor): string
    {
        $sin = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', ' ', (string) $valor);

        return trim(preg_replace('/\s+/u', ' ', (string) $sin));
    }

    // ── Escritura ───────────────────────────────────────────────────────────

    public function guardarBorrador(array $entrada, int $revisionEsperada, int $usuarioId): AparienciaVersion
    {
        $config = $this->validar($entrada);

        return $this->escribirBorrador($revisionEsperada, $usuarioId, fn () => ['config' => $config]);
    }

    /** Copia una versión publicada (actual o del historial) al borrador para revisarla y publicarla. */
    public function restaurar(int $versionId, int $revisionEsperada, int $usuarioId): AparienciaVersion
    {
        $version = AparienciaVersion::whereIn('estado', [AparienciaVersion::PUBLICADA, AparienciaVersion::HISTORIAL])
            ->findOrFail($versionId);

        $borrador = $this->escribirBorrador($revisionEsperada, $usuarioId, fn () => [
            'config'            => $this->completar($version->config),
            'origen_version_id' => $version->id,
        ]);

        $this->registrarActividad("Restauró la versión {$version->numero} al borrador", $usuarioId, ['version' => $version->numero]);

        return $borrador;
    }

    public function descartarBorrador(int $revisionEsperada, int $usuarioId): void
    {
        DB::transaction(function () use ($revisionEsperada) {
            $borrador = AparienciaVersion::where('estado', AparienciaVersion::BORRADOR)->lockForUpdate()->first();
            if (! $borrador) {
                return;
            }
            $this->exigirRevision($borrador, $revisionEsperada);
            $borrador->delete();
        });

        $this->registrarActividad('Descartó el borrador', $usuarioId, []);
        $this->purgarHuerfanas();
    }

    public function publicar(int $revisionEsperada, ?string $nota, int $usuarioId): AparienciaVersion
    {
        $nueva = DB::transaction(function () use ($revisionEsperada, $nota, $usuarioId) {
            $borrador = AparienciaVersion::where('estado', AparienciaVersion::BORRADOR)->lockForUpdate()->first();
            if (! $borrador) {
                throw new AparienciaConflictoException('No hay un borrador para publicar: otro administrador pudo haberlo publicado o descartado.');
            }
            $this->exigirRevision($borrador, $revisionEsperada);

            $actual = AparienciaVersion::where('estado', AparienciaVersion::PUBLICADA)->lockForUpdate()->first();
            $antes = $this->completar($actual?->config ?? []);
            $despues = $this->completar($borrador->config);

            $cambios = $this->resumenCambios($antes, $despues);
            if ($cambios === []) {
                throw ValidationException::withMessages(['borrador' => 'El borrador es igual a lo publicado: no hay cambios que publicar.']);
            }
            if ($borrador->origen_version_id) {
                $origen = AparienciaVersion::find($borrador->origen_version_id);
                array_unshift($cambios, 'Restaurada desde la versión ' . ($origen?->numero ?? '?'));
            }

            if ($actual) {
                $actual->update(['estado' => AparienciaVersion::HISTORIAL, 'publicada_unica' => null]);
            }

            $nueva = AparienciaVersion::create([
                'estado'            => AparienciaVersion::PUBLICADA,
                'numero'            => ((int) AparienciaVersion::max('numero')) + 1,
                'config'            => $despues,
                'revision'          => 1,
                'base_version_id'   => $actual?->id,
                'origen_version_id' => $borrador->origen_version_id,
                'resumen_cambios'   => $cambios,
                'nota'              => $nota !== null ? mb_substr($this->textoPlano($nota), 0, 255) : null,
                'publicada_unica'   => 1,
                'published_by'      => $usuarioId,
                'published_at'      => now(),
                'created_by'        => $borrador->created_by ?? $usuarioId,
                'updated_by'        => $usuarioId,
            ]);

            $borrador->delete();

            return $nueva;
        });

        $this->olvidarCache();
        $this->registrarActividad("Publicó la versión {$nueva->numero}", $usuarioId, [
            'version' => $nueva->numero,
            'cambios' => $nueva->resumen_cambios,
        ]);
        $this->purgarHuerfanas();

        return $nueva;
    }

    /** Crea o actualiza el (único) borrador respetando el candado optimista. */
    private function escribirBorrador(int $revisionEsperada, int $usuarioId, \Closure $datos): AparienciaVersion
    {
        try {
            return DB::transaction(function () use ($revisionEsperada, $usuarioId, $datos) {
                $borrador = AparienciaVersion::where('estado', AparienciaVersion::BORRADOR)->lockForUpdate()->first();
                $publicada = AparienciaVersion::where('estado', AparienciaVersion::PUBLICADA)->first();
                $valores = $datos();

                if ($borrador) {
                    $this->exigirRevision($borrador, $revisionEsperada);
                    $borrador->update(array_merge($valores, [
                        'revision'   => $borrador->revision + 1,
                        'updated_by' => $usuarioId,
                    ]));

                    return $borrador->fresh();
                }

                if ($revisionEsperada !== 0) {
                    throw new AparienciaConflictoException('El borrador que estabas editando ya no existe: otro administrador lo publicó o lo descartó.');
                }

                return AparienciaVersion::create(array_merge($valores, [
                    'estado'          => AparienciaVersion::BORRADOR,
                    'revision'        => 1,
                    'base_version_id' => $publicada?->id,
                    'borrador_unico'  => 1,
                    'created_by'      => $usuarioId,
                    'updated_by'      => $usuarioId,
                ]));
            });
        } catch (QueryException $e) {
            // Dos administradores creando el primer borrador al mismo tiempo: gana uno (índice único).
            if (str_contains($e->getMessage(), 'borrador_unico')) {
                throw new AparienciaConflictoException('Otro administrador acaba de crear un borrador. Recarga para continuar sobre él.');
            }
            throw $e;
        }
    }

    private function exigirRevision(AparienciaVersion $borrador, int $revisionEsperada): void
    {
        if ($borrador->revision !== $revisionEsperada) {
            throw new AparienciaConflictoException(
                'Otro administrador guardó cambios en el borrador después de que lo abriste.',
                $this->nombreUsuario($borrador->updated_by),
                $borrador->updated_at?->format('d/m/Y H:i'),
            );
        }
    }

    // ── Resumen de cambios, bitácora y limpieza ─────────────────────────────

    public function resumenCambios(array $antes, array $despues): array
    {
        $cambios = [];
        $a = Arr::dot(Arr::except($antes, ['tarjetas']));
        $b = Arr::dot(Arr::except($despues, ['tarjetas']));
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $ruta) {
            if (($a[$ruta] ?? null) !== ($b[$ruta] ?? null)) {
                $cambios[] = AparienciaCatalogo::etiqueta($ruta);
            }
        }

        $tarjetasAntes = array_column($antes['tarjetas'] ?? [], null, 'clave');
        if (array_column($antes['tarjetas'] ?? [], 'clave') !== array_column($despues['tarjetas'] ?? [], 'clave')) {
            $cambios[] = 'Tarjetas › Orden';
        }
        $campos = ['titulo' => 'título', 'descripcion' => 'descripción', 'icono' => 'ícono', 'color' => 'color', 'activa' => 'visible'];
        foreach ($despues['tarjetas'] ?? [] as $tarjeta) {
            $previa = $tarjetasAntes[$tarjeta['clave']] ?? [];
            foreach ($campos as $campo => $nombre) {
                if (($previa[$campo] ?? null) !== ($tarjeta[$campo] ?? null)) {
                    $cambios[] = 'Tarjetas › ' . ($previa['titulo'] ?? $tarjeta['titulo']) . " › {$nombre}";
                }
            }
        }

        return array_values(array_unique($cambios));
    }

    /**
     * Borra imágenes que ninguna versión (borrador, publicada ni historial) referencia y que ya
     * pasaron el periodo de gracia. Lo que use una versión recuperable nunca se borra.
     */
    public function purgarHuerfanas(): int
    {
        try {
            $referenciadas = [];
            foreach (AparienciaVersion::query()->get(['config']) as $version) {
                $referenciadas = array_merge($referenciadas, $this->uuidsDe($version->config ?? []));
            }
            $limite = now()->subHours((int) config('portalcliente.apariencia.gracia_huerfanas_horas', 24));

            $borradas = 0;
            AparienciaImagen::whereNotIn('uuid', array_unique($referenciadas))
                ->where('created_at', '<', $limite)
                ->get()
                ->each(function (AparienciaImagen $imagen) use (&$borradas) {
                    $this->imagenes->eliminar($imagen);
                    $borradas++;
                });

            return $borradas;
        } catch (\Throwable $e) {
            Log::warning('[PortalCliente/apariencia] Limpieza de imágenes huérfanas falló: ' . $e->getMessage());

            return 0;
        }
    }

    private function registrarActividad(string $descripcion, int $usuarioId, array $propiedades): void
    {
        try {
            activity('portal_cliente_apariencia')
                ->causedBy(User::find($usuarioId))
                ->withProperties($propiedades)
                ->log($descripcion);
        } catch (\Throwable $e) {
            Log::warning('[PortalCliente/apariencia] No se pudo registrar la actividad: ' . $e->getMessage());
        }
    }

    public function nombreUsuario(?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $usuario = User::find($id);
        if (! $usuario) {
            return "Usuario #{$id}";
        }
        $nombre = trim(($usuario->name ?? '') . ' ' . ($usuario->father_last_name ?? ''));

        return $nombre !== '' ? $nombre : ($usuario->login_user ?? "Usuario #{$id}");
    }

    // ── Presentación (lo que consumen las vistas Blade) ─────────────────────

    public function vistaPublica(string $pantalla): array
    {
        return $this->vista($this->configPublicada(), $pantalla, null);
    }

    /**
     * Datos listos para pintar una pantalla de acceso. $fuentePrevia != null ⇒ modo vista previa
     * (imágenes por la ruta de administración, enlaces a la vista previa y formularios inertes).
     */
    public function vista(array $config, string $pantalla, ?array $fuentePrevia): array
    {
        $c = $this->completar($config);
        $previa = $fuentePrevia !== null;
        $this->precargarImagenes($this->uuidsDe($c));

        $escritorio = $this->urlImagen($c['fondo']['escritorio'], $previa) ?? $this->urlImagen($c['fondo']['respaldo'], $previa);
        $movil = $this->urlImagen($c['fondo']['movil'], $previa) ?? $escritorio;

        $titulo = (string) $c['mensajes']['titulo'];
        $destacado = (string) $c['mensajes']['destacado'];
        $partes = [$titulo, '', ''];
        if ($destacado !== '' && ($pos = mb_strpos($titulo, $destacado)) !== false) {
            $partes = [mb_substr($titulo, 0, $pos), $destacado, mb_substr($titulo, $pos + mb_strlen($destacado))];
        }

        $tarjetas = [];
        foreach ($c['tarjetas'] as $t) {
            if (! $t['activa']) {
                continue;
            }
            $tarjetas[] = [
                'titulo'      => $t['titulo'],
                'descripcion' => $t['descripcion'],
                'svg'         => AparienciaCatalogo::svgIcono($t['icono']),
                'color'       => AparienciaCatalogo::colorTarjeta($t['color']),
            ];
        }

        $conf = $c['pantallas'][$pantalla] ?? $c['pantallas']['login'];
        $esLogin = $pantalla === 'login';

        $enlaces = [];
        foreach (['login', 'registro', 'recuperar'] as $p) {
            $enlaces[$p] = $previa
                ? route('admin.portal-cliente.apariencia.vista-previa', array_merge(['pantalla' => $p], $fuentePrevia), false)
                : route("portal.{$p}", [], false);
        }

        return [
            'previa'        => $previa,
            'pantalla'      => $pantalla,
            'css'           => $this->variablesCss($c),
            'fondo'         => ['escritorio' => $escritorio, 'movil' => $movil],
            'logo'          => $this->urlImagen($c['identidad']['logo'], $previa) ?? LogoEmpresa::url(),
            'eslogan'       => $c['identidad']['mostrar_eslogan'] ? $c['identidad']['eslogan'] : '',
            'titulo'        => $esLogin ? $c['identidad']['titulo'] : $conf['titulo'],
            'subtitulo'     => $esLogin ? $c['identidad']['subtitulo'] : $conf['subtitulo'],
            'mensaje'       => [
                'mostrar_titulo'      => $c['mensajes']['mostrar_titulo'] && $titulo !== '',
                'partes'              => $partes,
                'mostrar_descripcion' => $c['mensajes']['mostrar_descripcion'] && $c['mensajes']['descripcion'] !== '',
                'descripcion'         => $c['mensajes']['descripcion'],
                'tamano'              => $c['mensajes']['tamano'],
                'alineacion'          => $c['mensajes']['alineacion'],
            ],
            'mostrar_identidad' => (bool) $conf['mostrar_identidad'],
            'tarjetas'      => $conf['mostrar_tarjetas'] ? $tarjetas : [],
            'enlaces'       => $enlaces,
        ];
    }

    /** Variables CSS. Todo sale de valores ya validados (hex #RRGGBB y enteros acotados). */
    private function variablesCss(array $c): array
    {
        $defaults = AparienciaCatalogo::defaults()['identidad']['colores'];
        $hex = fn (string $clave) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($c['identidad']['colores'][$clave] ?? ''))
            ? $c['identidad']['colores'][$clave]
            : $defaults[$clave];
        $rgb = fn (string $color) => implode(', ', array_map('hexdec', str_split(ltrim($color, '#'), 2)));
        $escala = max(0, min(100, (int) $c['fondo']['degradado'])) / 80;
        $alfa = fn (float $base) => number_format(min(0.98, $base * $escala), 3, '.', '');
        $num = fn ($valor, int $min, int $max) => max($min, min($max, (int) $valor));

        $formulario = $num($c['identidad']['formulario']['opacidad'], 55, 98) / 100;
        $tarjeta = $num($c['identidad']['tarjetas']['opacidad'], 20, 95) / 100;

        return [
            '--azul'           => $hex('primario'),
            '--azul-profundo'  => $hex('profundo'),
            '--azul-noche'     => $hex('noche'),
            '--electrico'      => $hex('electrico'),
            '--cyan'           => $hex('acento'),
            '--azul-rgb'       => $rgb($hex('primario')),
            '--profundo-rgb'   => $rgb($hex('profundo')),
            '--noche-rgb'      => $rgb($hex('noche')),
            '--electrico-rgb'  => $rgb($hex('electrico')),
            '--acento-rgb'     => $rgb($hex('acento')),
            '--velo-1'         => $alfa(self::VELO_ESCRITORIO[0]),
            '--velo-2'         => $alfa(self::VELO_ESCRITORIO[1]),
            '--velo-3'         => $alfa(self::VELO_ESCRITORIO[2]),
            '--velo-4'         => $alfa(self::VELO_ESCRITORIO[3]),
            '--velo-m1'        => $alfa(self::VELO_MOVIL[0]),
            '--velo-m2'        => $alfa(self::VELO_MOVIL[1]),
            '--velo-m3'        => $alfa(self::VELO_MOVIL[2]),
            '--oscurecer'      => number_format($num($c['fondo']['oscurecer'], 0, 80) / 100, 2, '.', ''),
            '--img-opacidad'   => number_format($num($c['fondo']['opacidad_imagen'], 20, 100) / 100, 2, '.', ''),
            '--fondo-pos'      => $num($c['fondo']['posicion_x'], 0, 100) . '% ' . $num($c['fondo']['posicion_y'], 0, 100) . '%',
            '--svc-a1'         => number_format($tarjeta, 2, '.', ''),
            '--svc-a2'         => number_format(max(0.1, $tarjeta - 0.12), 2, '.', ''),
            '--svc-radio'      => $num($c['identidad']['tarjetas']['radio'], 6, 28) . 'px',
            '--card-a1'        => number_format($formulario, 2, '.', ''),
            '--card-a2'        => number_format(max(0.4, $formulario - 0.12), 2, '.', ''),
            '--card-blur'      => $num($c['identidad']['formulario']['desenfoque'], 0, 30) . 'px',
            '--card-radio'     => $num($c['identidad']['formulario']['radio'], 8, 32) . 'px',
        ];
    }

    private function precargarImagenes(array $uuids): void
    {
        $faltan = array_diff($uuids, array_keys($this->imagenesCargadas));
        if ($faltan === []) {
            return;
        }
        try {
            $encontradas = AparienciaImagen::whereIn('uuid', $faltan)->get()->keyBy('uuid');
        } catch (\Throwable $e) {
            $encontradas = collect();
        }
        foreach ($faltan as $uuid) {
            $this->imagenesCargadas[$uuid] = $encontradas->get($uuid);
        }
    }

    /** URL de una imagen, o null si no existe (registro o archivo): la pantalla cae al respaldo/degradado. */
    public function urlImagen(?string $uuid, bool $previa): ?string
    {
        if (! $uuid) {
            return null;
        }
        $this->precargarImagenes([$uuid]);
        $imagen = $this->imagenesCargadas[$uuid] ?? null;
        if (! $imagen || ! $this->imagenes->existeArchivo($imagen)) {
            return null;
        }

        return $previa
            ? route('admin.portal-cliente.apariencia.imagen', ['uuid' => $uuid], false)
            : route('portal.apariencia.imagen', ['uuid' => $uuid], false);
    }

    // ── Panel administrativo ────────────────────────────────────────────────

    public function estadoPanel(User $usuario): array
    {
        $publicada = $this->publicada();
        $borrador = $this->borrador();
        $configPublicada = $this->completar($publicada?->config ?? []);
        $configBorrador = $borrador ? $this->completar($borrador->config) : null;

        $uuids = array_unique(array_merge($this->uuidsDe($configPublicada), $configBorrador ? $this->uuidsDe($configBorrador) : []));
        $imagenes = [];
        foreach (AparienciaImagen::whereIn('uuid', $uuids)->get() as $imagen) {
            $imagenes[$imagen->uuid] = $this->metaImagen($imagen);
        }

        $origen = $borrador?->origen_version_id ? AparienciaVersion::find($borrador->origen_version_id) : null;

        return [
            'publicada' => [
                'id'           => $publicada?->id,
                'numero'       => $publicada?->numero ?? 0,
                'config'       => $configPublicada,
                'published_at' => $publicada?->published_at?->format('d/m/Y H:i'),
                'publicado_por' => $this->nombreUsuario($publicada?->published_by),
            ],
            'borrador' => $borrador ? [
                'revision'            => $borrador->revision,
                'config'              => $configBorrador,
                'updated_at'          => $borrador->updated_at?->format('d/m/Y H:i'),
                'actualizado_por'     => $this->nombreUsuario($borrador->updated_by),
                'base_desactualizada' => (int) $borrador->base_version_id !== (int) $publicada?->id,
                'restaurado_de'       => $origen?->numero,
            ] : null,
            'cambios_pendientes' => $configBorrador ? $this->resumenCambios($configPublicada, $configBorrador) : [],
            'defaults'      => AparienciaCatalogo::defaults(),
            'catalogo'      => AparienciaCatalogo::paraPanel(),
            'imagenes'      => $imagenes,
            'logo_oficial'  => LogoEmpresa::url(),
            'puede'         => [
                'editar'   => $usuario->can('portal-cliente.apariencia.editar'),
                'publicar' => $usuario->can('portal-cliente.apariencia.publicar'),
            ],
        ];
    }

    public function metaImagen(AparienciaImagen $imagen): array
    {
        return [
            'uuid'   => $imagen->uuid,
            'tipo'   => $imagen->tipo,
            'url'    => route('admin.portal-cliente.apariencia.imagen', ['uuid' => $imagen->uuid], false),
            'ancho'  => $imagen->ancho,
            'alto'   => $imagen->alto,
            'peso'   => $imagen->peso,
            'nombre' => $imagen->nombre_original,
            'existe' => $this->imagenes->existeArchivo($imagen),
        ];
    }

    public function versiones(): array
    {
        return AparienciaVersion::whereIn('estado', [AparienciaVersion::PUBLICADA, AparienciaVersion::HISTORIAL])
            ->orderByDesc('numero')
            ->limit(100)
            ->get()
            ->map(fn (AparienciaVersion $v) => [
                'id'            => $v->id,
                'numero'        => $v->numero,
                'actual'        => $v->estado === AparienciaVersion::PUBLICADA,
                'published_at'  => $v->published_at?->format('d/m/Y H:i'),
                'publicado_por' => $this->nombreUsuario($v->published_by),
                'cambios'       => $v->resumen_cambios ?? [],
                'nota'          => $v->nota,
            ])
            ->all();
    }

    /** Config a mostrar en la vista previa según la fuente pedida. */
    public function configParaPrevia(string $fuente, ?int $versionId): array
    {
        return match ($fuente) {
            'borrador' => $this->completar($this->borrador()?->config ?? $this->publicada()?->config ?? []),
            'version'  => $this->completar(
                AparienciaVersion::whereIn('estado', [AparienciaVersion::PUBLICADA, AparienciaVersion::HISTORIAL])
                    ->findOrFail((int) $versionId)->config
            ),
            default    => $this->completar($this->publicada()?->config ?? []),
        };
    }
}
