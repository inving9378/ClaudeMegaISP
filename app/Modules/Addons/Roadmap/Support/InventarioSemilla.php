<?php

namespace App\Modules\Addons\Roadmap\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEMILLA DEL MOTOR DE AUDITORÍA (#559) — pendientes por módulo del inventario del 2026-08-08.
 *
 * Qué es y qué NO es:
 *   - Es el arranque de la Fase 1: lo que un escaneo de código NO puede ver por sí solo (huecos de
 *     datos, decisiones de producto abiertas, fases declaradas y no construidas, bugs conocidos).
 *   - NO duplica a los detectores en vivo. Huecos ruteados, TODO/FIXME, andamiaje muerto y enlaces
 *     de menú rotos los descubre `AuditorService` leyendo el código AHORA — si algo de eso se
 *     arregla, deja de aparecer solo. Aquí va únicamente lo que el escaneo no alcanza.
 *
 * FASE 2: cuando existan los spec items por módulo con su DoD, esta semilla se retira y el motor
 * mide realidad-vs-spec (ver `AuditorService::medirContraSpec`). Mientras tanto, esto es el spec.
 *
 * `tipo`:
 *   - 'mecanico' → item ejecutable (nivel A, reversible). Fluye solo a la cola.
 *   - 'producto' → NADIE lo adivina: va a la bandeja de Irving con la pregunta concreta.
 *
 * `clave` debe ser ESTABLE: es parte de la huella de dedup. Renombrarla recrea el item.
 *
 * `vigente` (opcional): closure que responde "¿este gap SIGUE existiendo?". Sin ella, la entrada
 * se cree a sí misma para siempre. Con ella, se apaga sola cuando alguien cierra el hueco — que es
 * exactamente lo que pasó en la primera corrida: el circuito registró addon-talento en
 * module_registry mientras se escribía esta lista. Si la comprobación truena, el gap se descarta
 * (fallar hacia "no generar trabajo" es el lado seguro).
 */
class InventarioSemilla
{
    /** Fecha del inventario que sembró esta lista (trazabilidad en el item creado). */
    public const FECHA = '2026-08-08';

    /**
     * @return array<string, array<int, array{clave:string,titulo:string,tipo:string,detalle:string,pregunta?:string}>>
     */
    public static function porModulo(): array
    {
        return [
            'Reportes' => [
                [
                    'clave'    => 'modulo-vacio-decidir',
                    'titulo'   => 'Reportes: el módulo está vacío y duplica /releases — decidir si se llena o se elimina',
                    'tipo'     => 'producto',
                    'detalle'  => 'El módulo contiene sólo module.json, routes.php, ModuleServiceProvider y seis .gitkeep: '
                        . '0 controladores, 0 rutas registradas. Sus 6 permisos son release_* (los de Core/Release) y su '
                        . 'admin_card apunta a /releases, que sirve Core/Release. Hoy su único efecto es una tarjeta '
                        . 'duplicada en /administracion.',
                    'pregunta' => '¿Qué hacemos con el módulo Reportes: lo eliminamos del registry y del disco (su función ya la cubre Core/Release), o lo llenamos con reportes operativos propios? Si es lo segundo, ¿qué reportes debe traer?',
                    'vigente'  => fn () => is_dir(base_path('app/Modules/Addons/Reportes')),
                ],
            ],

            'GestionRed' => [
                [
                    'clave'   => 'getoltdata-traga-403',
                    'titulo'  => 'GestionRed: getOLTData se traga el 403 y deja la pantalla muda',
                    'tipo'    => 'mecanico',
                    'detalle' => 'Cuando el usuario no trae el permiso, getOLTData captura el 403 y devuelve vacío en vez de '
                        . 'propagar el error → el operador ve una pantalla en blanco sin saber que le falta permiso. '
                        . 'Arreglo: propagar el estado de error y que el front muestre el aviso.',
                    // El fix (item #571) agregó el aviso de 403 dentro del catch de getOLTData;
                    // si ese literal desaparece del helper, el gap volvió a abrirse.
                    'vigente' => function () {
                        $path = base_path('resources/js/components/module/olts/helper/request.js');
                        if (! is_file($path)) {
                            return false;
                        }

                        return ! str_contains(file_get_contents($path), 'No tienes permiso para ver esta información');
                    },
                ],
            ],

            'Finanzas' => [
                [
                    'clave'   => 'link-notificaciones-pendientes-404',
                    'titulo'  => 'Finanzas: el enlace del sidebar /finanzas/notificaciones-pendientes da 404 (sin ruta)',
                    'tipo'    => 'mecanico',
                    'detalle' => 'El link vive en module-sidebar/finanzas.blade.php gateado por facturacion.notif.gestionar, '
                        . 'pero NO hay ruta registrada para /finanzas/notificaciones-pendientes. Bug preexistente. '
                        . 'Arreglo mecánico: quitar el link del sidebar (si la pantalla no existe) o registrar la ruta '
                        . 'a la pantalla que sí la cubre.',
                    // El detector `enlace_roto` sólo lee module.json; este link vive en un partial
                    // Blade del sidebar, así que la comprobación va aquí. Cierra si YA HAY ruta
                    // registrada (se optó por construir la pantalla) O si el link ya no está en el
                    // sidebar (se optó por quitarlo, item #575) — cualquiera de las dos resuelve el gap.
                    'vigente' => function () {
                        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $r) {
                            if (str_starts_with(ltrim($r->uri(), '/'), 'finanzas/notificaciones-pendientes')) {
                                return false;
                            }
                        }

                        $sidebar = base_path('resources/views/module-sidebar/finanzas.blade.php');
                        if (is_file($sidebar) && !str_contains(file_get_contents($sidebar), 'notificaciones-pendientes')) {
                            return false;
                        }

                        return true;
                    },
                ],
            ],

            // NOTA: el pendiente de Mensajes (falta el GET de /message/reminder) NO va en la
            // semilla: el detector `enlace_roto` ya lo encuentra solo leyendo el module.json, y un
            // detector en vivo se apaga cuando alguien lo arregla. Duplicarlo aquí crearía dos
            // items para el mismo hueco.

            'Hub' => [
                [
                    'clave'   => 'validador-invertido',
                    'titulo'  => 'Hub: el validador de integraciones reporta valid:false invertido',
                    'tipo'    => 'mecanico',
                    'detalle' => 'El validador devuelve valid:false pero el ternario que lo consume lo invierte; el campo '
                        . 'autoritativo es last_validation_status. Normalizar el retorno para que un solo campo mande.',
                ],
            ],

            'Inventario' => [
                [
                    'clave'    => 'tipos-sin-clasificar',
                    'titulo'   => 'Inventario: ~18 tipos de artículo siguen sin categoría (herramienta/material)',
                    'tipo'     => 'producto',
                    'detalle'  => 'inventory_item_types.categoria quedó en NULL para los dudosos: ONT, MODEM, TELEFONOS DE CASA, '
                        . 'ACOPLADOR, SPLITTER, CONECTOR, CONECTORES, CABLE, PILAS, PAPELERIA, FLYERS, CARRETE, TENSOR, '
                        . 'TENSORES, HOJAS, ELIMINADOR, POWER. Sin clasificar, "Mi material" del portal los agrupa en '
                        . '"Sin clasificar".',
                    'pregunta' => '¿Cómo se clasifica cada uno de los tipos dudosos de inventario: herramienta, material, o hace falta una tercera categoría "equipo de cliente" (ONT, MODEM, TELEFONOS DE CASA)?',
                    'vigente'  => fn () => Schema::hasColumn('inventory_item_types', 'categoria')
                        && DB::table('inventory_item_types')->whereNull('categoria')->exists(),
                ],
            ],

            'Talento' => [
                [
                    'clave'    => 'no-esta-en-module-registry',
                    'titulo'   => 'Talento no está en module_registry: corre siempre y no se puede desactivar',
                    'tipo'     => 'producto',
                    'detalle'  => 'Es el único módulo en disco sin fila en module_registry. ModuleManagerService::isActive() '
                        . 'hace `?? true` (falla-abierto), así que Talento arranca siempre, no aparece en Module Manager y '
                        . 'no hay switch para apagarlo. Registrarlo lo vuelve gobernable, pero también apagable: es un '
                        . 'cambio de comportamiento sobre el módulo más grande del sistema (54 tablas, 240 rutas).',
                    'pregunta' => '¿Registramos addon-talento en module_registry (queda gobernable desde Module Manager, pero también apagable), o lo dejamos fuera a propósito como módulo siempre-encendido?',
                    'vigente'  => fn () => Schema::hasTable('module_registry')
                        && ! DB::table('module_registry')->where('slug', 'addon-talento')->exists(),
                ],
                [
                    'clave'   => 'dashboardservice-with-level',
                    'titulo'  => 'Talento: DashboardService::tecnicoPreview y ::team truenan por with(\'level\')',
                    'tipo'    => 'mecanico',
                    'detalle' => 'Ambos métodos hacen with(\'level\') sobre TalentoColaborador, que NO tiene esa relación → '
                        . 'excepción en tiempo de ejecución. Bug preexistente, arreglo acotado: definir la relación o '
                        . 'quitar el eager-load.',
                    'vigente' => function () {
                        $f = base_path('app/Modules/Addons/Talento/Services/DashboardService.php');

                        return is_file($f) && str_contains((string) file_get_contents($f), "with('level')");
                    },
                ],
            ],

            'Marketing' => [
                [
                    'clave'   => 'enum-broll-marketing-assets',
                    'titulo'  => 'Marketing: marketing_assets.type no incluye \'broll\' en el ENUM',
                    'tipo'    => 'mecanico',
                    'detalle' => 'La migración que agrega \'broll\' al enum quedó posiblemente colgada por lock. Verificar con '
                        . 'SHOW COLUMNS FROM marketing_assets WHERE Field=\'type\' y completar de forma aditiva si falta.',
                    'vigente' => function () {
                        if (! Schema::hasTable('marketing_assets')) {
                            return false;
                        }
                        $col = DB::select("SHOW COLUMNS FROM marketing_assets WHERE Field = 'type'");

                        return $col !== [] && ! str_contains((string) ($col[0]->Type ?? ''), "'broll'");
                    },
                ],
                [
                    'clave'   => 'voz-nicho-gamer',
                    'titulo'  => 'Marketing: el nicho gamer cae a la voz \'nova\' en vez de \'echo\'/\'onyx\'',
                    'tipo'    => 'mecanico',
                    'detalle' => 'Bug en el mapeo de preferred_voice_id del nicho: el fallback pisa la voz configurada.',
                ],
                [
                    'clave'   => 'tts-lee-telefonos-corridos',
                    'titulo'  => 'Marketing: el TTS lee los números de teléfono corridos',
                    'tipo'    => 'mecanico',
                    'detalle' => 'OpenAI TTS pronuncia el teléfono como un número gigante. Preprocesar el texto insertando '
                        . 'separación entre dígitos antes de mandarlo al TTS.',
                ],
            ],

            'Vendedores' => [
                [
                    'clave'    => 'rutas-prospectos-statistics-inexistentes',
                    'titulo'   => 'Vendedores: /sellers/prospectos y /sellers/statistics no existen (404 desde el menú)',
                    'tipo'     => 'producto',
                    'detalle'  => 'Verificado contra route:list: las rutas no están registradas (no es un 403 por permisos, '
                        . 'la ruta no existe). El enlace se ofrece desde el Portal de Colaborador y muere en 404.',
                    'pregunta' => '¿Las pantallas de Vendedores /sellers/prospectos y /sellers/statistics se reconstruyen (y con qué contenido), o se retiran los enlaces del menú?',
                ],
            ],

            'Usuarios' => [
                [
                    'clave'    => 'permisos-endpoints-en-abort',
                    'titulo'   => 'Usuarios: getPermissionUser y updatePermissionUser están deshabilitados con abort',
                    'tipo'     => 'producto',
                    'detalle'  => 'PermissionController tiene dos endpoints cortados con abort() y una ruta activa hacia '
                        . 'syncRoles marcada con TODO. Tocan permisos de usuario, que es frontera dura del circuito.',
                    'pregunta' => '¿Los endpoints de permisos por usuario (getPermissionUser/updatePermissionUser) se reactivan, se rediseñan, o se eliminan junto con sus rutas? Tocan asignación de permisos, así que la decisión es tuya.',
                ],
            ],

            'Flotas' => [
                [
                    'clave'    => 'ocr-documentos-placeholder',
                    'titulo'   => 'Flotas: el OCR de documentos es un placeholder rotulado "Fase 7"',
                    'tipo'     => 'producto',
                    'detalle'  => 'La UI muestra "Detección automática con IA (Fase 7)" sin implementación detrás.',
                    'pregunta' => '¿Construimos ya el OCR de documentos de Flotas conectándolo al módulo IA existente, o se queda como placeholder hasta una fase posterior?',
                ],
            ],

            'Payments' => [
                [
                    'clave'   => 'resolvesystemuserid-rol-inexistente',
                    'titulo'  => 'Payments: resolveSystemUserId busca el rol SUPER_ADMIN, que no existe',
                    'tipo'    => 'mecanico',
                    'detalle' => 'PaymentApplicationService::resolveSystemUserId() busca un rol SUPER_ADMIN inexistente (los '
                        . 'reales son super-administrator/DESARROLLADOR) y cae al fallback user id 1 = "Admin". Los pagos '
                        . 'del webhook SPEI/OpenPay quedan atribuidos a Admin en vez de MEGAISP. Es sólo la ATRIBUCIÓN '
                        . '(add_by), el pago ya funciona.',
                    'vigente' => function () {
                        $f = base_path('app/Modules/Addons/Payments/Services/PaymentApplicationService.php');

                        return is_file($f) && str_contains((string) file_get_contents($f), 'SUPER_ADMIN');
                    },
                ],
            ],
        ];
    }
}
