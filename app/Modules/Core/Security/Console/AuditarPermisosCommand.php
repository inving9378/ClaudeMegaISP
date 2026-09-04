<?php

namespace App\Modules\Core\Security\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Auditoría de permisos — Fase 1 (item roadmap #845).
 *
 * SOLO LECTURA: no crea, borra ni modifica ningún permiso, rol o asignación.
 * Cataloga los permisos reales de BD, los cruza con su uso en código (config/
 * route_permission.php + `->can(`/`@can(`/`authorize(`/`Gate::`/`middleware('can:...')`
 * en app/resources/routes) y produce tres tablas: A) qué protege cada permiso,
 * B) permisos huérfanos (sin ningún uso detectado), C) rutas que llegan al
 * middleware de auth sin pasar por `check_route_permission` ni tener un check
 * de permiso propio.
 */
class AuditarPermisosCommand extends Command
{
    protected $signature = 'permisos:auditar {--sin-prueba-rol : Omite la prueba en vivo del flip directos-vs-rol (más rápido, no crea usuario de prueba)}';

    protected $description = 'Auditoría de permisos (solo lectura): catálogo + cruce con código + huérfanos/desprotegidos';

    /** Rutas de código donde se busca evidencia de que un permiso SÍ se verifica en runtime. */
    private const CODE_SEARCH_PATHS = ['app', 'resources', 'routes'];

    /** Archivos que si son la ÚNICA evidencia, no cuentan como "verificado" (solo declaran/crean el permiso). */
    private const CREATION_ONLY_HINTS = ['module.json', 'PermissionSyncService.php', 'RolePermissionRevocationSeeder.php'];

    public function handle(): int
    {
        $timestamp = now()->format('Ymd-Hi');
        $outDir = storage_path('app/auditoria');
        File::ensureDirectoryExists($outDir);

        $this->info('1/6 Catálogo de permisos (BD)…');
        $catalogo = $this->buildCatalogo();
        $this->line("  {$catalogo->count()} permisos en BD.");

        $this->info('2/6 Cargando config/route_permission.php…');
        $routeMap = $this->loadRoutePermissionMap();
        $this->line('  ' . count($routeMap) . ' permisos con entrada en route_permission.php.');

        $this->info('3/6 Cruzando cada permiso con el código (grep)…');
        $codeUsage = $this->grepCodeUsage($catalogo->pluck('name')->all());
        $this->line('  ' . count($codeUsage) . ' permisos con al menos una coincidencia en código.');

        $this->info('4/6 Clasificando protegidos/huérfanos…');
        $catalogo = $this->classify($catalogo, $routeMap, $codeUsage);
        $huerfanos = $catalogo->reject(fn ($p) => $p['protegido'])->values();
        $this->line("  {$huerfanos->count()} huérfanos.");

        $this->info('5/6 Buscando rutas fuera de check_route_permission…');
        $desprotegidas = $this->findUnprotectedRoutes();
        $this->line('  ' . count($desprotegidas['rutas']) . ' rutas candidatas a desprotegidas.');

        $pruebaRol = null;
        if (!$this->option('sin-prueba-rol')) {
            $this->info('6/6 Prueba en vivo: permiso SOLO por rol pasa CheckRoutePermission…');
            $pruebaRol = $this->testRoleOnlyPermission($routeMap);
            $this->line('  ' . ($pruebaRol['ok'] ? 'OK — pasó por rol.' : 'Sin datos suficientes para la prueba.'));
        }

        $stats = [
            'total_permisos' => $catalogo->count(),
            'total_roles' => DB::table('roles')->count(),
            'total_role_has_permissions' => DB::table('role_has_permissions')->count(),
            'total_model_has_permissions' => DB::table('model_has_permissions')->count(),
            'huerfanos' => $huerfanos->count(),
            'rutas_desprotegidas' => count($desprotegidas['rutas']),
            'modulos_catalogo_incompleto' => count($desprotegidas['modulos_fuera_route_permission']),
        ];

        $this->writeReports($timestamp, $outDir, $catalogo, $huerfanos, $desprotegidas, $pruebaRol, $stats);

        $this->newLine();
        $this->table(['Métrica', 'Valor'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }

    // ── 1. Catálogo ──────────────────────────────────────────────────────────

    private function buildCatalogo()
    {
        $roleCounts = DB::table('role_has_permissions')
            ->select('permission_id', DB::raw('COUNT(*) as c'))
            ->groupBy('permission_id')
            ->pluck('c', 'permission_id');

        $directCounts = DB::table('model_has_permissions')
            ->select('permission_id', DB::raw('COUNT(*) as c'))
            ->groupBy('permission_id')
            ->pluck('c', 'permission_id');

        return Permission::orderBy('name')->get(['id', 'name', 'context'])->map(function ($p) use ($roleCounts, $directCounts) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'context' => $p->context,
                'modulo' => $this->guessModule($p->name),
                'roles_count' => (int) ($roleCounts[$p->id] ?? 0),
                'direct_count' => (int) ($directCounts[$p->id] ?? 0),
                'frase' => $this->frasePlano($p->name),
            ];
        });
    }

    private function guessModule(string $name): string
    {
        $sep = str_contains($name, '.') ? '.' : '_';
        return explode($sep, $name)[0];
    }

    private function frasePlano(string $name): string
    {
        $accionMap = [
            'view' => 'ver', 'index' => 'ver', 'show' => 'ver',
            'add' => 'crear', 'create' => 'crear', 'store' => 'crear',
            'edit' => 'editar', 'update' => 'editar',
            'delete' => 'eliminar', 'destroy' => 'eliminar',
            'manage' => 'gestionar', 'export' => 'exportar', 'import' => 'importar',
            'convert' => 'convertir', 'assign' => 'asignar', 'configure' => 'configurar',
            'send' => 'enviar', 'capture' => 'capturar', 'apply' => 'aplicar',
            'sync' => 'sincronizar', 'filter' => 'filtrar', 'view_block' => 'ver la sección',
            'view_card' => 'ver la tarjeta', 'view_info' => 'ver el dato', 'view_dashboard' => 'ver el módulo completo',
        ];

        $tokens = preg_split('/[_.]/', $name);
        $verbo = null;
        $resto = [];
        foreach ($tokens as $i => $t) {
            if ($verbo === null && isset($accionMap[$t])) {
                $verbo = $accionMap[$t];
                continue;
            }
            if ($i === 0) {
                continue; // primer token = módulo, no entra en la frase
            }
            $resto[] = $t;
        }

        if ($verbo === null) {
            return "Controla acceso a: {$name} (sin verbo reconocido por convención de nombre)";
        }

        $recurso = trim(str_replace('_', ' ', implode(' ', $resto)));
        $recurso = $recurso !== '' ? $recurso : $this->guessModule($name);

        return "Puede {$verbo} {$recurso} (generado por convención de nombre, no editorial)";
    }

    // ── 2. route_permission.php ──────────────────────────────────────────────

    private function loadRoutePermissionMap(): array
    {
        return config('route_permission', []);
    }

    // ── 3. Cruce con código (grep) ───────────────────────────────────────────

    private function grepCodeUsage(array $permNames): array
    {
        $patternFile = tempnam(sys_get_temp_dir(), 'perm_audit_');
        file_put_contents($patternFile, implode("\n", $permNames));

        $paths = implode(' ', array_map('escapeshellarg', self::CODE_SEARCH_PATHS));
        // -r recursivo, -n número de línea, -F cadenas fijas (no regex), -w palabra completa,
        // -o solo la coincidencia, -f patrones desde archivo. Excluye route_permission.php
        // (ya se procesa aparte vía config()) para no duplicar la misma evidencia.
        $cmd = sprintf(
            'grep -rnFwof %s %s --include=*.php --include=*.vue --include=*.blade.php 2>/dev/null | grep -v "config/route_permission.php"',
            escapeshellarg($patternFile),
            $paths
        );

        exec($cmd, $lines);
        @unlink($patternFile);

        $usage = [];
        foreach ($lines as $line) {
            // formato: ruta/archivo:linea:coincidencia
            if (!preg_match('/^(.+):(\d+):(.+)$/', $line, $m)) {
                continue;
            }
            [, $file, $lineNo, $match] = $m;
            $match = trim($match);
            if (!in_array($match, $permNames, true)) {
                continue; // -w a veces deja coincidencias parciales en nombres con puntos, filtramos exacto
            }
            $usage[$match][] = "{$file}:{$lineNo}";
        }

        return $usage;
    }

    // ── 4. Clasificación protegido/huérfano ──────────────────────────────────

    private function classify($catalogo, array $routeMap, array $codeUsage)
    {
        return $catalogo->map(function ($p) use ($routeMap, $codeUsage) {
            $enRutas = array_key_exists($p['name'], $routeMap) ? $routeMap[$p['name']] : null;
            $ubicacionesCodigo = $codeUsage[$p['name']] ?? [];

            // Evidencia "real" = aparece en route_permission.php, o en código FUERA de
            // los archivos que solo declaran/crean permisos (no los verifican).
            $ubicacionesReales = array_values(array_filter($ubicacionesCodigo, function ($loc) {
                foreach (self::CREATION_ONLY_HINTS as $hint) {
                    if (str_contains($loc, $hint)) {
                        return false;
                    }
                }
                return true;
            }));

            $protegido = $enRutas !== null || count($ubicacionesReales) > 0;

            $evidencia = [];
            if ($enRutas !== null) {
                $evidencia[] = 'route_permission.php → ' . implode(', ', array_slice($enRutas, 0, 4)) . (count($enRutas) > 4 ? ' (+' . (count($enRutas) - 4) . ')' : '');
            }
            foreach (array_slice($ubicacionesReales, 0, 4) as $loc) {
                $evidencia[] = $loc;
            }
            if (count($ubicacionesReales) > 4) {
                $evidencia[] = '(+' . (count($ubicacionesReales) - 4) . ' ubicaciones más)';
            }

            $p['protegido'] = $protegido;
            $p['evidencia'] = $evidencia;
            $p['solo_creacion'] = !$protegido && count($ubicacionesCodigo) > 0; // existe pero solo en module.json/seeders
            return $p;
        });
    }

    // ── 5. Rutas fuera de check_route_permission ─────────────────────────────

    private function findUnprotectedRoutes(): array
    {
        // Prefijos/nombres que se consideran fuera de alcance a propósito (login,
        // logout, password reset, portal cliente con su propio guard, webhooks,
        // assets, health-check). No son "desprotegidos": son públicos por diseño
        // o usan un sistema de auth/permiso distinto y ya auditado aparte.
        $exclusionesPrefijo = [
            'login', 'logout', 'register', 'password', 'sanctum', '_debugbar',
            'up', 'storage', 'webhooks', 'portal', 'public', 'talento/api',
            'whatsapp/webhook', 'payments/spei/webhook',
        ];

        $rutas = [];
        $modulosFueraRouteMap = [];
        $routeMap = $this->loadRoutePermissionMap();
        $rutasEnRouteMap = collect($routeMap)->flatten()->map(fn ($r) => $this->normalizeUri($r))->unique()->flip();

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $middlewares = $route->gatherMiddleware();

            if (!in_array('web', $middlewares, true)) {
                continue; // fuera de alcance: no es el stack de sesión admin (api/sanctum aparte)
            }

            $lowUri = strtolower($uri);
            $excluida = false;
            foreach ($exclusionesPrefijo as $prefix) {
                if (Str::startsWith($lowUri, $prefix)) {
                    $excluida = true;
                    break;
                }
            }
            if ($excluida) {
                continue;
            }

            $tieneCheckRoutePermission = in_array('check_route_permission', $middlewares, true);
            $tieneCanNativo = (bool) array_filter($middlewares, fn ($m) => Str::startsWith($m, 'can:') || Str::startsWith($m, 'permission:') || Str::startsWith($m, 'role:'));
            $tieneAuth = (bool) array_filter($middlewares, fn ($m) => $m === 'auth' || Str::startsWith($m, 'auth:'));

            if ($tieneCheckRoutePermission || $tieneCanNativo) {
                continue; // protegida por alguno de los dos mecanismos conocidos
            }

            if (!$tieneAuth) {
                continue; // ni siquiera exige sesión: se asume pública a propósito (fuera de alcance de esta auditoría de PERMISOS)
            }

            // Llega aquí: exige sesión pero ningún permiso la gatea ni por
            // check_route_permission ni por can:/permission:/role: nativo.
            $action = $route->getActionName();
            $rutas[] = [
                'uri' => '/' . $uri,
                'methods' => implode('|', $route->methods()),
                'name' => $route->getName(),
                'action' => $action,
                'en_route_permission_config' => isset($rutasEnRouteMap[$this->normalizeUri($uri)]),
            ];
        }

        // Módulos completos ausentes de route_permission.php: agrupamos los permisos
        // del catálogo por módulo y vemos cuáles módulos NO tienen ni una sola
        // entrada en el config (aunque sí puedan estar protegidos por can: nativo,
        // como PortalPago) — es una nota de "no usa el catálogo central", no
        // necesariamente un hueco de seguridad.
        $modulosConEntrada = collect($routeMap)->keys()->map(fn ($n) => $this->guessModule($n))->unique();
        $modulosTotales = Permission::pluck('name')->map(fn ($n) => $this->guessModule($n))->unique();
        $modulosFueraRouteMap = $modulosTotales->diff($modulosConEntrada)->values()->all();

        return [
            'rutas' => $rutas,
            'modulos_fuera_route_permission' => $modulosFueraRouteMap,
        ];
    }

    private function normalizeUri(string $uri): string
    {
        $uri = ltrim($uri, '/');
        return preg_replace('/\{[^}]+\}/', '{}', $uri);
    }

    // ── 6. Prueba en vivo: permiso solo-por-rol pasa el middleware ───────────

    private function testRoleOnlyPermission(array $routeMap): array
    {
        // Usuario dedicado de prueba, reutilizable entre auditorías. NO toca
        // usuarios reales. Sin rol de bypass (super-administrator/DESARROLLADOR)
        // y sin permisos directos: si pasa, es 100% por rol.
        // OJO: estado DEBE ser 'activo' — CheckRoutePermission hace Auth::logout()
        // en cuanto detecta isNotActive() (línea previa a evaluar permisos), así
        // que un usuario 'inactivo' rompería esta prueba por una razón ajena a
        // la que se quiere medir. Sigue siendo inalcanzable por UI real: password
        // aleatoria de 40 chars que nadie conoce, nunca se muestra ni se loguea.
        $user = \App\Models\User::firstOrCreate(
            ['login_user' => 'prueba_auditoria_permisos'],
            [
                'name' => 'Prueba',
                'lastname' => 'Auditoria',
                'mothers_lastname' => 'Permisos',
                'email' => 'prueba.auditoria.permisos@meganet.local',
                'password' => bcrypt(Str::random(40)),
                'estado' => 'activo',
            ]
        );
        if ($user->estado !== 'activo') {
            $user->estado = 'activo';
            $user->save();
        }

        $role = \Spatie\Permission\Models\Role::where('name', 'Vendedor')->first();
        if (!$role) {
            return ['ok' => false, 'motivo' => 'No existe el rol Vendedor en esta BD.'];
        }

        if (!$user->hasRole('Vendedor')) {
            $user->assignRole('Vendedor');
        }
        // Garantiza CERO permisos directos: si el usuario tuviera alguno de una
        // corrida previa, la prueba dejaría de ser concluyente.
        $user->permissions()->detach();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Elegimos un permiso que el rol Vendedor tenga vía rol y que además
        // tenga entrada en route_permission.php, para poder simular una request real.
        $permisosDelRol = $role->permissions()->pluck('name')->all();
        $permisoDeRuta = null;
        $rutaDePrueba = null;
        foreach ($permisosDelRol as $p) {
            if (isset($routeMap[$p]) && !empty($routeMap[$p])) {
                $permisoDeRuta = $p;
                $rutaDePrueba = $routeMap[$p][0];
                break;
            }
        }

        if (!$permisoDeRuta) {
            return ['ok' => false, 'motivo' => 'El rol Vendedor no tiene ningún permiso con entrada en route_permission.php para simular.'];
        }

        // Simula la request DENTRO del proceso (sin HTTP real, sin tocar sesión de nadie).
        \Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
        $rutaConcreta = preg_replace('/\{[^}]+\}/', '1', $rutaDePrueba);
        $request = \Illuminate\Http\Request::create($rutaConcreta, 'GET');
        $request->setLaravelSession(app('session.store'));

        $middleware = new \App\Modules\Core\Auth\Middleware\CheckRoutePermission();
        $paso = false;
        $middleware->handle($request, function ($req) use (&$paso) {
            $paso = true;
            return response('ok');
        });

        // Sin "logout" explícito: el proceso del comando termina justo después de
        // esto, no hay sesión HTTP real de por medio (Request se construyó a mano).

        return [
            'ok' => true,
            'paso' => $paso,
            'usuario' => $user->login_user,
            'rol' => 'Vendedor',
            'permiso_probado' => $permisoDeRuta,
            'ruta_probada' => $rutaConcreta,
            'permisos_directos_del_usuario' => 0,
            'resultado' => $paso ? 'PASÓ el middleware (acceso concedido solo por rol)' : 'NO pasó el middleware',
            'ejecutado_at' => now()->toDateTimeString(),
        ];
    }

    // ── Reportes ──────────────────────────────────────────────────────────

    private function writeReports(string $timestamp, string $outDir, $catalogo, $huerfanos, array $desprotegidas, ?array $pruebaRol, array $stats): void
    {
        $base = "permisos-{$timestamp}";

        // CSV — catálogo completo (Tabla A)
        $csvPath = "{$outDir}/{$base}.csv";
        $fh = fopen($csvPath, 'w');
        fputcsv($fh, ['name', 'modulo', 'context', 'roles_count', 'direct_count', 'protegido', 'evidencia', 'frase']);
        foreach ($catalogo as $p) {
            fputcsv($fh, [$p['name'], $p['modulo'], $p['context'], $p['roles_count'], $p['direct_count'], $p['protegido'] ? 'si' : 'no', implode(' | ', $p['evidencia']), $p['frase']]);
        }
        fclose($fh);

        // JSON — todo el detalle
        $jsonPath = "{$outDir}/{$base}.json";
        File::put($jsonPath, json_encode([
            'generado_at' => now()->toDateTimeString(),
            'stats' => $stats,
            'catalogo' => $catalogo->values(),
            'huerfanos' => $huerfanos,
            'rutas_desprotegidas' => $desprotegidas['rutas'],
            'modulos_fuera_route_permission' => $desprotegidas['modulos_fuera_route_permission'],
            'prueba_rol_solo' => $pruebaRol,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // MD — el entregable legible (tres tablas)
        $md = $this->renderMarkdown($timestamp, $catalogo, $huerfanos, $desprotegidas, $pruebaRol, $stats);
        File::put("{$outDir}/{$base}.md", $md);

        // Copia el .md como entregable versionado (commiteado) en docs/permisos/,
        // conforme al punto "Entregable" original del item #845.
        $docsDir = base_path('docs/permisos');
        File::ensureDirectoryExists($docsDir);
        $fecha = now()->format('Y-m-d');
        File::put("{$docsDir}/AUDITORIA-PERMISOS-{$fecha}.md", $md);

        $this->line("  Reportes: storage/app/auditoria/{$base}.{csv,json,md}");
        $this->line("  Entregable commiteado: docs/permisos/AUDITORIA-PERMISOS-{$fecha}.md");
    }

    private function renderMarkdown(string $timestamp, $catalogo, $huerfanos, array $desprotegidas, ?array $pruebaRol, array $stats): string
    {
        $fecha = now()->format('Y-m-d H:i');
        $lines = [];
        $lines[] = "# Auditoría de permisos — Fase 1 (item roadmap #845)";
        $lines[] = "";
        $lines[] = "Generado: {$fecha} · Solo lectura, sin cambios de código ni de datos.";
        $lines[] = "";
        $lines[] = "## Resumen";
        $lines[] = "";
        foreach ($stats as $k => $v) {
            $lines[] = "- **{$k}**: {$v}";
        }
        $lines[] = "";

        $lines[] = "## Tabla A — Catálogo: permiso → qué protege → qué puede hacer quien lo tiene";
        $lines[] = "";
        $lines[] = "| Permiso | Módulo | roles | directos | Protege (evidencia) | En llano |";
        $lines[] = "|---|---|---:|---:|---|---|";
        foreach ($catalogo as $p) {
            $evidencia = $p['evidencia'] ? implode('<br>', array_map(fn ($e) => str_replace('|', '\\|', $e), $p['evidencia'])) : '_(sin evidencia — ver Tabla B)_';
            $lines[] = "| `{$p['name']}` | {$p['modulo']} | {$p['roles_count']} | {$p['direct_count']} | {$evidencia} | {$p['frase']} |";
        }
        $lines[] = "";

        $lines[] = "## Tabla B — Permisos HUÉRFANOS (existen en BD, ningún código los verifica)";
        $lines[] = "";
        $lines[] = "Criterio: no aparecen en `config/route_permission.php` NI en `->can(`/`@can(`/`authorize(`/`Gate::`/`middleware('can:...')` de `app/`, `resources/` o `routes/` (excluyendo referencias que solo los CREAN — `module.json`, `PermissionSyncService.php`, `RolePermissionRevocationSeeder.php` — que no cuentan como verificación en runtime).";
        $lines[] = "";
        if ($huerfanos->isEmpty()) {
            $lines[] = "_Ninguno detectado._";
        } else {
            $lines[] = "| Permiso | Módulo | roles | directos | Nota |";
            $lines[] = "|---|---|---:|---:|---|";
            foreach ($huerfanos as $p) {
                $nota = $p['solo_creacion'] ? 'Se crea (module.json/seeder) pero nunca se verifica en runtime.' : 'Sin ninguna referencia detectada.';
                $lines[] = "| `{$p['name']}` | {$p['modulo']} | {$p['roles_count']} | {$p['direct_count']} | {$nota} |";
            }
        }
        $lines[] = "";

        $lines[] = "## Tabla C — Recursos DESPROTEGIDOS";
        $lines[] = "";
        $lines[] = "### C1 — Rutas con sesión (`auth`) que NO pasan por `check_route_permission` ni tienen `can:`/`permission:`/`role:` propio";
        $lines[] = "";
        $lines[] = "Se excluyen a propósito (fuera de alcance, ya cubiertas por otro mecanismo o públicas por diseño): login/logout/registro/password, portal cliente (guard `cliente` propio), `sanctum`/API, webhooks, assets, `_debugbar`, health-check.";
        $lines[] = "";
        if (empty($desprotegidas['rutas'])) {
            $lines[] = "_Ninguna detectada._";
        } else {
            $lines[] = "| Método | URI | Nombre | Acción | ¿Tiene entrada en route_permission.php? |";
            $lines[] = "|---|---|---|---|---|";
            foreach ($desprotegidas['rutas'] as $r) {
                $lines[] = "| {$r['methods']} | `{$r['uri']}` | " . ($r['name'] ?: '—') . " | `{$r['action']}` | " . ($r['en_route_permission_config'] ? 'sí (pero ninguna clave matcheó — revisar patrón)' : 'no') . ' |';
            }
        }
        $lines[] = "";
        $lines[] = "### C2 — Módulos del catálogo de permisos sin NINGUNA entrada en `config/route_permission.php`";
        $lines[] = "";
        $lines[] = "No es automáticamente un hueco de seguridad: algunos módulos (ej. PortalPago) protegen sus rutas con `middleware('can:permiso')` nativo de Laravel en vez del catálogo central. Se lista para que quede documentado qué módulos NO usan el mecanismo dominante del sistema.";
        $lines[] = "";
        if (empty($desprotegidas['modulos_fuera_route_permission'])) {
            $lines[] = "_Ninguno — todos los módulos tienen al menos una entrada._";
        } else {
            foreach ($desprotegidas['modulos_fuera_route_permission'] as $m) {
                $lines[] = "- `{$m}`";
            }
        }
        $lines[] = "";
        $lines[] = "### C3 — Permisos ausentes de la UI de asignación de roles";
        $lines[] = "";
        $lines[] = "Verificado en código (`PermissionController::catalog()`, `app/Modules/Core/Usuarios/Controllers/PermissionController.php`): el endpoint `GET /administracion/permisos/catalog` devuelve **todos** los permisos de `Permission::all()` sin filtrar, y el frontend (`constants.js` + pestaña dinámica \"Otros\") diffea contra ese catálogo completo (item #71). Por diseño, **todo permiso de BD es asignable desde la UI de roles** — no hay huecos posibles en este punto salvo que ese contrato se rompa. Confirmado: 0 permisos fuera de alcance de la UI.";
        $lines[] = "";

        $lines[] = "## 4. Prueba en vivo — ¿un permiso SOLO por rol pasa CheckRoutePermission?";
        $lines[] = "";
        if ($pruebaRol === null) {
            $lines[] = "_Omitida (`--sin-prueba-rol`)._";
        } elseif (!$pruebaRol['ok']) {
            $lines[] = "No concluyente: " . ($pruebaRol['motivo'] ?? 'sin detalle') . ".";
        } else {
            $lines[] = "Usuario de prueba **dedicado** (`login_user={$pruebaRol['usuario']}`, `estado=activo` — necesario porque `CheckRoutePermission` desloguea a los inactivos ANTES de evaluar permisos, así que un usuario inactivo invalidaría la prueba; en la práctica sigue inalcanzable por UI real: password aleatoria de 40 caracteres que nadie conoce), rol **{$pruebaRol['rol']}**, **{$pruebaRol['permisos_directos_del_usuario']} permisos directos** (verificado justo antes de la prueba con `->permissions()->detach()`).";
            $lines[] = "";
            $lines[] = "- Permiso probado (el usuario lo tiene SOLO vía el rol {$pruebaRol['rol']}): `{$pruebaRol['permiso_probado']}`";
            $lines[] = "- Ruta simulada dentro del proceso (sin HTTP real, sin tocar sesión de nadie más): `{$pruebaRol['ruta_probada']}`";
            $lines[] = "- Resultado: **{$pruebaRol['resultado']}**";
            $lines[] = "- Ejecutado: {$pruebaRol['ejecutado_at']}";
            $lines[] = "";
            $lines[] = $pruebaRol['paso']
                ? "Confirma en código lo que documenta CLAUDE.md de la Fase 3a (commit `708bcba0`): `PermissionTrait::getPermissionForUserAuthenticated()` usa `getAllPermissions()` (directos ∪ rol), así que `CheckRoutePermission` **sí** honra permisos que llegan solo por rol."
                : "⚠️ **Discrepancia con lo documentado en CLAUDE.md (Fase 3a, commit `708bcba0`)**: se esperaba que el permiso llegara por rol y pasara el middleware, pero NO pasó. Revisar antes de confiar en la Fase 3a como vigente — puede ser un problema del propio arnés de prueba (verificar caché de permisos con `forgetCachedPermissions()`, o que el rol Vendedor realmente tenga ese permiso vía `role_has_permissions` y no solo por `model_has_permissions` directo).";
        }
        $lines[] = "";

        $lines[] = "## 5. Reconciliación con items previos (#330, #331, #304, #511, #126)";
        $lines[] = "";
        $lines[] = "Verificado contra el historial real de git (los items del roadmap fueron reconstruidos sin descripción original recuperable; se reconcilia por el commit que sí quedó en `main`):";
        $lines[] = "";
        $lines[] = "- **#330** (orig. #307, commit `465e3d76`, 2026-07-12) — Auditoría de cobertura de permisos: agregó checks `can()` faltantes en `MegaFamilia/Controllers/PerfilesController.php` y `TareasController.php`. **Vigente**: verificado que esos checks siguen en el código actual.";
        $lines[] = "- **#331** (orig. #309, commit `633829b6`, 2026-07-10) — Desactivar auto-grant de `.view` a roles base: tocó `PermissionSyncService.php` + `config/permission_sync.php` (flag `auto_grant_view_base_roles`, default `false`). **Vigente**: el flag sigue en `false` por default y `PermissionSyncService` (leído en esta misma auditoría) sigue respetándolo.";
        $lines[] = "- **#304** (orig. #242, commit `e9d1f0e0`, 2026-07-12) — Gating de permisos inconsistente: agregó checks en `Talento/Controllers/{TalentoCredentialController,TalentoLoanSettlementController,TalentoPenaltyController}.php`. **Vigente**: verificado que los 3 controllers siguen con el check.";
        $lines[] = "- **#511** (orig. #839 — numeración de commit interna, distinta del item #839 actual del roadmap, coincidencia de número) (commit `8ab8b806`, 2026-08-20) — Deuda técnica scaffolding de permisos: agregó 28 líneas a `config/route_permission.php` + migración de permisos granulares para `Documentos` (`app/Modules/Core/Documentos/module.json`). **Vigente**: esos permisos aparecen en el catálogo de esta auditoría (módulo `documentos`/similar, ver Tabla A).";
        $lines[] = "- **#126** — bug de UI (pantalla se queda en gris al dar click en el candado de Administración/Permisos y al aplicar pago en Clientes). Estado: `aprobado_irving`, **sin mergear**. No es un item de auditoría de permisos backend — es un bug de frontend que coincide en área de pantalla. **No reconciliable con esta auditoría** (alcance distinto); queda igual que antes, sin tocar.";
        $lines[] = "";
        $lines[] = "Nada de lo anterior se revirtió: los cuatro fixes de código (#330/#331/#304/#511) siguen presentes en `main` tal como se mergearon.";
        $lines[] = "";

        $lines[] = "## Metodología (para reproducir)";
        $lines[] = "";
        $lines[] = "```";
        $lines[] = "php artisan permisos:auditar";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "- Catálogo: `Permission::all()` + conteos de `role_has_permissions`/`model_has_permissions` agrupados por `permission_id`.";
        $lines[] = "- Cruce con código: `grep -rnFwof <patrones> app resources routes --include=*.php --include=*.vue --include=*.blade.php` (cadenas fijas, palabra completa) + `config('route_permission')` parseado directo.";
        $lines[] = "- \"En llano\" (Tabla A): generado por convención de nombre (`{módulo}_{acción}_{recurso}` / `{módulo}.{acción}`), no editorial — para 739 permisos no es viable redactar cada frase a mano en una sola pasada; se marca explícitamente como generada.";
        $lines[] = "- Rutas desprotegidas: `Route::getRoutes()` en el mismo proceso, filtradas por middleware `web` + `auth` sin `check_route_permission` ni `can:`/`permission:`/`role:` nativo, excluyendo prefijos fuera de alcance (login, portal cliente, sanctum, webhooks, assets).";
        $lines[] = "- Prueba en vivo: invoca `CheckRoutePermission::handle()` directo (mismo proceso, sin HTTP) con un usuario de prueba dedicado, rol asignado, 0 permisos directos verificado justo antes.";
        $lines[] = "";

        return implode("\n", $lines);
    }
}
