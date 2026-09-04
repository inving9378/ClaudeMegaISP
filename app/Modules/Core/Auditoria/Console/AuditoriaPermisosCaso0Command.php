<?php

namespace App\Modules\Core\Auditoria\Console;

use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roadmap #846 (Fase 2 de la auditoría de permisos, sub-item de #841).
 *
 * Caso 0 reproducible reportado en PRODUCCIÓN: la usuaria ALONDRA NIMY entra a
 * Inventario -> Almacenes y ve "No hay elementos para mostrar" aunque el menú
 * y la ruta la dejan pasar. Este comando es SOLO LECTURA sobre datos reales:
 * lo único que escribe es un usuario/rol de PRUEBA (prefijo
 * prueba_auditoria_caso0_alondra), nunca toca ALONDRA ni ningún dato real.
 *
 * No repara nada — diagnostica y reporta (decisión q1 del item, opción 1).
 */
class AuditoriaPermisosCaso0Command extends Command
{
    protected $signature = 'auditoria:permisos:caso0 {--json : Además del texto, escribe un JSON en storage/app/auditoria}';
    protected $description = 'Reproduce el Caso 0 (Alondra Nimy / Inventario > Almacenes) en dev y reporta la causa raíz';

    private const TEST_LOGIN = 'prueba_auditoria_caso0_alondra';
    private const TEST_ROLE = 'Almacen';
    private const VIEW_PERMISSION = 'inventory_store_view_inventory_store';
    private const ADD_PERMISSION = 'inventory_store_add_inventory_store';

    public function handle(): int
    {
        $admin = User::where('login_user', 'admin')->first() ?? User::orderBy('id')->first();
        $testUser = $this->ensureTestUser();

        $this->info('=== Caso 0: Alondra Nimy / Inventario > Almacenes (roadmap #846) ===');
        $this->line("Usuario de prueba: {$testUser->login_user} (id={$testUser->id}, rol=" . self::TEST_ROLE . ')');
        $this->line('⚠️  DEV no tiene a ALONDRA NIMY (los ids/usuarios de dev y prod NO coinciden). Este usuario');
        $this->line('    de prueba replica el síntoma exacto reportado: permiso de VISTA sin ser responsable de');
        $this->line('    ningún almacén. No representa necesariamente el rol/asignación real de Alondra en prod.');
        $this->newLine();

        $comparativa = $this->compararUsuarios($admin, $testUser);
        $this->tablaComparativa($comparativa);

        $this->newLine();
        $this->causaRaiz();

        $this->newLine();
        $this->botonAgregar($testUser);

        $this->newLine();
        $this->menuLateral();

        $this->newLine();
        $otrosCasos = $this->buscarOtrosCasos();
        $this->reportarOtrosCasos($otrosCasos);

        if ($this->option('json')) {
            $this->escribirJson($comparativa, $otrosCasos);
        }

        return self::SUCCESS;
    }

    private function ensureTestUser(): User
    {
        $role = Role::firstOrCreate(['name' => self::TEST_ROLE, 'guard_name' => 'web']);

        $user = User::where('login_user', self::TEST_LOGIN)->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Prueba',
                'last_name' => 'Auditoria Caso0',
                'login_user' => self::TEST_LOGIN,
                'email' => self::TEST_LOGIN . '@dev.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'estado' => 'activo',
            ]);
        }

        if (!$user->hasRole(self::TEST_ROLE)) {
            $user->assignRole($role);
        }

        // Permiso DIRECTO de vista (no vía rol): replica el síntoma reportado
        // -- "el menú la deja pasar, la ruta la deja pasar" -- sin otorgarle
        // isAdmin()/isSuperAdmin() ni hacerla responsable de ningún almacén.
        if (!$user->hasDirectPermission(self::VIEW_PERMISSION)) {
            $permission = Permission::firstOrCreate(['name' => self::VIEW_PERMISSION, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        // Nunca debe ser "responsable" (inventory_stores.user_id) de un almacén real.
        InventoryStore::where('user_id', $user->id)->update(['user_id' => null]);

        return $user->fresh();
    }

    private function compararUsuarios(User $admin, User $testUser): array
    {
        $helper = app(\App\Http\HelpersModule\module\inventory\inventorystore\InventoryStoreDatatableHelper::class);

        Auth::login($admin);
        $filasAdmin = $helper->count();

        Auth::login($testUser);
        $filasPrueba = $helper->count();
        $menuVisible = Auth::user()->canAny([
            'inventory_view_inventory', 'inventory_item_view_inventory_item',
            'inventory_item_type_view_inventory_item_type', 'inventory_movement_view_inventory_movement',
            'inventory_store_view_inventory_store', 'inventory_item_custom_model_view_inventory_item_custom_model',
        ]);
        $submenuAlmacenesVisible = Auth::user()->can(self::VIEW_PERMISSION);
        $puedeAgregar = Auth::user()->can(self::ADD_PERMISSION);
        $esResponsableDeAlgunAlmacen = InventoryStore::where('user_id', $testUser->id)->exists();

        Auth::logout();

        return [
            'filas_admin' => $filasAdmin,
            'filas_prueba' => $filasPrueba,
            'menu_visible_prueba' => $menuVisible,
            'submenu_almacenes_visible_prueba' => $submenuAlmacenesVisible,
            'puede_agregar_prueba' => $puedeAgregar,
            'es_responsable_prueba' => $esResponsableDeAlgunAlmacen,
        ];
    }

    private function tablaComparativa(array $c): void
    {
        $this->table(
            ['Punto de control', 'Admin', 'Prueba (rol ' . self::TEST_ROLE . ')'],
            [
                ['Menú "Inventario" visible', 'sí (bypass isAdmin)', $c['menu_visible_prueba'] ? 'sí (por permiso)' : 'NO'],
                ['Submenú "Almacenes > Listar" visible', 'sí (bypass isAdmin)', $c['submenu_almacenes_visible_prueba'] ? 'sí (por permiso)' : 'NO'],
                ['Ruta GET /inventory/inventory_store', 'pasa (isAdmin)', 'pasa (permiso ' . self::VIEW_PERMISSION . ')'],
                ['Es "responsable" de algún almacén (user_id)', 'n/a (bypass)', $c['es_responsable_prueba'] ? 'sí' : 'NO'],
                ['Filas devueltas por table()/count()', (string) $c['filas_admin'], (string) $c['filas_prueba']],
                ['Permiso ' . self::ADD_PERMISSION, 'sí (bypass isAdmin)', $c['puede_agregar_prueba'] ? 'sí' : 'NO'],
            ]
        );
    }

    private function causaRaiz(): void
    {
        $this->info('--- 1) y 2) Permiso que protege la pantalla + por qué la consulta da 0 filas ---');
        $this->line('Permiso que protege menú Y ruta: `' . self::VIEW_PERMISSION . '` (config/route_permission.php:1072,');
        $this->line('  gatea /inventory/inventory_store y /inventory/inventory_store/table; el mismo permiso gatea el');
        $this->line('  link "Almacenes > Listar" del sidebar en resources/views/module-sidebar/inventario.blade.php:21).');
        $this->line('  Ese permiso NO tiene relación con qué FILAS se ven -- solo con si la pantalla carga.');
        $this->newLine();
        $this->line('Por qué da 0 filas: NO es un scope del modelo (InventoryStore no declara scopeUserId ni global scope).');
        $this->line('  Es un `where` a mano dentro del datatable helper, repetido en count()/ordering_query()/');
        $this->line('  searching_query()/filtering_query():');
        $this->line('  app/Http/HelpersModule/module/inventory/inventorystore/InventoryStoreDatatableHelper.php:22-77');
        $this->line("      if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {");
        $this->line("          \$query->where('user_id', auth()->user()->id);");
        $this->line('      }');
        $this->line('  `inventory_stores.user_id` es el "responsable" del almacén (columna FK única, 1 usuario por');
        $this->line('  almacén, sin tabla pivote). isAdmin()/isSuperAdmin() (app/Models/User.php:166-174) solo son');
        $this->line('  true para los roles super-administrator / Super Administrador / Administrador / DESARROLLADOR.');
        $this->line('  Cualquier otro rol con el permiso de VISTA pero que no sea responsable de ningún almacén cae');
        $this->line('  en el where y ve 0 filas -- exactamente el síntoma reportado de Alondra.');
        $this->newLine();
        $this->info('--- 3) ¿Es un permiso/asignación visible? ---');
        $this->line('NO. Hallazgo: "alcance de datos no asignable". El criterio que decide qué almacenes ve cada');
        $this->line('  usuario (ser su "responsable") no es un permiso Spatie ni tiene pantalla de asignación --');
        $this->line('  se resuelve leyendo `inventory_stores.user_id` directo en BD. Nadie con el permiso de vista');
        $this->line('  puede saber, sin abrir tinker/BD, por qué ve la pantalla vacía; y no hay forma de asignar');
        $this->line('  "colaboradores extra" a un almacén (1 responsable, no un equipo).');
    }

    private function botonAgregar(User $testUser): void
    {
        $this->info('--- 4) Por qué el botón "Agregar" sigue visible ---');
        $this->line('resources/js/components/module/inventory/inventory_store/InventoryStoreListar.vue:10-17 -- el');
        $this->line('  botón "Agregar" es un <button> estático, SIN v-if ni v-hasPermission (a diferencia de otros');
        $this->line('  CRUDs del sistema que sí condicionan sus botones por permiso). Se renderiza para cualquiera');
        $this->line('  que llegue a la pantalla, tenga o no ' . self::ADD_PERMISSION . '.');
        $puede = $testUser->can(self::ADD_PERMISSION) ? 'SÍ' : 'NO';
        $this->line("  El backend SÍ está protegido: la usuaria de prueba tiene {$puede} el permiso " . self::ADD_PERMISSION);
        $this->line('  y POST /inventory/inventory_store/add está gateado en config/route_permission.php:1073 --');
        $this->line('  si lo intenta, CheckRoutePermission la bloquea (403 JSON en la llamada AJAX real, o redirect');
        $this->line('  silencioso en navegación de página completa, según app/Modules/Core/Auth/Middleware/');
        $this->line('  CheckRoutePermission.php:82-120). Es un gap de UX/consistencia, NO un hoyo de seguridad.');
    }

    private function menuLateral(): void
    {
        $this->info('--- 5) ¿El menú lateral sale de permisos o de lista fija? ---');
        $this->line('Mixto, con dos capas (verificado contra app/Modules/Core/Layout/ViewComposers/SidebarComposer.php');
        $this->line('  y resources/views/module-sidebar/inventario.blade.php):');
        $this->line('  - Capa 1 (qué módulos EXISTEN en el menú): tabla `module_sidebar_config` en BD, vía');
        $this->line('    ModuleSidebarConfig::visibleInSidebar() -- una LISTA FIJA de configuración (no depende del');
        $this->line('    usuario). Decide si "Inventario" aparece como entrada del sidebar en absoluto.');
        $this->line('  - Capa 2 (qué VE cada usuario dentro de esa entrada): el propio partial blade envuelve cada');
        $this->line('    <a> en @canany/@can (líneas 2,14,21,31,38... de inventario.blade.php) -- esto SÍ es');
        $this->line('    permisos reales de Spatie, evaluados por usuario.');
        $this->line('  Admin ve todo el árbol por el bypass isAdmin()/isSuperAdmin() dentro de esos mismos @can');
        $this->line('    (canAny/can devuelven true automáticamente para admin vía Gate::before, ver User::isAdmin()).');
        $this->line('  La usuaria de prueba ve "Inventario > Almacenes > Listar" únicamente porque tiene el permiso');
        $this->line('    ' . self::VIEW_PERMISSION . ' -- exactamente lo mismo que abre la ruta. El menú NO es la causa');
        $this->line('    de "0 filas"; el menú y la ruta hacen bien su trabajo.');
    }

    /**
     * Escaneo reusable (estructura pensada para Casos 1..N, decisión q2): busca en
     * app/Http/HelpersModule y app/Http/Traits/Models cualquier `where('user_id', ...)`
     * cuyo guard cercano sea isAdmin()/isSuperAdmin() SIN que en la misma línea haya
     * un `->can(`/`Gate::` -- o sea, sin permiso que lo gobierne.
     */
    private function buscarOtrosCasos(): array
    {
        $encontrados = [];
        $dirs = [
            app_path('Http/HelpersModule'),
            app_path('Http/Traits/Models'),
        ];

        foreach ($dirs as $dir) {
            if (!File::isDirectory($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $lines = file($file->getPathname());
                foreach ($lines as $i => $line) {
                    if (!preg_match("/where\\(\\s*['\"]user_id['\"]\\s*,\\s*auth\\(\\)/", $line)) {
                        continue;
                    }

                    $desde = max(0, $i - 6);
                    $contexto = implode('', array_slice($lines, $desde, 7));
                    $tieneGuardAdmin = (bool) preg_match('/isAdmin\(\)|isSuperAdmin\(\)/', $contexto);
                    $tienePermiso = (bool) preg_match('/->can\(|Gate::/', $contexto);

                    if ($tieneGuardAdmin && !$tienePermiso) {
                        $encontrados[] = [
                            'archivo' => str_replace(base_path() . '/', '', $file->getPathname()),
                            'linea' => $i + 1,
                            'clasificacion' => 'NO GOBERNADO (alcance de datos no asignable)',
                        ];
                    } elseif ($tieneGuardAdmin && $tienePermiso) {
                        $encontrados[] = [
                            'archivo' => str_replace(base_path() . '/', '', $file->getPathname()),
                            'linea' => $i + 1,
                            'clasificacion' => 'gobernado por permiso (referencia, no es hallazgo)',
                        ];
                    }
                }
            }
        }

        return $encontrados;
    }

    private function reportarOtrosCasos(array $casos): void
    {
        $this->info('--- Barrido: otros casos del mismo patrón (misma familia que Almacenes) ---');
        if (empty($casos)) {
            $this->line('  (sin resultados)');
            return;
        }

        $this->table(['Archivo', 'Línea', 'Clasificación'], array_map(
            fn ($c) => [$c['archivo'], $c['linea'], $c['clasificacion']],
            $casos
        ));
    }

    private function escribirJson(array $comparativa, array $otrosCasos): void
    {
        $path = storage_path('app/auditoria/caso0-alondra-item846-' . now()->format('Ymd-His') . '.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'generado_en' => now()->toIso8601String(),
            'comparativa' => $comparativa,
            'otros_casos' => $otrosCasos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->newLine();
        $this->info("JSON escrito en: {$path}");
    }
}
