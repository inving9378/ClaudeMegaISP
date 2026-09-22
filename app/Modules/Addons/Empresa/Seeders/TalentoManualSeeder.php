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
 * visible_roles usa ManualSection::ROLES_ASSIGNABLE + ROLE_ADMIN_ONLY,
 * calcado de qué rol real tiene el permiso de cada pantalla en config/
 * route_permission.php y en los permisos ya asignados en BD (super-
 * administrator/DESARROLLADOR/Administrador/ADMINISTRADOR_COMPLETO/Super
 * Administrador siempre ven todo — son ROLES_BYPASS, no hace falta
 * repetirlos aquí).
 */
class TalentoManualSeeder extends Seeder
{
    private const TECNICOS = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];

    public function run(): void
    {
        $chapter = ManualChapter::firstOrCreate(
            ['slug' => 'talento'],
            ['title' => 'Talento', 'order' => (int) ManualChapter::max('order') + 1]
        );

        foreach ($this->sections() as $i => $s) {
            $this->upsertSection($chapter, $i + 1, $s['title'], $s['content'], $s['roles']);
        }
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

    /** @return array<int, array{title:string, content:string, roles:?array}> */
    private function sections(): array
    {
        return [
            [
                'title' => 'Qué es Talento',
                'roles' => null,
                'content' => '<p>Talento es el módulo con todo lo relacionado a quienes trabajan en Meganet: técnicos, '
                    . 'vendedores, mostrador, y cualquier otro colaborador. Junta en un solo lugar su ficha, sus órdenes '
                    . 'de trabajo en campo, cuánto se les paga y por qué, su material asignado, su capacitación y su '
                    . 'documentación laboral.</p>'
                    . '<p>Tiene dos caras: el <strong>panel de administración</strong> (lo que ve quien gestiona al '
                    . 'personal — Recursos Humanos, supervisión) y el <strong>Portal de Colaborador</strong> '
                    . '(<code>/talento/portal</code>, lo que ve cada quien de sí mismo: su día, su dinero, su material). '
                    . 'Cada pantalla de este capítulo dice para quién es.</p>',
            ],
            [
                'title' => 'Colaboradores',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento</code> — la ficha central de cada persona: datos '
                    . 'personales, rol, nivel, estado (activo/baja), y accesos a sus demás pantallas (compensación, '
                    . 'custodia, documentos). Desde aquí se da de alta a un colaborador nuevo y se edita su información.</p>',
            ],
            [
                'title' => 'Órdenes de trabajo',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/ordenes</code> — el trabajo de campo: '
                    . 'instalaciones, soportes, cambios de equipo, reubicaciones y bajas, cada una con su checklist de '
                    . 'evidencias obligatorias (fotos, lectura de dBm, firma del cliente) antes de poder cerrarse. Cada '
                    . 'tipo de orden vale puntos distintos, y esos puntos son la base de la compensación del técnico.</p>',
            ],
            [
                'title' => 'Compensación',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/compensacion</code> — cómo se calcula lo que se '
                    . 'le paga a cada colaborador: puntos por orden de trabajo completada y validada, bono de salud de '
                    . 'red (por buena señal óptica) y comisiones cuando aplican. Es donde Recursos Humanos revisa el '
                    . 'desglose antes de una liquidación.</p>',
            ],
            [
                'title' => 'Liquidaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/liquidaciones</code> — el pago semanal en sí: '
                    . 'genera la liquidación de la semana de pago (sábado 18:00 a sábado 18:00) a partir de todo lo '
                    . 'que el colaborador ganó, y queda como un registro cerrado e irrepetible para esa semana '
                    . '(no se puede volver a liquidar el mismo periodo dos veces).</p>',
            ],
            [
                'title' => 'Custodia',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/custodia</code> — qué herramienta y material '
                    . 'de la empresa tiene cada colaborador bajo su resguardo en este momento (taladro, casco, cable, '
                    . 'etc.), y el historial de qué tuvo antes. Cada colaborador ve lo suyo también desde el Portal, '
                    . 'en "Mi material".</p>',
            ],
            [
                'title' => 'Dispositivos',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/dispositivos</code> — credenciales y equipos '
                    . 'de acceso digital asignados: usuario de sistema, accesos a herramientas internas, y su estado.</p>',
            ],
            [
                'title' => 'Roadmap',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/roadmap</code> — la ruta de crecimiento del '
                    . 'colaborador dentro de la empresa: qué necesita cumplir para subir de nivel o de puesto.</p>',
            ],
            [
                'title' => 'Proyectos',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/proyectos</code> — trabajo de planta externa '
                    . '(tendido de red, no atención a un cliente puntual) organizado por proyecto, con actividades y '
                    . 'puntos propios, distinto de las órdenes de trabajo de campo normales.</p>',
            ],
            [
                'title' => 'Calidad de caja',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/calidad</code> — revisión de calidad sobre las '
                    . 'cajas de corte de vendedores/mostrador: si el efectivo, las notas y los conceptos cuadran, y '
                    . 'penaliza cuando no.</p>',
            ],
            [
                'title' => 'Penalizaciones',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/penalizaciones</code> — el historial de '
                    . 'descuentos o llamadas de atención aplicadas a un colaborador (por caja descuadrada, orden mal '
                    . 'cerrada, etc.) y su efecto sobre la compensación.</p>',
            ],
            [
                'title' => 'Credenciales',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/credenciales</code> — gafetes, uniformes y '
                    . 'credenciales físicas de identificación entregadas al colaborador, con su fecha de entrega y '
                    . 'vigencia.</p>',
            ],
            [
                'title' => 'Paquetes de documentos',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/expediente/paquetes</code> — plantillas de '
                    . 'documentos laborales (contratos, acuses, reglamentos) que se arman en paquete y se mandan a '
                    . 'firmar a uno o varios colaboradores desde su expediente.</p>',
            ],
            [
                'title' => 'Préstamos y Finiquito',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/finiquito</code> — dos cosas en la misma '
                    . 'pantalla: préstamos que la empresa le hace a un colaborador (y su descuento programado de la '
                    . 'nómina), y el cálculo de finiquito cuando un colaborador causa baja.</p>',
            ],
            [
                'title' => 'Academia',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/academia</code> — cursos y material de '
                    . 'capacitación disponibles para el colaborador, con su avance y certificados.</p>',
            ],
            [
                'title' => 'Niveles',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/niveles</code> — los niveles de experiencia '
                    . 'que existen (junior, senior, etc.) y qué requisitos hay que cumplir para subir de uno a otro.</p>',
            ],
            [
                'title' => 'Dashboard de Talento',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/dashboard</code> — panorama general: cuántas '
                    . 'órdenes se completaron, avance de compensación de la semana en curso, y alertas relevantes del '
                    . 'equipo.</p>',
            ],
            [
                'title' => 'Escalafón',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/escalafon</code> — el ranking de colaboradores '
                    . 'por desempeño (puntos, calidad, antigüedad), usado como referencia para ascensos.</p>',
            ],
            [
                'title' => 'Embajadores',
                'roles' => self::TECNICOS,
                'content' => '<p><strong>Ruta:</strong> <code>/talento/embajadores-colabs</code> — el programa de '
                    . 'referidos: colaboradores que además refieren clientes nuevos y ganan comisión por ello. Muestra '
                    . 'sus referidos y comisiones generadas.</p>',
            ],
            [
                'title' => 'Mis ventas y ranking de ventas',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Rutas:</strong> <code>/talento/mis-ventas</code> y '
                    . '<code>/talento/ventas/ranking</code> — ventas cerradas por un vendedor colaborador y el '
                    . 'ranking comparativo entre todos ellos, con los mismos números que usa el módulo Vendedores.</p>',
            ],
            [
                'title' => 'Artículos de vendedor',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/articulos-vendedor</code> — material/artículos '
                    . 'promocionales o de venta entregados a un vendedor (SIMs, folletos, equipos de demostración).</p>',
            ],
            [
                'title' => 'Caja de vendedor',
                'roles' => ['CONTADOR'],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/caja-vendedor</code> — el corte de caja diario '
                    . 'de efectivo de un colaborador con funciones de cobro: apertura, cierre, ingresos extra, '
                    . 'observaciones y gastos a proveedor, con su comprobante en PDF.</p>',
            ],
            [
                'title' => 'Comisiones',
                'roles' => ['Mostrador', 'Vendedor', 'TECNICO', 'Almacen'],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/comisiones</code> — las reglas de comisión '
                    . 'asignadas a un colaborador con cuenta de vendedor, sus pagos registrados y su estado de cuenta '
                    . '(deudas, descuentos). Si el colaborador no tiene una cuenta de vendedor activada, la pantalla '
                    . 'lo dice claramente en vez de mostrar datos a medias.</p>',
            ],
            [
                'title' => 'Documentos pendientes',
                'roles' => [ManualSection::ROLE_ADMIN_ONLY],
                'content' => '<p><strong>Ruta:</strong> <code>/talento/documentos-pendientes</code> — vista '
                    . 'consolidada de TODOS los documentos que faltan por firmar en toda la empresa (no solo los de un '
                    . 'colaborador), para dar seguimiento a quién le falta qué.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi día',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p><strong>Ruta:</strong> <code>/talento/portal</code> — lo primero que ve el '
                    . 'colaborador al entrar a su propio portal: sus órdenes de trabajo del día, con acceso directo a '
                    . 'completarlas, subir evidencia y ver el checklist pendiente.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi dinero',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p>Dentro del Portal de Colaborador — su propia compensación: cuenta acumulada, '
                    . 'desglose de cómo se calculó (mismos números exactos que ve Recursos Humanos, nunca un cálculo '
                    . 'aparte), fondo de ahorro y préstamos activos con su descuento programado.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mi material',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p>Dentro del Portal de Colaborador — de solo lectura: qué herramienta y material tiene '
                    . 'en resguardo ahora mismo, agrupado por tipo (herramienta/material), y su historial. Es el '
                    . 'mismo dato que Custodia, visto desde el lado del colaborador.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mis prospectos',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p>Dentro del Portal de Colaborador — para quien también vende: sus prospectos de CRM '
                    . 'asignados, con su estado de seguimiento.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Mis documentos',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p>Dentro del Portal de Colaborador — documentos laborales propios pendientes de firmar '
                    . 'y ya firmados (contratos, acuses, reglamentos), con firma directamente desde ahí.</p>',
            ],
            [
                'title' => 'Portal de Colaborador — Perfil',
                'roles' => array_merge(self::TECNICOS, ['Vendedor', 'conductor']),
                'content' => '<p>Dentro del Portal de Colaborador — datos de contacto propios, editables por el '
                    . 'colaborador mismo.</p>',
            ],
        ];
    }
}
