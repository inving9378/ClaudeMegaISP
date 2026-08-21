<?php

use App\Modules\Addons\Empresa\Models\ManualChapter;
use App\Modules\Addons\Empresa\Models\ManualSection;
use App\Modules\Addons\Empresa\Models\ManualSectionVersion;
use Illuminate\Database\Migrations\Migration;

/**
 * #838 — migra el contenido que antes vivía hardcodeado en manual.blade.php
 * (Parte A, #795) a las tablas nuevas, para que "editable, contenido en BD,
 * nunca como archivos Blade sueltos" sea cierto desde el primer commit. Cada
 * sección nace con una versión publicada (v1) igual al texto que ya se veía
 * en producción — no se pierde nada, solo se vuelve editable.
 *
 * Idempotente por slug (firstOrCreate); down() no borra contenido ya editado
 * por un usuario real, solo lo creado aquí si nunca se tocó (mismo criterio
 * conservador que otras migraciones de seed del proyecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pend = fn (string $texto) => '<div class="pend">' . $texto . '</div>';
        $docref = fn (string $codigo, string $titulo) => '<a class="docref" href="#"><b>' . $codigo . '</b> · ' . $titulo . '</a>';

        $chapters = [
            [
                'slug' => 'identidad',
                'title' => 'Identidad',
                'sections' => [
                    ['slug' => 'quienes-somos', 'title' => 'Quiénes somos', 'content' =>
                        '<p>Meganet Telecomunicaciones es un proveedor de servicios de Internet que '
                        . 'opera red propia de fibra y enlaces inalámbricos, con atención directa a '
                        . 'clientes residenciales y empresariales en su zona de cobertura.</p>'],
                    ['slug' => 'historia', 'title' => 'Historia', 'content' =>
                        $pend('Pendiente de redacción — dirección aportará el texto.')],
                    ['slug' => 'datos-empresa', 'title' => 'Datos de la empresa', 'content' =>
                        $pend('Pendiente de redacción — razón social, RFC, domicilio fiscal y datos de contacto oficiales.')],
                ],
            ],
            [
                'slug' => 'mision',
                'title' => 'Misión',
                'sections' => [
                    ['slug' => 'mision', 'title' => 'Misión', 'content' =>
                        '<p>Conectar a nuestra comunidad con un servicio de Internet estable, honesto '
                        . 'y bien atendido, sostenido por una red propia y un equipo que responde.</p>'
                        . $pend('Texto de muestra — sustituir por la misión oficial aprobada.')],
                ],
            ],
            [
                'slug' => 'vision',
                'title' => 'Visión',
                'sections' => [
                    ['slug' => 'vision', 'title' => 'Visión', 'content' =>
                        '<p>Ser el operador de referencia en nuestra región por calidad de red y por '
                        . 'trato al cliente, con cobertura ampliada y operación medible.</p>'
                        . $pend('Texto de muestra — sustituir por la visión oficial aprobada.')],
                ],
            ],
            [
                'slug' => 'valores',
                'title' => 'Valores',
                'sections' => [
                    ['slug' => 'valores', 'title' => 'Valores', 'content' =>
                        '<p>Cumplimiento de lo prometido · Trato directo y claro · Cuidado de la red · '
                        . 'Responsabilidad sobre el trabajo propio · Mejora continua.</p>'],
                ],
            ],
            [
                'slug' => 'objetivos',
                'title' => 'Objetivos estratégicos',
                'sections' => [
                    ['slug' => 'objetivos-generales', 'title' => 'Objetivos generales', 'content' => $pend('Pendiente de redacción.')],
                    ['slug' => 'metas-ejercicio', 'title' => 'Metas del ejercicio', 'content' => $pend('Pendiente de redacción.')],
                ],
            ],
            [
                'slug' => 'organizacion',
                'title' => 'Estructura organizacional',
                'sections' => [
                    ['slug' => 'organigrama', 'title' => 'Organigrama', 'content' =>
                        '<p>Dirección General · Operaciones y Red · Soporte Técnico · Instalaciones · '
                        . 'Administración y Cobranza · Comercial.</p>'
                        . $docref('MN-GOB-002', 'Organigrama oficial (PDF)')],
                    ['slug' => 'areas', 'title' => 'Áreas y responsabilidades', 'content' => $pend('Pendiente de redacción.')],
                ],
            ],
            [
                'slug' => 'politicas',
                'title' => 'Políticas',
                'sections' => [
                    ['slug' => 'politica-calidad', 'title' => 'Calidad de servicio', 'content' =>
                        '<p>Parámetros de disponibilidad, tiempos de restablecimiento y criterios de '
                        . 'escalamiento aplicables a toda la operación.</p>'],
                    ['slug' => 'politica-seguridad', 'title' => 'Seguridad de la información', 'content' =>
                        '<p>Manejo de credenciales, accesos a equipos de red, respaldos y '
                        . 'confidencialidad de datos de clientes.</p>'
                        . $docref('MN-POL-002', 'Política de seguridad de la información')],
                    ['slug' => 'politica-privacidad', 'title' => 'Privacidad y datos personales', 'content' =>
                        '<p>Aviso de privacidad, finalidades del tratamiento y ejercicio de derechos ARCO.</p>'],
                    ['slug' => 'politica-recursos', 'title' => 'Uso de recursos y equipo', 'content' => $pend('Pendiente de redacción.')],
                    ['slug' => 'politica-atencion', 'title' => 'Atención al cliente', 'content' => $pend('Pendiente de redacción.')],
                ],
            ],
            [
                'slug' => 'reglamentos',
                'title' => 'Reglamentos',
                'sections' => [
                    ['slug' => 'reglamento-interior', 'title' => 'Reglamento interior de trabajo', 'content' =>
                        '<p>Jornada, asistencia, permisos, obligaciones y sanciones.</p>'
                        . $docref('MN-POL-001', 'Reglamento interior de trabajo (PDF firmado)')],
                    ['slug' => 'codigo-conducta', 'title' => 'Código de conducta', 'content' => $pend('Pendiente de redacción.')],
                    ['slug' => 'seguridad-higiene', 'title' => 'Seguridad e higiene', 'content' =>
                        '<p>Uso de equipo de protección en trabajos en altura, manejo de escaleras y '
                        . 'herramienta, y protocolo ante incidentes.</p>'],
                ],
            ],
            [
                'slug' => 'procedimientos',
                'title' => 'Procedimientos clave',
                'sections' => [
                    ['slug' => 'proc-instalacion', 'title' => 'Instalación', 'content' =>
                        '<p>Resumen del flujo: orden de trabajo → verificación de factibilidad → '
                        . 'tendido y configuración → acta de instalación firmada.</p>'
                        . $docref('MN-OPE-001', 'Manual de instalación')],
                    ['slug' => 'proc-soporte', 'title' => 'Soporte y fallas', 'content' =>
                        '<p>Recepción del reporte, diagnóstico remoto, visita en sitio y cierre con '
                        . 'confirmación del cliente.</p>'],
                    ['slug' => 'proc-cobranza', 'title' => 'Cobranza', 'content' => $pend('Pendiente de redacción.')],
                    ['slug' => 'proc-almacen', 'title' => 'Almacén', 'content' => $pend('Pendiente de redacción.')],
                ],
            ],
            [
                'slug' => 'compromiso',
                'title' => 'Compromiso con el cliente',
                'sections' => [
                    ['slug' => 'sla', 'title' => 'Niveles de servicio', 'content' =>
                        '<p>Tiempos objetivo de respuesta y restablecimiento por tipo de incidencia.</p>'],
                    ['slug' => 'canales', 'title' => 'Canales de atención', 'content' =>
                        '<p>Teléfono, WhatsApp, portal de cliente y atención en oficina.</p>'],
                ],
            ],
            [
                'slug' => 'directorio',
                'title' => 'Directorio',
                'sections' => [
                    ['slug' => 'directorio', 'title' => 'Directorio', 'content' =>
                        '<p>Contactos internos por área, extensiones y responsables de guardia.</p>'
                        . $pend('Pendiente de redacción.')],
                ],
            ],
        ];

        foreach ($chapters as $chapterOrder => $chapterData) {
            $chapter = ManualChapter::firstOrCreate(
                ['slug' => $chapterData['slug']],
                ['title' => $chapterData['title'], 'order' => $chapterOrder + 1]
            );

            foreach ($chapterData['sections'] as $sectionOrder => $sectionData) {
                $section = ManualSection::firstOrCreate(
                    ['chapter_id' => $chapter->id, 'slug' => $sectionData['slug']],
                    ['title' => $sectionData['title'], 'order' => $sectionOrder + 1, 'content' => $sectionData['content']]
                );

                if ($section->wasRecentlyCreated) {
                    $version = ManualSectionVersion::create([
                        'section_id' => $section->id,
                        'version_number' => 1,
                        'content' => $sectionData['content'],
                        'is_published' => true,
                        'published_at' => now(),
                        'created_by' => null,
                        'created_at' => now(),
                    ]);
                    $section->published_version_id = $version->id;
                    $section->save();
                }
            }
        }
    }

    public function down(): void
    {
        // Conservador: no borra contenido. Si el manual nunca se tocó después
        // de sembrarse, `php artisan migrate:rollback` deja las tablas (creadas
        // en la migración anterior) vacías via su propio down(); aquí no hay
        // nada seguro que revertir sin arriesgar ediciones reales de un usuario.
    }
};
