<?php

namespace App\Modules\Addons\Empresa\Seeders;

use App\Modules\Addons\Manual\Models\ManualSection;
use Illuminate\Database\Seeder;

/**
 * Capítulo "Talento" del Manual Operativo de Meganet (addon-manual, /manual
 * — renombrado de "Manual de Usuario" el 2026-09-22) — decisión de Irving:
 * se movió aquí desde /empresa/manual (addon-empresa, que sigue existiendo
 * con solo su contenido de identidad/misión/valores; ver
 * TalentoManualSeeder.php, ahora sin uso — el capítulo se dio de baja ahí).
 * Nota: ambos módulos comparten hoy el mismo nombre visible "Manual
 * Operativo de Meganet" — decisión explícita de Irving, no un descuido.
 *
 * Este destino es un sistema DISTINTO (ver App\Modules\Addons\Manual\):
 * - Una fila por PANTALLA (module_slug único), no capítulo→sección.
 * - `content` es MARKDOWN (el frontend lo parsea a mano en ManualIndex.vue,
 *   sin librería), NO HTML crudo — nada de <p>/<ul>/<strong> aquí.
 * - La navegación del menú es una lista FIJA en ManualIndex.vue (MENU); cada
 *   `search` ahí debe calzar exacto con el `module_slug` de una fila de este
 *   seeder, o esa entrada del menú no encuentra contenido.
 * - Capturas en public/images/manual/talento/*.png; las de las pestañas de la
 *   ficha propia (ficha-*.png) se tomaron el 2026-10-23 al retirarse el Portal.
 * - visible_roles (vía App\Support\Manual\HasManualRoleVisibility, misma
 *   lógica que el otro manual) — idéntico mapeo rol↔pantalla que antes.
 *
 * Idempotente: upsert por module_slug con version fija en 1 (el versionado
 * incremental del módulo es para las regeneraciones automáticas por Claude
 * API; este contenido es curado a mano, no participa de eso — ver
 * ManualGeneratorService::loadActiveModules(), que solo toca module_slug que
 * calzan con una fila real de `modules`/`module_registry`, nunca los
 * "talento-*" de este seeder).
 */
class TalentoUserManualSeeder extends Seeder
{
    private const TECNICOS = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];
    private const COLABORADORES = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA', 'Vendedor', 'conductor', 'Mostrador', 'SUPERVISOR_MOSTRADOR'];
    // Páginas del Portal de Colaborador, que se retiró el 2026-10-23: se borran del manual al sembrar.
    private const RETIRADAS = [
        'talento-portal-mi-dia', 'talento-portal-mi-dinero', 'talento-portal-mi-material',
        'talento-portal-mis-prospectos', 'talento-portal-mis-documentos', 'talento-portal-perfil',
    ];
    private const SHOT_BASE = '/images/manual/talento/';

    public function run(): void
    {
        ManualSection::whereIn('module_slug', self::RETIRADAS)->delete();

        foreach ($this->sections() as $s) {
            $content = $s['content'] . $this->figure($s['shot'] ?? null, $s['title']);

            ManualSection::updateOrCreate(
                ['module_slug' => $s['slug'], 'version' => 1],
                [
                    'title' => $s['title'],
                    'content' => $content,
                    'generated_at' => now(),
                    'visible_roles' => $s['roles'],
                ]
            );
        }
    }

    private function figure(?string $shot, string $title): string
    {
        if (!$shot) {
            return '';
        }

        $src = self::SHOT_BASE . $shot . '.png';

        return "\n\n![Captura de la pantalla {$title}]({$src})\n*Así se ve esta pantalla hoy en el sistema.*";
    }

    /** @return array<int, array{slug:string, title:string, content:string, roles:?array, shot:?string}> */
    private function sections(): array
    {
        return [
            [
                'slug' => 'talento-que-es',
                'title' => 'Qué es Talento',
                'roles' => null,
                'shot' => null,
                'content' => 'Talento es el módulo con todo lo relacionado a quienes trabajan en Meganet: técnicos, '
                    . 'vendedores, mostrador, y cualquier otro colaborador. Junta en un solo lugar su ficha, sus órdenes '
                    . 'de trabajo en campo, cuánto se les paga y por qué, su material asignado, su capacitación y su '
                    . "documentación laboral.\n\n"
                    . 'Tiene dos caras: el **panel de administración** (lo que ve quien gestiona al personal) y la '
                    . '**ficha propia** de cada colaborador (al entrar a Talento cada quien cae en la suya: su tablero, '
                    . 'su día, su dinero, sus prospectos y, si tiene gente a su cargo, su equipo). Todo está en un solo '
                    . "lugar: el antiguo Portal de Colaborador se retiró el 23 de octubre de 2026.\n\n"
                    . 'Cada pantalla de este capítulo explica qué hace cada botón, no solo para qué sirve la pantalla en general.',
            ],
            [
                'slug' => 'talento-colaboradores',
                'title' => 'Colaboradores',
                'roles' => self::TECNICOS,
                'shot' => 'colaboradores',
                'content' => "**Ruta:** `/talento` — la lista de todo el personal.\n\n"
                    . "- El buscador de arriba filtra por nombre o correo; los dos desplegables junto a él filtran por status (activo/inactivo) y por tipo (interno/externo).\n"
                    . "- **\"+ Nuevo colaborador\"** (arriba a la derecha) abre el alta de una persona nueva.\n"
                    . "- Cada fila muestra nombre, correo, tipo, departamento, supervisor, fecha de ingreso y status. A la derecha, 4 botones: el **lápiz naranja** edita los datos de esa persona; el **ícono de gráfica** lleva a su desempeño; el **ícono de documento** abre su expediente/documentos; y el **ícono de llave** permite restablecerle la contraseña de acceso.",
            ],
            [
                'slug' => 'talento-ordenes-de-trabajo',
                'title' => 'Órdenes de trabajo',
                'roles' => self::TECNICOS,
                'shot' => 'ordenes-de-trabajo',
                'content' => "**Ruta:** `/talento/ordenes` — todo el trabajo de campo: instalaciones, soportes, cambios de equipo, reubicaciones y bajas.\n\n"
                    . "- **\"+ Nueva orden\"** crea una orden manualmente y la asigna a un colaborador.\n"
                    . "- Los filtros de arriba buscan por colaborador o prospecto, y acotan por estado, tipo y rango de fecha.\n"
                    . "- La tabla muestra folio, colaborador, tipo de orden, puntos que vale, cuándo está agendada y su estado.\n"
                    . "- A la derecha de cada orden hay dos botones: **\"Ver\"** (siempre presente) abre el detalle completo con su checklist de evidencias. El segundo botón cambia según el estado: **\"Iniciar\"** en una orden pendiente, o **\"Validar\"** en una que el técnico ya marcó como completada — validar es lo que hace que esos puntos cuenten de verdad para la compensación de esa semana.",
            ],
            [
                'slug' => 'talento-compensacion',
                'title' => 'Compensación',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'compensacion',
                'content' => "**Ruta:** `/talento/compensacion` — las reglas con las que se calcula cuánto se le paga a cada colaborador.\n\n"
                    . "- **\"+ Nueva regla\"** crea una regla de pago (sueldo base + cuota de unidades requeridas + tarifa extra por unidad, con un período diario/semanal).\n"
                    . "- La tabla \"Reglas definidas\" lista cada regla con su tipo de colaborador, período, sueldo base, cuota, tarifa por unidad y si está activa; el botón **\"Editar\"** de cada fila la modifica.\n"
                    . "- Abajo, \"Asignar regla a colaborador\": se busca al colaborador, se elige la regla del desplegable, se pone la fecha desde la que aplica, y **\"Asignar\"** la deja activa para él.",
            ],
            [
                'slug' => 'talento-liquidaciones',
                'title' => 'Liquidaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'liquidaciones',
                'content' => "**Ruta:** `/talento/liquidaciones` — el pago semanal en sí (la semana de pago corre de sábado 18:00 a sábado 18:00).\n\n"
                    . "- **\"Calcular\"** (arriba a la derecha) genera las liquidaciones de la semana para todos los colaboradores, a partir de sus órdenes validadas y sus reglas de compensación.\n"
                    . "- La tabla muestra colaborador, período, unidades, pago base, sobreproducción, monto bruto y estado (Borrador/Cerrada).\n"
                    . "- **\"Ver\"** abre el desglose completo de esa liquidación. **\"Cerrar\"** (en rojo, porque es irreversible) la deja definitiva — una vez cerrada, esa semana ya no se puede volver a calcular ni modificar.",
            ],
            [
                'slug' => 'talento-custodia',
                'title' => 'Custodia',
                'roles' => self::COLABORADORES,
                'shot' => 'custodia',
                'content' => "**Ruta:** `/talento/custodia` — qué herramienta y material tiene cada colaborador bajo su resguardo ahora mismo.\n\n"
                    . "- Es de **solo lectura** (el dato real vive en Inventario) — aquí no se asigna ni se quita material, solo se consulta.\n"
                    . "- El buscador filtra por nombre de colaborador; cada tarjeta muestra su nombre, correo y tipo; al abrir una tarjeta se ve el detalle de lo que tiene asignado.\n"
                    . "- Cada colaborador ve lo suyo en la pestaña **Custodia** de su propia ficha.",
            ],
            [
                'slug' => 'talento-dispositivos',
                'title' => 'Dispositivos',
                'roles' => self::TECNICOS,
                'shot' => 'dispositivos',
                'content' => "**Ruta:** `/talento/dispositivos` — la app móvil de campo (\"Talento Equipo\") y qué celular tiene vinculado cada colaborador.\n\n"
                    . "- Arriba: la versión vigente de la app, con su código QR y los botones **\"Descargar APK\"** y **\"Copiar enlace\"** para instalarla en el celular del colaborador.\n"
                    . "- Abajo, la tabla de \"Dispositivos vinculados\": colaborador, dispositivo, plataforma, último acceso y estado; el buscador filtra por colaborador.\n"
                    . "- Cada colaborador solo puede tener **un** dispositivo activo a la vez — vincular uno nuevo revoca automáticamente el anterior (una re-vinculación puede pedir aprobación manual).",
            ],
            [
                'slug' => 'talento-proyectos',
                'title' => 'Proyectos',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'proyectos',
                'content' => "**Ruta:** `/talento/proyectos` — trabajo de planta externa (tendido de red por proyecto, distinto de una orden de trabajo de campo puntual).\n\n"
                    . "- **\"+ Nuevo proyecto\"** da de alta un proyecto con su lead y vigencia.\n"
                    . "- El buscador y el filtro \"Todos\" acotan la lista; la tabla muestra nombre, estado, lead a cargo, vigencia y porcentaje de avance.",
            ],
            [
                'slug' => 'talento-calidad-de-caja',
                'title' => 'Calidad de caja',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'calidad-de-caja',
                'content' => "**Ruta:** `/talento/calidad` — inspecciones de calidad sobre el trabajo de fusión óptica (\"cajas ODB\") de los técnicos.\n\n"
                    . "- Pestaña **\"Inspecciones\"**: **\"+ Nueva inspección\"** registra una revisión (con calificación de fusión, potencia y estético); los filtros acotan por caja, proyecto y resultado; la tabla muestra caja, proyecto, técnico, fecha, cada calificación, resultado y si ya fue validada.\n"
                    . "- Pestaña **\"Catálogo de estándares\"**: los criterios contra los que se califica cada inspección.",
            ],
            [
                'slug' => 'talento-penalizaciones',
                'title' => 'Penalizaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'penalizaciones',
                'content' => "**Ruta:** `/talento/penalizaciones` — descuentos o llamadas de atención aplicadas a un colaborador y su efecto en la compensación.\n\n"
                    . "- **\"Aplicar penalización\"** (botón rojo) da de alta una nueva, eligiendo técnico, categoría y monto.\n"
                    . "- Los 3 filtros de arriba acotan por técnico, categoría y estado; la tabla muestra técnico, tipo, monto, quién la aplicó, fecha y estado.\n"
                    . "- Pestaña **\"Apelaciones\"**: si un colaborador la disputa, aquí se resuelve. Pestaña **\"Catálogo de tipos\"**: el listado de motivos posibles y su monto estándar.",
            ],
            [
                'slug' => 'talento-credenciales-y-fondos-de-ahorro',
                'title' => 'Credenciales y fondos de ahorro',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'credenciales',
                'content' => "**Ruta:** `/talento/credenciales` — dos cosas distintas en la misma pantalla: documentos con fecha de vencimiento (licencias, certificaciones) y fondos de ahorro pendientes de aplicar.\n\n"
                    . "- Pestaña **\"Alertas de vencimiento\"** (la que abre por default): lista los documentos próximos a vencer o ya vencidos, con días restantes; el filtro \"Todos los estados\" acota la vista.\n"
                    . "- Pestaña **\"Por colaborador\"**: el mismo dato organizado por persona.\n"
                    . "- Pestaña **\"Fondos pendientes\"**: aportaciones de ahorro que todavía no se han aplicado.",
            ],
            [
                'slug' => 'talento-expediente-paquete-de-documentos',
                'title' => 'Expediente RH — paquete de documentos por puesto',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'paquetes-de-documentos',
                'content' => "**Ruta:** `/talento/expediente/paquetes` — qué documentos (contrato, responsivas, reglamentos) le corresponden a cada puesto.\n\n"
                    . "- El desplegable **\"Puesto\"** elige el puesto a configurar: son los roles de Administración, y quien tiene varios roles recibe los documentos de todos.\n"
                    . "- Al elegirlo aparece la lista de plantillas del expediente con una casilla cada una — se marcan las que aplican a ese puesto (un técnico recibe el paquete completo, un puesto de oficina solo el suyo) y se guarda. Ese paquete es lo que se genera automáticamente cuando se da de alta a alguien nuevo en ese puesto.",
            ],
            [
                'slug' => 'talento-prestamos-y-finiquito',
                'title' => 'Préstamos y finiquito',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'prestamos-y-finiquito',
                'content' => "**Ruta:** `/talento/finiquito` — dos pestañas en la misma pantalla.\n\n"
                    . "- Pestaña **\"Préstamos\"**: **\"+ Registrar préstamo\"** da de alta uno nuevo (monto, motivo, descuento semanal, quién lo autoriza); los filtros acotan por técnico y estado (activos/liquidados); la tabla muestra monto original, saldo pendiente, descuento por semana, motivo, quién lo autorizó y estado — el descuento se aplica solo, semana a semana, en la liquidación.\n"
                    . "- Pestaña **\"Finiquito\"**: calcula lo que corresponde pagar cuando un colaborador causa baja.",
            ],
            [
                'slug' => 'talento-academia',
                'title' => 'Academia',
                'roles' => self::COLABORADORES,
                'shot' => 'academia',
                'content' => "**Ruta:** `/talento/academia` — cursos de capacitación.\n\n"
                    . "- Pestaña **\"Catálogo de cursos\"** (la que abre por default): solo los cursos generales y los de su puesto. Cada tarjeta dice para quién es (**\"general\"** si es para todos, o el puesto, por ejemplo **\"Para: Técnico\"**) y tiene **\"Ver curso\"** para entrar a tomarlo.\n"
                    . "- Quien administra la Academia, en la pestaña **\"Admin\"**, ve todos los cursos; al crear o editar uno elige en **\"Para quién\"**: **\"General (para todos)\"** o **\"Solo estos roles\"** marcando los puestos.\n"
                    . "- Pestaña **\"Mis certificaciones\"**: los cursos que el colaborador que mira ya completó y certificó.",
            ],
            [
                'slug' => 'talento-dashboard',
                'title' => 'Dashboard de Talento',
                'roles' => self::TECNICOS,
                'shot' => 'dashboard-de-talento',
                'content' => "**Ruta:** `/talento/dashboard` — panorama general del equipo.\n\n"
                    . "- 4 tarjetas arriba: colaboradores activos, asistencia de hoy, órdenes de hoy y alertas.\n"
                    . "- Pestaña **\"Producción diaria\"** (la que abre por default): una gráfica con selector de días (7/30/etc.) y 3 casillas para incluir/quitar instalaciones, garantías recientes y garantías antiguas.\n"
                    . "- Pestaña **\"Mi panel\"**: el resumen personal de quien mira, si también es colaborador. Pestaña **\"Calculadora de pago\"**: simula cuánto pagaría cierta producción bajo una regla de compensación. Pestaña **\"Mi equipo\"**: el resumen de los colaboradores a su cargo.",
            ],
            [
                'slug' => 'talento-escalafon',
                'title' => 'Escalafón',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'escalafon',
                'content' => "**Ruta:** `/talento/escalafon` — el ranking de colaboradores por desempeño.\n\n"
                    . "- El selector **\"Semanas\"** (arriba a la derecha) cambia la ventana de tiempo que se está midiendo.\n"
                    . "- Pestaña **\"Posiciones\"** (la que abre por default): tabla con posición, colaborador, estrellas, puntaje total y el desglose por categoría (cuota, calidad, salud de red, apego a normas, asistencia) — los primeros 3 lugares llevan medalla.\n"
                    . "- Pestaña **\"Más mejorado\"**: quién subió más posiciones respecto al periodo anterior.",
            ],
            [
                'slug' => 'talento-roles-multiples',
                'title' => 'Colaboradores con roles múltiples',
                'roles' => self::TECNICOS,
                'shot' => 'embajadores',
                'content' => "**Ruta:** `/talento/embajadores-colabs` — **vista de solo lectura**: un colaborador puede además ser cliente/embajador (programa de referidos) o vendedor en otros módulos del sistema; esta pantalla muestra ese cruce sin modificar ninguna de esas tablas.\n\n"
                    . "- El buscador de arriba filtra por colaborador.\n"
                    . "- La tabla muestra, por colaborador: tipo, si es embajador (y cuántos referidos y comisiones acumuladas si lo es), si es vendedor (y sus ventas de las últimas 4 semanas si lo es).\n"
                    . "- El ícono de **ojo** al final de cada fila abre el detalle de ese cruce.",
            ],
            [
                'slug' => 'talento-mis-ventas',
                'title' => 'Mis ventas y ranking de ventas',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'mis-ventas-y-ranking-de-ventas',
                'content' => "**Rutas:** `/talento/mis-ventas` y `/talento/ventas/ranking` — vista de **solo lectura**: las cifras se calculan con el mismo motor que usa el dashboard del módulo Vendedores, filtradas a las ventas y prospectos propios de quien mira (o de todos, en el ranking).\n\n"
                    . "Si el colaborador que se está viendo no tiene una cuenta de vendedor asociada, la pantalla lo dice explícitamente en vez de mostrar números a medias — no hay nada más que hacer ahí hasta que se le active esa cuenta.",
            ],
            [
                'slug' => 'talento-articulos-de-vendedor',
                'title' => 'Artículos de vendedor',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'articulos-de-vendedor',
                'content' => "**Ruta:** `/talento/articulos-vendedor` — catálogo **de solo lectura** (reusa el inventario del módulo Vendedores) de qué artículo tiene asignado cada vendedor.\n\n"
                    . 'El buscador filtra por artículo o vendedor; la tabla muestra artículo, tipo, categoría, cantidad, condición, a qué vendedor está asignado y desde cuándo.',
            ],
            [
                'slug' => 'talento-caja-de-vendedor',
                'title' => 'Caja de vendedor',
                'roles' => ['CONTADOR'],
                'shot' => 'caja-de-vendedor',
                'content' => "**Ruta:** `/talento/caja-vendedor` — vista **replicada** de la caja diaria de efectivo que ya usa el módulo Vendedores: lee y escribe las mismas tablas, sin un motor de dinero aparte.\n\n"
                    . 'Se busca al colaborador en el desplegable **"Colaborador"** para ver sus cajas — apertura, cierre, ingresos extra, observaciones y gastos a proveedor, con su comprobante en PDF, igual que en Vendedores.',
            ],
            [
                'slug' => 'talento-comisiones',
                'title' => 'Comisiones',
                'roles' => ['Mostrador', 'Vendedor', 'TECNICO', 'Almacen'],
                'shot' => 'comisiones',
                'content' => "**Ruta:** `/talento/comisiones` — vista **replicada** de comisiones de Vendedores: usa el mismo motor de cálculo y las mismas tablas, sin un motor de dinero paralelo.\n\n"
                    . 'Se elige un colaborador en el desplegable para ver sus reglas de comisión asignadas, sus pagos registrados y su estado de cuenta. Si el colaborador elegido no tiene una cuenta de vendedor asociada, la pantalla lo avisa claramente en vez de mostrar datos a medias.',
            ],
            [
                'slug' => 'talento-documentos-pendientes',
                'title' => 'Documentos pendientes',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'documentos-pendientes',
                'content' => "**Ruta:** `/talento/documentos-pendientes` — vista de **solo lectura** de todos los documentos con firma pendiente, de TODOS los colaboradores a la vez (no solo de uno).\n\n"
                    . "- 3 tarjetas arriba: total de pendientes, cuántos llevan más de 7 días y cuántos más de 30.\n"
                    . "- **\"Actualizar\"** refresca la lista.\n"
                    . "- La tabla agrupa los documentos por colaborador, con días pendientes y última acción. La única acción disponible por fila es **\"Recordar\"**, que le manda un recordatorio por WhatsApp — esta pantalla no firma ni edita nada, solo avisa.",
            ],
            [
                'slug' => 'talento-mi-ficha',
                'title' => 'Tu ficha de Talento',
                'roles' => null,
                'shot' => null,
                'content' => "**Ruta:** `/talento` — al entrar, cada colaborador cae directo en su propia ficha (quien administra al personal ve la lista de colaboradores).\n\n"
                    . "- Arriba sale su nombre y, debajo, sus pestañas. **Información** trae sus datos, su credencial (el carnet, con el botón **\"Editar foto y datos\"**) y su tablero **\"Mi progreso\"** (técnicos y mostrador: trabajos y calidad; vendedores: ventas y prospectos).\n"
                    . "- Las pestañas **Mi día**, **Mi dinero**, **Mis prospectos** (si vende) y **Mi equipo** (si tiene gente a su cargo) solo le salen a la persona en su propia ficha: un supervisor o administrador que abre la ficha de otro no las ve, porque marcar asistencia, subir evidencia o firmar solo lo hace uno mismo.\n"
                    . "- Desde cualquier pantalla, el menú de usuario (arriba a la derecha, con su nombre) tiene el atajo **\"Mi día\"**, que abre su ficha en esa pestaña.\n"
                    . "- Lo que antes estaba en el Portal y ya existía en la ficha sigue ahí: su material está en **Custodia**, sus documentos para firmar en **Paquetes de documentos**, y el modo oscuro y cerrar sesión en el menú de usuario.",
            ],
            [
                'slug' => 'talento-mi-dia',
                'title' => 'Tu ficha — Mi día',
                'roles' => self::COLABORADORES,
                'shot' => 'ficha-mi-dia',
                'content' => "Pestaña **Mi día** de su propia ficha — lo del día a día.\n\n"
                    . "- **\"Registro de hoy\"**: a qué hora registró su entrada (y dónde) y su salida. Aquí solo se consulta: la entrada y la salida se registran en la pestaña **Asistencia**, con **\"Registrar entrada\"** y **\"Registrar salida\"**. Se usa su ubicación para revisar que esté en su lugar de trabajo; si está fuera, la entrada se registra igual pero queda marcada para revisión.\n"
                    . "- **\"Órdenes de hoy\"**: sus órdenes de trabajo agendadas para hoy, con cliente, dirección y estado. Al abrir una: **\"Iniciar orden\"**; luego **\"Capturar evidencia\"** (foto con la cámara en vivo, sin galería; algunas piden la lectura dBm o una justificación), las firmas del técnico y del cliente, y **\"Completar orden\"**, que avisa qué evidencia falta si la hay. Con las dos firmas, **\"Aceptar orden\"** la pasa a espera de activación.\n"
                    . "- La cámara y la ubicación solo funcionan entrando al sistema por https (la dirección con nombre, no la IP).",
            ],
            [
                'slug' => 'talento-mi-dinero',
                'title' => 'Tu ficha — Mi dinero',
                'roles' => self::COLABORADORES,
                'shot' => 'ficha-mi-dinero',
                'content' => "Pestaña **Mi dinero** de su propia ficha — su propia compensación de la semana de pago, solo para consultar, en 4 partes:\n\n"
                    . "- **\"Cuenta\"** (la que abre primero): neto, abonos y descuentos de la semana. Mientras la semana sigue abierta se ve vacía con el aviso \"Tu cuenta se llena cuando se cierra la semana de pago\" — no es un error, todavía no se cierra.\n"
                    . "- **\"Desglose\"**: lo que lleva producido en la semana — pago proyectado, unidades contra su cuota y sobreproducción.\n"
                    . "- **\"Fondo\"**: su fondo de ahorro (lo acumulado, la meta y el aporte semanal). Es ahorro que recupera, no un descuento por penalización.\n"
                    . "- **\"Préstamos\"**: lo que le falta pagar de cada préstamo y su abono semanal.\n\n"
                    . 'Son los mismos números que ve Recursos Humanos en Liquidaciones — nunca un cálculo aparte.',
            ],
            [
                'slug' => 'talento-mis-prospectos',
                'title' => 'Tu ficha — Mis prospectos',
                'roles' => ['Vendedor'],
                'shot' => 'ficha-mis-prospectos',
                'content' => "Pestaña **Mis prospectos** de su propia ficha — solo para quien vende: sus prospectos del CRM con teléfono, correo, estado y desde cuándo.\n\n"
                    . "- El buscador filtra por nombre o teléfono y el desplegable por estado (con cuántos hay en cada uno).\n"
                    . "- Si puede editar prospectos en el CRM, el nombre abre la ficha del prospecto.\n"
                    . '- Si no tiene ninguno asignado, la pantalla lo dice ("No tienes prospectos asignados") en vez de quedar en blanco.',
            ],
            [
                'slug' => 'talento-mi-equipo',
                'title' => 'Tu ficha — Mi equipo',
                'roles' => null,
                'shot' => 'ficha-mi-equipo',
                'content' => "Pestaña **Mi equipo** de su propia ficha — solo para quien tiene colaboradores a su cargo (su equipo directo; el súper administrador de Talento ve a todos).\n\n"
                    . "- **\"Cómo va mi equipo\"**: cada persona con su tipo de trabajo y su avance de los últimos 30 días; el nombre abre su ficha.\n"
                    . "- **\"Penalizaciones\"**: las que proponen las reglas semanales, **por confirmar** (el número rojo dice cuántas hay). El descuento solo se aplica cuando el supervisor pulsa **\"Confirmar\"** y acepta el aviso con el monto. Debajo, las aplicadas en los últimos 60 días con su estado (aplicada, en apelación, revocada o mantenida).",
            ],
        ];
    }
}
