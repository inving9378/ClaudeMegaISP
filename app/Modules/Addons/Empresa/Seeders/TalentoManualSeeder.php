<?php

namespace App\Modules\Addons\Empresa\Seeders;

use App\Modules\Addons\Empresa\Models\ManualChapter;
use App\Modules\Addons\Empresa\Models\ManualSection;
use App\Modules\Addons\Empresa\Models\ManualSectionVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Capítulo "Talento" del Manual General de la Empresa — primer capítulo del
 * manual de usuario pantalla-por-pantalla (los demás módulos se irán
 * agregando con este mismo patrón). Idempotente: correrlo de nuevo
 * actualiza el contenido y lo re-publica, sin duplicar capítulo/secciones
 * (localizados por slug único, como hace el resto del controlador).
 *
 * Cada sección explica QUÉ HACE cada botón/control de esa pantalla real (no
 * solo "para qué sirve la pantalla") y lleva una captura real embebida
 * (storage/app/public/empresa/manual/talento/<shot>.png, tomada con
 * Playwright contra dev el 2026-09-22 — admin real para las pantallas de
 * administración, cuenta de prueba dedicada "manual_demo_tecnico" para las
 * del Portal de Colaborador que necesitan datos propios de un colaborador).
 *
 * visible_roles usa ManualSection::ROLES_ASSIGNABLE + ROLE_ADMIN_ONLY,
 * calcado de qué rol real tiene el permiso de cada pantalla en BD (ver
 * verificación con usuarios reales en la bitácora del item). No se muestra
 * en el documento — solo decide qué sección le llega a cada quien.
 */
class TalentoManualSeeder extends Seeder
{
    private const TECNICOS = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];
    // public/images/ viaja con git (a diferencia de storage/app/public, gitignored) — las
    // capturas del manual son contenido versionado, no un upload de usuario.
    private const SHOT_BASE = '/images/manual/talento/';

    public function run(): void
    {
        $chapter = ManualChapter::firstOrCreate(
            ['slug' => 'talento'],
            ['title' => 'Talento', 'order' => (int) ManualChapter::max('order') + 1]
        );

        $currentSlugs = [];
        foreach ($this->sections() as $i => $s) {
            $content = $s['content'] . $this->figure($s['shot'] ?? null, $s['title']);
            $this->upsertSection($chapter, $i + 1, $s['title'], $content, $s['roles']);
            $currentSlugs[] = Str::slug($s['title']);
        }

        // Un título que cambia entre corridas (ej. "Embajadores" -> "Colaboradores con
        // roles múltiples") cambia de slug y upsertSection() lo trata como sección NUEVA —
        // esto poda las que quedaron huérfanas con el título/slug viejo, para que el
        // seeder sea la fuente de verdad real del capítulo, no solo aditivo.
        ManualSection::where('chapter_id', $chapter->id)
            ->whereNotIn('slug', $currentSlugs)
            ->get()
            ->each(fn (ManualSection $orphan) => $orphan->delete());
    }

    private function figure(?string $shot, string $title): string
    {
        if (!$shot) {
            return '';
        }

        $src = self::SHOT_BASE . $shot . '.png';

        return '<figure><img src="' . $src . '" alt="Captura de la pantalla ' . e($title) . '">'
            . '<figcaption>Así se ve esta pantalla hoy en el sistema.</figcaption></figure>';
    }

    private function upsertSection(ManualChapter $chapter, int $order, string $title, string $content, ?array $roles): void
    {
        $slug = Str::slug($title);

        $section = ManualSection::withTrashed()
            ->where('chapter_id', $chapter->id)
            ->where('slug', $slug)
            ->first();

        if (!$section) {
            $section = ManualSection::create([
                'chapter_id' => $chapter->id,
                'title' => $title,
                'slug' => $slug,
                'order' => $order,
            ]);
        } elseif ($section->trashed()) {
            $section->restore();
        }

        $section->title = $title;
        $section->order = $order;
        $section->visible_roles = $roles;
        $section->content = $content;
        $section->save();

        if (optional($section->publishedVersion)->content === $content) {
            return; // ya está publicado exactamente este contenido, nada que versionar
        }

        $nextVersion = (int) ManualSectionVersion::where('section_id', $section->id)->max('version_number') + 1;
        $version = ManualSectionVersion::create([
            'section_id' => $section->id,
            'version_number' => $nextVersion,
            'content' => $content,
            'is_published' => true,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        ManualSectionVersion::where('section_id', $section->id)->where('id', '!=', $version->id)->update(['is_published' => false]);
        $section->published_version_id = $version->id;
        $section->save();
    }

    /** @return array<int, array{title:string, content:string, roles:?array, shot:?string}> */
    private function sections(): array
    {
        return [
            [
                'title' => 'Qué es Talento',
                'roles' => null,
                'shot' => null,
                'content' => '<p>Talento es el módulo con todo lo relacionado a quienes trabajan en Meganet: técnicos, '
                    . 'vendedores, mostrador, y cualquier otro colaborador. Junta en un solo lugar su ficha, sus órdenes '
                    . 'de trabajo en campo, cuánto se les paga y por qué, su material asignado, su capacitación y su '
                    . 'documentación laboral.</p>'
                    . '<p>Tiene dos caras: el <strong>panel de administración</strong> (lo que ve quien gestiona al '
                    . 'personal) y el <strong>Portal de Colaborador</strong> (<code>/talento/portal</code>, lo que ve '
                    . 'cada quien de sí mismo: su día, su dinero, su material). Cada pantalla de este capítulo explica '
                    . 'qué hace cada botón, no solo para qué sirve la pantalla en general.</p>',
            ],
            [
                'title' => 'Colaboradores',
                'roles' => self::TECNICOS,
                'shot' => 'colaboradores',
                'content' => '<p><strong>Ruta:</strong> <code>/talento</code> — la lista de todo el personal.</p>'
                    . '<ul>'
                    . '<li>El buscador de arriba filtra por nombre o correo; los dos desplegables junto a él filtran '
                    . 'por status (activo/inactivo) y por tipo (interno/externo).</li>'
                    . '<li><strong>"+ Nuevo colaborador"</strong> (arriba a la derecha) abre el alta de una persona '
                    . 'nueva.</li>'
                    . '<li>Cada fila muestra nombre, correo, tipo, departamento, supervisor, fecha de ingreso y '
                    . 'status. A la derecha, 4 botones: el <strong>lápiz naranja</strong> edita los datos de esa '
                    . 'persona; el <strong>ícono de gráfica</strong> lleva a su desempeño; el <strong>ícono de '
                    . 'documento</strong> abre su expediente/documentos; y el <strong>ícono de llave</strong> permite '
                    . 'restablecerle la contraseña de acceso.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Órdenes de trabajo',
                'roles' => self::TECNICOS,
                'shot' => 'ordenes-de-trabajo',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/ordenes</code> — todo el trabajo de campo: '
                    . 'instalaciones, soportes, cambios de equipo, reubicaciones y bajas.</p>'
                    . '<ul>'
                    . '<li><strong>"+ Nueva orden"</strong> crea una orden manualmente y la asigna a un colaborador.</li>'
                    . '<li>Los filtros de arriba buscan por colaborador o prospecto, y acotan por estado, tipo y '
                    . 'rango de fecha.</li>'
                    . '<li>La tabla muestra folio, colaborador, tipo de orden, puntos que vale, cuándo está agendada '
                    . 'y su estado.</li>'
                    . '<li>A la derecha de cada orden hay dos botones: <strong>"Ver"</strong> (siempre presente) abre '
                    . 'el detalle completo con su checklist de evidencias. El segundo botón cambia según el estado: '
                    . '<strong>"Iniciar"</strong> en una orden pendiente, o <strong>"Validar"</strong> en una que el '
                    . 'técnico ya marcó como completada — validar es lo que hace que esos puntos cuenten de verdad '
                    . 'para la compensación de esa semana.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Compensación',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'compensacion',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/compensacion</code> — las reglas con las que '
                    . 'se calcula cuánto se le paga a cada colaborador.</p>'
                    . '<ul>'
                    . '<li><strong>"+ Nueva regla"</strong> crea una regla de pago (sueldo base + cuota de unidades '
                    . 'requeridas + tarifa extra por unidad, con un período diario/semanal).</li>'
                    . '<li>La tabla "Reglas definidas" lista cada regla con su tipo de colaborador, período, sueldo '
                    . 'base, cuota, tarifa por unidad y si está activa; el botón <strong>"Editar"</strong> de cada '
                    . 'fila la modifica.</li>'
                    . '<li>Abajo, <strong>"Asignar regla a colaborador"</strong>: se busca al colaborador, se elige '
                    . 'la regla del desplegable, se pone la fecha desde la que aplica, y <strong>"Asignar"</strong> '
                    . 'la deja activa para él.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Liquidaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'liquidaciones',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/liquidaciones</code> — el pago semanal en sí '
                    . '(la semana de pago corre de sábado 18:00 a sábado 18:00).</p>'
                    . '<ul>'
                    . '<li><strong>"Calcular"</strong> (arriba a la derecha) genera las liquidaciones de la semana '
                    . 'para todos los colaboradores, a partir de sus órdenes validadas y sus reglas de compensación.</li>'
                    . '<li>La tabla muestra colaborador, período, unidades, pago base, sobreproducción, monto bruto y '
                    . 'estado (Borrador/Cerrada).</li>'
                    . '<li><strong>"Ver"</strong> abre el desglose completo de esa liquidación. <strong>"Cerrar"</strong> '
                    . '(en rojo, porque es irreversible) la deja definitiva — una vez cerrada, esa semana ya no se '
                    . 'puede volver a calcular ni modificar.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Custodia',
                'roles' => self::TECNICOS,
                'shot' => 'custodia',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/custodia</code> — qué herramienta y material '
                    . 'tiene cada colaborador bajo su resguardo ahora mismo.</p>'
                    . '<ul>'
                    . '<li>Es de <strong>solo lectura</strong> (el dato real vive en Inventario) — aquí no se asigna '
                    . 'ni se quita material, solo se consulta.</li>'
                    . '<li>El buscador filtra por nombre de colaborador; cada tarjeta muestra su nombre, correo y '
                    . 'tipo; al abrir una tarjeta se ve el detalle de lo que tiene asignado.</li>'
                    . '<li>Cada colaborador ve exactamente lo mismo, de sí mismo, en "Mi material" dentro de su '
                    . 'propio Portal.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Dispositivos',
                'roles' => self::TECNICOS,
                'shot' => 'dispositivos',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/dispositivos</code> — la app móvil de campo '
                    . '("Talento Equipo") y qué celular tiene vinculado cada colaborador.</p>'
                    . '<ul>'
                    . '<li>Arriba: la versión vigente de la app, con su código QR y los botones '
                    . '<strong>"Descargar APK"</strong> y <strong>"Copiar enlace"</strong> para instalarla en el '
                    . 'celular del colaborador.</li>'
                    . '<li>Abajo, la tabla de "Dispositivos vinculados": colaborador, dispositivo, plataforma, '
                    . 'último acceso y estado; el buscador filtra por colaborador.</li>'
                    . '<li>Cada colaborador solo puede tener <strong>un</strong> dispositivo activo a la vez — '
                    . 'vincular uno nuevo revoca automáticamente el anterior (una re-vinculación puede pedir '
                    . 'aprobación manual).</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Roadmap',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'roadmap',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/roadmap</code> — <strong>ojo:</strong> esta '
                    . 'pantalla no es la ruta de crecimiento de un colaborador (eso vive en "Niveles"/"Escalafón"). Es '
                    . 'la bitácora de construcción del propio módulo Talento: por fases (Fase 0, Fase 1…), qué se '
                    . 'construyó en cada una y cuándo se completó. Sirve para saber qué tan avanzado está el sistema, '
                    . 'no para gestionar personal.</p>',
            ],
            [
                'title' => 'Proyectos',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'proyectos',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/proyectos</code> — trabajo de planta externa '
                    . '(tendido de red por proyecto, distinto de una orden de trabajo de campo puntual).</p>'
                    . '<ul>'
                    . '<li><strong>"+ Nuevo proyecto"</strong> da de alta un proyecto con su lead y vigencia.</li>'
                    . '<li>El buscador y el filtro "Todos" acotan la lista; la tabla muestra nombre, estado, lead a '
                    . 'cargo, vigencia y porcentaje de avance.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Calidad de caja',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'calidad-de-caja',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/calidad</code> — inspecciones de calidad sobre '
                    . 'el trabajo de fusión óptica ("cajas ODB") de los técnicos.</p>'
                    . '<ul>'
                    . '<li>Pestaña <strong>"Inspecciones"</strong>: <strong>"+ Nueva inspección"</strong> registra una '
                    . 'revisión (con calificación de fusión, potencia y estético); los filtros acotan por caja, '
                    . 'proyecto y resultado; la tabla muestra caja, proyecto, técnico, fecha, cada calificación, '
                    . 'resultado y si ya fue validada.</li>'
                    . '<li>Pestaña <strong>"Catálogo de estándares"</strong>: los criterios contra los que se '
                    . 'califica cada inspección.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Penalizaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'penalizaciones',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/penalizaciones</code> — descuentos o llamadas '
                    . 'de atención aplicadas a un colaborador y su efecto en la compensación.</p>'
                    . '<ul>'
                    . '<li><strong>"Aplicar penalización"</strong> (botón rojo) da de alta una nueva, eligiendo '
                    . 'técnico, categoría y monto.</li>'
                    . '<li>Los 3 filtros de arriba acotan por técnico, categoría y estado; la tabla muestra técnico, '
                    . 'tipo, monto, quién la aplicó, fecha y estado.</li>'
                    . '<li>Pestaña <strong>"Apelaciones"</strong>: si un colaborador la disputa, aquí se resuelve. '
                    . 'Pestaña <strong>"Catálogo de tipos"</strong>: el listado de motivos posibles y su monto '
                    . 'estándar.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Credenciales y fondos de ahorro',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'credenciales',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/credenciales</code> — dos cosas distintas en '
                    . 'la misma pantalla: documentos con fecha de vencimiento (licencias, certificaciones) y fondos '
                    . 'de ahorro pendientes de aplicar.</p>'
                    . '<ul>'
                    . '<li>Pestaña <strong>"Alertas de vencimiento"</strong> (la que abre por default): lista los '
                    . 'documentos próximos a vencer o ya vencidos, con días restantes; el filtro "Todos los estados" '
                    . 'acota la vista.</li>'
                    . '<li>Pestaña <strong>"Por colaborador"</strong>: el mismo dato organizado por persona.</li>'
                    . '<li>Pestaña <strong>"Fondos pendientes"</strong>: aportaciones de ahorro que todavía no se han '
                    . 'aplicado.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Expediente RH — paquete de documentos por puesto',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'paquetes-de-documentos',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/expediente/paquetes</code> — qué documentos '
                    . '(contrato, responsivas, reglamentos) le corresponden a cada puesto.</p>'
                    . '<ul>'
                    . '<li>El desplegable <strong>"Puesto"</strong> elige el puesto a configurar.</li>'
                    . '<li>Al elegirlo aparece la lista de plantillas del expediente con una casilla cada una — se '
                    . 'marcan las que aplican a ese puesto (un técnico recibe el paquete completo, un puesto de '
                    . 'oficina solo el suyo) y se guarda. Ese paquete es lo que se genera automáticamente cuando se '
                    . 'da de alta a alguien nuevo en ese puesto.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Préstamos y finiquito',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'prestamos-y-finiquito',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/finiquito</code> — dos pestañas en la misma '
                    . 'pantalla.</p>'
                    . '<ul>'
                    . '<li>Pestaña <strong>"Préstamos"</strong>: <strong>"+ Registrar préstamo"</strong> da de alta '
                    . 'uno nuevo (monto, motivo, descuento semanal, quién lo autoriza); los filtros acotan por '
                    . 'técnico y estado (activos/liquidados); la tabla muestra monto original, saldo pendiente, '
                    . 'descuento por semana, motivo, quién lo autorizó y estado — el descuento se aplica solo, '
                    . 'semana a semana, en la liquidación.</li>'
                    . '<li>Pestaña <strong>"Finiquito"</strong>: calcula lo que corresponde pagar cuando un '
                    . 'colaborador causa baja.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Academia',
                'roles' => self::TECNICOS,
                'shot' => 'academia',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/academia</code> — cursos de capacitación.</p>'
                    . '<ul>'
                    . '<li>Pestaña <strong>"Catálogo de cursos"</strong> (la que abre por default): tarjetas por '
                    . 'curso, cada una con una etiqueta de a quién le corresponde ("técnicos" o "general") y un botón '
                    . '<strong>"Ver curso"</strong> para entrar a tomarlo; el filtro de arriba acota por '
                    . 'departamento.</li>'
                    . '<li>Pestaña <strong>"Mis certificaciones"</strong>: los cursos que el colaborador que mira ya '
                    . 'completó y certificó.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Niveles',
                'roles' => self::TECNICOS,
                'shot' => 'niveles',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/niveles</code> — la escala de experiencia '
                    . '(junior, senior, etc.) y qué se necesita para subir de un nivel a otro.</p>'
                    . '<ul>'
                    . '<li>Pestaña <strong>"Colaboradores"</strong> (la que abre por default): se elige un '
                    . 'colaborador del desplegable para ver su nivel actual y su avance hacia el siguiente.</li>'
                    . '<li>Pestaña <strong>"Definición de niveles"</strong>: la lista de niveles que existen y sus '
                    . 'requisitos.</li>'
                    . '<li>Pestaña <strong>"Gating"</strong>: qué le queda bloqueado a un colaborador hasta que '
                    . 'alcance cierto nivel.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Dashboard de Talento',
                'roles' => self::TECNICOS,
                'shot' => 'dashboard-de-talento',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/dashboard</code> — panorama general del '
                    . 'equipo.</p>'
                    . '<ul>'
                    . '<li>4 tarjetas arriba: colaboradores activos, asistencia de hoy, órdenes de hoy y alertas.</li>'
                    . '<li>Pestaña <strong>"Producción diaria"</strong> (la que abre por default): una gráfica con '
                    . 'selector de días (7/30/etc.) y 3 casillas para incluir/quitar instalaciones, garantías '
                    . 'recientes y garantías antiguas.</li>'
                    . '<li>Pestaña <strong>"Mi panel"</strong>: el resumen personal de quien mira, si también es '
                    . 'colaborador. Pestaña <strong>"Calculadora de pago"</strong>: simula cuánto pagaría cierta '
                    . 'producción bajo una regla de compensación. Pestaña <strong>"Mi equipo"</strong>: el resumen de '
                    . 'los colaboradores a su cargo.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Escalafón',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'escalafon',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/escalafon</code> — el ranking de colaboradores '
                    . 'por desempeño.</p>'
                    . '<ul>'
                    . '<li>El selector <strong>"Semanas"</strong> (arriba a la derecha) cambia la ventana de tiempo '
                    . 'que se está midiendo.</li>'
                    . '<li>Pestaña <strong>"Posiciones"</strong> (la que abre por default): tabla con posición, '
                    . 'colaborador, nivel, estrellas, puntaje total y el desglose por categoría (cuota, calidad, '
                    . 'salud de red, apego a normas, asistencia) — los primeros 3 lugares llevan medalla.</li>'
                    . '<li>Pestaña <strong>"Más mejorado"</strong>: quién subió más posiciones respecto al periodo '
                    . 'anterior.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Colaboradores con roles múltiples',
                'roles' => self::TECNICOS,
                'shot' => 'embajadores',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/embajadores-colabs</code> — <strong>vista de '
                    . 'solo lectura</strong>: un colaborador puede además ser cliente/embajador (programa de '
                    . 'referidos) o vendedor en otros módulos del sistema; esta pantalla muestra ese cruce sin '
                    . 'modificar ninguna de esas tablas.</p>'
                    . '<ul>'
                    . '<li>El buscador de arriba filtra por colaborador.</li>'
                    . '<li>La tabla muestra, por colaborador: tipo, si es embajador (y cuántos referidos y comisiones '
                    . 'acumuladas si lo es), si es vendedor (y sus ventas de las últimas 4 semanas si lo es).</li>'
                    . '<li>El ícono de <strong>ojo</strong> al final de cada fila abre el detalle de ese cruce.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Mis ventas y ranking de ventas',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'mis-ventas-y-ranking-de-ventas',
                'content' => '<p><strong>Rutas:</strong> <code>/talento/mis-ventas</code> y '
                    . '<code>/talento/ventas/ranking</code> — vista de <strong>solo lectura</strong>: las cifras se '
                    . 'calculan con el mismo motor que usa el dashboard del módulo Vendedores, filtradas a las '
                    . 'ventas y prospectos propios de quien mira (o de todos, en el ranking).</p>'
                    . '<p>Si el colaborador que se está viendo no tiene una cuenta de vendedor asociada, la pantalla '
                    . 'lo dice explícitamente en vez de mostrar números a medias — no hay nada más que hacer ahí '
                    . 'hasta que se le active esa cuenta.</p>',
            ],
            [
                'title' => 'Artículos de vendedor',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'articulos-de-vendedor',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/articulos-vendedor</code> — catálogo '
                    . '<strong>de solo lectura</strong> (reusa el inventario del módulo Vendedores) de qué artículo '
                    . 'tiene asignado cada vendedor.</p>'
                    . '<p>El buscador filtra por artículo o vendedor; la tabla muestra artículo, tipo, categoría, '
                    . 'cantidad, condición, a qué vendedor está asignado y desde cuándo.</p>',
            ],
            [
                'title' => 'Caja de vendedor',
                'roles' => ['CONTADOR'],
                'shot' => 'caja-de-vendedor',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/caja-vendedor</code> — vista '
                    . '<strong>replicada</strong> de la caja diaria de efectivo que ya usa el módulo Vendedores: lee '
                    . 'y escribe las mismas tablas, sin un motor de dinero aparte.</p>'
                    . '<p>Se busca al colaborador en el desplegable <strong>"Colaborador"</strong> para ver sus '
                    . 'cajas — apertura, cierre, ingresos extra, observaciones y gastos a proveedor, con su '
                    . 'comprobante en PDF, igual que en Vendedores.</p>',
            ],
            [
                'title' => 'Comisiones',
                'roles' => ['Mostrador', 'Vendedor', 'TECNICO', 'Almacen'],
                'shot' => 'comisiones',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/comisiones</code> — vista '
                    . '<strong>replicada</strong> de comisiones de Vendedores: usa el mismo motor de cálculo y las '
                    . 'mismas tablas, sin un motor de dinero paralelo.</p>'
                    . '<p>Se elige un colaborador en el desplegable para ver sus reglas de comisión asignadas, sus '
                    . 'pagos registrados y su estado de cuenta. Si el colaborador elegido no tiene una cuenta de '
                    . 'vendedor asociada, la pantalla lo avisa claramente en vez de mostrar datos a medias.</p>',
            ],
            [
                'title' => 'Documentos pendientes',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'shot' => 'documentos-pendientes',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/documentos-pendientes</code> — vista de '
                    . '<strong>solo lectura</strong> de todos los documentos con firma pendiente, de TODOS los '
                    . 'colaboradores a la vez (no solo de uno).</p>'
                    . '<ul>'
                    . '<li>3 tarjetas arriba: total de pendientes, cuántos llevan más de 7 días y cuántos más de 30.</li>'
                    . '<li><strong>"Actualizar"</strong> refresca la lista.</li>'
                    . '<li>La tabla agrupa los documentos por colaborador, con días pendientes y última acción. La '
                    . 'única acción disponible por fila es <strong>"Recordar"</strong>, que le manda un recordatorio '
                    . 'por WhatsApp — esta pantalla no firma ni edita nada, solo avisa.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi día',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-mi-dia',
                'content' => '<p><strong>Ruta:</strong> <code>/talento/portal</code> — lo primero que ve el '
                    . 'colaborador al entrar a su propio portal.</p>'
                    . '<ul>'
                    . '<li><strong>"Registrar entrada"</strong> marca su asistencia del día usando su ubicación '
                    . 'para validar que esté dentro de la geocerca configurada.</li>'
                    . '<li>Abajo, "Órdenes de hoy": sus órdenes de trabajo agendadas para hoy, con cliente, '
                    . 'dirección y estado; tocar una la abre para completar su checklist de evidencias.</li>'
                    . '</ul>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi dinero',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-mi-dinero',
                'content' => '<p>Dentro del Portal de Colaborador — su propia compensación, con 4 pestañas.</p>'
                    . '<ul>'
                    . '<li><strong>"Cuenta"</strong> (la que abre por default): neto, abonos y descuentos del período '
                    . 'de pago actual. Mientras la semana sigue abierta se ve en $0.00 con el aviso "la cuenta se '
                    . 'llena cuando se cierra la semana de pago" — no es un error, es que todavía no se ha calculado '
                    . 'esa semana.</li>'
                    . '<li><strong>"Desglose"</strong>: la producción del período en tiempo real (antes de que '
                    . 'cierre la semana), unidad por unidad.</li>'
                    . '<li><strong>"Fondo"</strong>: su fondo de ahorro acumulado.</li>'
                    . '<li><strong>"Préstamos"</strong>: sus préstamos activos y su descuento semanal.</li>'
                    . '</ul>'
                    . '<p>Son los mismos números exactos que ve Recursos Humanos desde Liquidaciones — nunca un '
                    . 'cálculo aparte.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi material',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-mi-material',
                'content' => '<p>Dentro del Portal de Colaborador — de <strong>solo lectura</strong>: qué tiene el '
                    . 'colaborador en resguardo ahora mismo, en dos pestañas, <strong>"Herramienta"</strong> y '
                    . '<strong>"Material"</strong>, cada artículo con su cantidad. Tocar uno despliega el detalle. '
                    . 'Es el mismo dato que Custodia en el panel de administración, visto desde su propio lado.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mis prospectos',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-mis-prospectos',
                'content' => '<p>Dentro del Portal de Colaborador — para quien también vende: sus prospectos de CRM '
                    . 'asignados, con su estado de seguimiento. Si no tiene ninguno asignado, la pantalla lo dice '
                    . 'claramente ("No tienes prospectos asignados") en vez de quedar en blanco.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mis documentos',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-mis-documentos',
                'content' => '<p>Dentro del Portal de Colaborador — sus propios documentos laborales (contratos, '
                    . 'acuses, reglamentos): los pendientes de firmar (se firman directamente ahí) y los ya '
                    . 'firmados. Si no tiene ninguno todavía, lo dice claramente en vez de quedar en blanco.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Perfil',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'shot' => 'portal-perfil',
                'content' => '<p>Dentro del Portal de Colaborador — nombre, puesto y correo del colaborador; el '
                    . 'interruptor <strong>"Modo oscuro"</strong> cambia el tema de toda su sesión del portal; el '
                    . 'botón <strong>"Cerrar sesión"</strong> (en rojo) sale de la cuenta.</p>',
            ],
        ];
    }
}
