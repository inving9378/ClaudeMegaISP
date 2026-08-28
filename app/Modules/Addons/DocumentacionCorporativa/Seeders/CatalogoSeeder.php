<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Seeders;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra los 14 apartados y los 139 conceptos del expediente corporativo.
 *
 * IDEMPOTENTE: `updateOrCreate` por (empresa_id, clave) y (empresa_id, slug).
 * Correrlo N veces no duplica nada y refresca la definición de cada concepto.
 *
 * Los conceptos sembrados NO se borran nunca: se desactivan con `activo = false`.
 * El usuario puede agregar los suyos, que este seeder no toca (sólo escribe los
 * slugs que conoce).
 *
 * TIPO DE RESOLVEDOR — criterio: se mapea por la FUENTE REAL del dato, no por la
 * etiqueta literal de la solicitud. Todo lo que se lee de una tabla `dc_*` propia
 * del módulo es `inventario` (un solo driver genérico); lo que se lee de OTRO
 * módulo del sistema es `sistema`/`grafica` con su clave en `FuenteRegistry`.
 * La solicitud usaba "sistema" para ambas cosas de forma inconsistente; unificar
 * por fuente evita registrar a mano ~20 fuentes para tablas que el módulo ya posee.
 */
class CatalogoSeeder extends Seeder
{
    public const TOTAL_CONCEPTOS = 139;

    public function run(): void
    {
        $empresas = DcEmpresa::activas()->get();

        if ($empresas->isEmpty()) {
            throw new RuntimeException(
                'No hay ninguna empresa activa en `dc_empresas`. Corre primero EmpresaSeeder.'
            );
        }

        $definicion = $this->definicion();
        $this->verificarTotal($definicion);

        foreach ($empresas as $empresa) {
            DB::transaction(function () use ($empresa, $definicion) {
                $ordenApartado = 0;
                $numero        = 0;

                foreach ($definicion as $clave => $apartadoDef) {
                    $ordenApartado++;

                    $apartado = DcApartado::withTrashed()->updateOrCreate(
                        ['empresa_id' => $empresa->id, 'clave' => $clave],
                        [
                            'nombre'      => $apartadoDef['nombre'],
                            'descripcion' => $apartadoDef['descripcion'],
                            'icono'       => $apartadoDef['icono'],
                            'orden'       => $ordenApartado,
                            'deleted_at'  => null,
                        ]
                    );

                    $ordenConcepto = 0;
                    foreach ($apartadoDef['conceptos'] as $c) {
                        $ordenConcepto++;
                        $numero++;

                        DcConcepto::withTrashed()->updateOrCreate(
                            ['empresa_id' => $empresa->id, 'slug' => $c['s']],
                            [
                                'apartado_id'           => $apartado->id,
                                'nombre'                => $numero . '. ' . $c['n'],
                                'tipo_resolvedor'       => $c['t'],
                                'config'                => $c['c'] ?? null,
                                'obligatorio'           => (bool) ($c['o'] ?? false),
                                'rol_responsable'       => $c['r'] ?? null,
                                'periodicidad_revision' => $c['p'] ?? null,
                                'base_legal'            => $c['b'] ?? null,
                                'confidencialidad'      => $c['x'] ?? 'interna',
                                'orden'                 => $ordenConcepto,
                                'deleted_at'            => null,
                            ]
                        );
                    }
                }
            });
        }
    }

    private function verificarTotal(array $definicion): void
    {
        $total = 0;
        $slugs = [];

        foreach ($definicion as $clave => $apartado) {
            foreach ($apartado['conceptos'] as $c) {
                $total++;
                $slugs[] = $c['s'];
            }
        }

        if ($total !== self::TOTAL_CONCEPTOS) {
            throw new RuntimeException(
                'El catálogo debe tener ' . self::TOTAL_CONCEPTOS . " conceptos; tiene {$total}."
            );
        }

        $duplicados = array_keys(array_filter(array_count_values($slugs), fn ($n) => $n > 1));
        if ($duplicados !== []) {
            throw new RuntimeException('Slugs duplicados en el catálogo: ' . implode(', ', $duplicados));
        }
    }

    /**
     * Los 14 apartados con sus conceptos, en el orden y numeración de la solicitud.
     *
     * Claves de cada concepto:
     *   n = nombre · s = slug · t = tipo_resolvedor · c = config
     *   o = obligatorio · p = periodicidad_revision · b = base_legal
     *   x = confidencialidad · r = rol_responsable
     */
    private function definicion(): array
    {
        return [
            'I' => [
                'nombre'      => 'Documentación corporativa y societaria',
                'descripcion' => 'Acta constitutiva, estatutos, libros corporativos, poderes y estructura accionaria.',
                'icono'       => 'briefcase',
                'conceptos'   => [
                    ['n' => 'Acta constitutiva y sus modificaciones', 's' => 'acta-constitutiva-y-modificaciones', 't' => 'documento', 'o' => true, 'p' => 'evento'],
                    ['n' => 'Estatutos sociales vigentes', 's' => 'estatutos-sociales-vigentes', 't' => 'documento', 'o' => true, 'p' => 'evento'],
                    ['n' => 'Libro de registro de acciones', 's' => 'libro-registro-de-acciones', 't' => 'inventario', 'c' => ['tabla' => 'dc_accionistas'], 'o' => true, 'p' => 'evento'],
                    ['n' => 'Libro de variaciones de capital', 's' => 'libro-variaciones-de-capital', 't' => 'inventario', 'c' => ['tabla' => 'dc_capital_variaciones'], 'o' => true, 'p' => 'evento'],
                    ['n' => 'Libro de actas de asamblea', 's' => 'libro-actas-de-asamblea', 't' => 'inventario', 'c' => ['tabla' => 'dc_actas', 'filtros' => ['tipo' => ['asamblea_ordinaria', 'asamblea_extraordinaria']]], 'o' => true, 'p' => 'evento'],
                    ['n' => 'Libro de sesiones del consejo de administración', 's' => 'libro-sesiones-consejo-administracion', 't' => 'inventario', 'c' => ['tabla' => 'dc_actas', 'filtros' => ['tipo' => 'consejo']], 'o' => true, 'p' => 'evento'],
                    ['n' => 'Poderes otorgados y vigentes', 's' => 'poderes-otorgados-y-vigentes', 't' => 'inventario', 'c' => ['tabla' => 'dc_poderes'], 'o' => true, 'p' => 'semestral'],
                    ['n' => 'Actas protocolizadas', 's' => 'actas-protocolizadas', 't' => 'documento', 'o' => true, 'p' => 'evento'],
                    ['n' => 'Estructura accionaria actualizada', 's' => 'estructura-accionaria-actualizada', 't' => 'grafica', 'c' => ['fuente' => 'dc.estructura_accionaria'], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Organigrama corporativo', 's' => 'organigrama-corporativo', 't' => 'plantilla', 'o' => true, 'p' => 'anual'],
                ],
            ],

            'II' => [
                'nombre'      => 'Información financiera y contable',
                'descripcion' => 'Estados financieros, balanzas, auxiliares, pólizas, presupuestos y papeles de trabajo.',
                'icono'       => 'trending-up',
                'conceptos'   => [
                    ['n' => 'Estados financieros históricos y actuales', 's' => 'estados-financieros-historicos-y-actuales', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Balanzas de comprobación', 's' => 'balanzas-de-comprobacion', 't' => 'documento', 'o' => true, 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Auxiliares contables', 's' => 'auxiliares-contables', 't' => 'documento', 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Pólizas contables', 's' => 'polizas-contables', 't' => 'documento', 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Relación de activos y pasivos', 's' => 'relacion-de-activos-y-pasivos', 't' => 'plantilla', 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Presupuestos aprobados', 's' => 'presupuestos-aprobados', 't' => 'documento', 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Flujo de efectivo histórico', 's' => 'flujo-de-efectivo-historico', 't' => 'grafica', 'c' => ['fuente' => 'finanzas.flujo_efectivo'], 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Flujo de efectivo proyectado', 's' => 'flujo-de-efectivo-proyectado', 't' => 'documento', 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Estados de resultados', 's' => 'estados-de-resultados', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Estado de situación financiera', 's' => 'estado-de-situacion-financiera', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Papeles de trabajo de auditorías internas o externas', 's' => 'papeles-de-trabajo-de-auditorias', 't' => 'documento', 'p' => 'anual', 'x' => 'restringida'],
                ],
            ],

            'III' => [
                'nombre'      => 'Información fiscal',
                'descripcion' => 'Declaraciones, opinión de cumplimiento, constancia de situación fiscal, acuses y créditos fiscales.',
                'icono'       => 'file-text',
                'conceptos'   => [
                    ['n' => 'Declaraciones mensuales', 's' => 'declaraciones-mensuales', 't' => 'documento', 'o' => true, 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Declaración anual', 's' => 'declaracion-anual', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Declaraciones informativas', 's' => 'declaraciones-informativas', 't' => 'documento', 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Opinión de cumplimiento', 's' => 'opinion-de-cumplimiento', 't' => 'documento', 'o' => true, 'p' => 'mensual', 'b' => 'CFF art. 32-D', 'x' => 'restringida'],
                    ['n' => 'Constancia de situación fiscal', 's' => 'constancia-de-situacion-fiscal', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Acuses de presentación', 's' => 'acuses-de-presentacion', 't' => 'documento', 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Pagos provisionales', 's' => 'pagos-provisionales', 't' => 'documento', 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Créditos fiscales', 's' => 'creditos-fiscales', 't' => 'documento', 'p' => 'evento', 'x' => 'restringida'],
                    ['n' => 'Requerimientos o auditorías de autoridades fiscales', 's' => 'requerimientos-o-auditorias-fiscales', 't' => 'documento', 'p' => 'evento', 'x' => 'restringida'],
                ],
            ],

            'IV' => [
                'nombre'      => 'Cuentas por cobrar, por pagar y flujo',
                'descripcion' => 'Cartera, saldos, antigüedad, proveedores, obligaciones de pago, ingresos y conciliaciones.',
                'icono'       => 'dollar-sign',
                'conceptos'   => [
                    ['n' => 'Cartera de clientes', 's' => 'cartera-de-clientes', 't' => 'sistema', 'c' => ['fuente' => 'clientes.cartera', 'detalle' => 'agregado'], 'b' => 'LFPDPPP', 'x' => 'restringida'],
                    ['n' => 'Saldos pendientes de cobro', 's' => 'saldos-pendientes-de-cobro', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.saldos_pendientes'], 'p' => 'mensual'],
                    ['n' => 'Antigüedad de saldos', 's' => 'antiguedad-de-saldos', 't' => 'grafica', 'c' => ['fuente' => 'finanzas.antiguedad_saldos'], 'p' => 'mensual'],
                    ['n' => 'Relación de proveedores', 's' => 'relacion-de-proveedores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores'], 'p' => 'trimestral'],
                    ['n' => 'Obligaciones pendientes de pago', 's' => 'obligaciones-pendientes-de-pago', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.cuentas_por_pagar'], 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Créditos otorgados o recibidos', 's' => 'creditos-otorgados-o-recibidos', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.creditos'], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Ingresos diarios, semanales y mensuales', 's' => 'ingresos-diarios-semanales-mensuales', 't' => 'grafica', 'c' => ['fuente' => 'finanzas.ingresos_por_periodo'], 'p' => 'mensual'],
                    ['n' => 'Conciliaciones financieras', 's' => 'conciliaciones-financieras', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.conciliaciones'], 'p' => 'mensual', 'x' => 'restringida'],
                ],
            ],

            'V' => [
                'nombre'      => 'Activos, bienes e infraestructura',
                'descripcion' => 'Activos fijos, vehículos, torres, antenas, postería, fibra, bodegas, cómputo y herramientas.',
                'icono'       => 'package',
                'conceptos'   => [
                    ['n' => 'Inventario de activos fijos', 's' => 'inventario-de-activos-fijos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos'], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Vehículos', 's' => 'vehiculos', 't' => 'sistema', 'c' => ['fuente' => 'flotas.vehiculos'], 'p' => 'semestral'],
                    ['n' => 'Equipos de telecomunicaciones', 's' => 'equipos-de-telecomunicaciones', 't' => 'sistema', 'c' => ['fuente' => 'red.equipos'], 'p' => 'semestral'],
                    ['n' => 'Equipos de transmisión', 's' => 'equipos-de-transmision', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'equipo_transmision']], 'p' => 'anual'],
                    ['n' => 'Torres', 's' => 'torres', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'torre'], 'mapa' => true], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Antenas', 's' => 'antenas', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'antena']], 'p' => 'anual'],
                    ['n' => 'Postería', 's' => 'posteria', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'posteria'], 'mapa' => true], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Fibra óptica instalada', 's' => 'fibra-optica-instalada', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'fibra'], 'mapa' => true], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Redes troncales', 's' => 'redes-troncales', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'red_troncal'], 'mapa' => true], 'p' => 'anual'],
                    ['n' => 'Centros de distribución', 's' => 'centros-de-distribucion', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'centro_distribucion'], 'mapa' => true], 'p' => 'anual'],
                    ['n' => 'Almacenes y bodegas', 's' => 'almacenes-y-bodegas', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'bodega'], 'mapa' => true], 'p' => 'anual'],
                    ['n' => 'Equipos de cómputo', 's' => 'equipos-de-computo', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'computo']], 'p' => 'anual'],
                    ['n' => 'Herramientas', 's' => 'herramientas', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos', 'filtros' => ['categoria' => 'herramienta']], 'p' => 'anual'],
                ],
            ],

            'VI' => [
                'nombre'      => 'Contratos y relaciones comerciales',
                'descripcion' => 'Contratos con clientes, proveedores, arrendamiento, servicios, mantenimiento, suministro e interconexión.',
                'icono'       => 'file-plus',
                'conceptos'   => [
                    ['n' => 'Contratos con clientes', 's' => 'contratos-con-clientes', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'cliente']], 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos con proveedores', 's' => 'contratos-con-proveedores', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'proveedor']], 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Convenios comerciales', 's' => 'convenios-comerciales', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'convenio_comercial']], 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos de arrendamiento', 's' => 'contratos-de-arrendamiento', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'arrendamiento']], 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos de servicios', 's' => 'contratos-de-servicios', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'servicios']], 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos de mantenimiento', 's' => 'contratos-de-mantenimiento', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'mantenimiento']], 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos de suministro', 's' => 'contratos-de-suministro', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'suministro']], 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Contratos de interconexión y uso de infraestructura', 's' => 'contratos-de-interconexion-e-infraestructura', 't' => 'inventario', 'c' => ['tabla' => 'dc_contratos', 'filtros' => ['tipo' => 'interconexion']], 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                ],
            ],

            'VII' => [
                'nombre'      => 'Personal y recursos humanos',
                'descripcion' => 'Plantilla laboral, contratos, expedientes y personal externo que interviene en la operación.',
                'icono'       => 'users',
                'conceptos'   => [
                    ['n' => 'Plantilla laboral vigente', 's' => 'plantilla-laboral-vigente', 't' => 'sistema', 'c' => ['fuente' => 'talento.plantilla'], 'o' => true, 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Contratos laborales', 's' => 'contratos-laborales', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Expedientes de empleados', 's' => 'expedientes-de-empleados', 't' => 'documento', 'p' => 'anual', 'b' => 'LFPDPPP', 'x' => 'restringida'],
                    ['n' => 'Prestadores de servicios profesionales', 's' => 'prestadores-de-servicios-profesionales', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos', 'clasificacion' => 'servicios_profesionales'], 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Relación de personal externo en la operación', 's' => 'relacion-de-personal-externo', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos'], 'o' => true, 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Técnicos especializados', 's' => 'tecnicos-especializados', 't' => 'sistema', 'c' => ['fuente' => 'talento.plantilla', 'clasificacion' => 'tecnicos'], 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Programadores', 's' => 'programadores', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos', 'clasificacion' => 'programadores'], 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Contadores', 's' => 'contadores', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos', 'clasificacion' => 'contadores'], 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Consultores', 's' => 'consultores', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos', 'clasificacion' => 'consultores'], 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Capacitadores', 's' => 'capacitadores', 't' => 'sistema', 'c' => ['fuente' => 'talento.externos', 'clasificacion' => 'capacitadores'], 'p' => 'semestral', 'x' => 'restringida'],
                ],
            ],

            'VIII' => [
                'nombre'      => 'Activos digitales y tecnológicos',
                'descripcion' => 'Sistemas, plataformas, software propio, servidores, bases de datos, licencias, respaldos y accesos.',
                'icono'       => 'server',
                'conceptos'   => [
                    ['n' => 'Sistemas administrativos', 's' => 'sistemas-administrativos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'sistema']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Sistemas operativos internos', 's' => 'sistemas-operativos-internos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'sistema']], 'p' => 'anual'],
                    ['n' => 'Plataformas de gestión', 's' => 'plataformas-de-gestion', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'plataforma']], 'p' => 'anual'],
                    ['n' => 'Software desarrollado para la empresa', 's' => 'software-desarrollado-para-la-empresa', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'software_propio']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Servidores físicos o virtuales', 's' => 'servidores-fisicos-o-virtuales', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'servidor']], 'o' => true, 'p' => 'semestral'],
                    ['n' => 'Bases de datos', 's' => 'bases-de-datos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'base_datos']], 'o' => true, 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Aplicaciones móviles', 's' => 'aplicaciones-moviles', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'app_movil']], 'p' => 'anual'],
                    ['n' => 'Sitios web', 's' => 'sitios-web', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'sitio_web']], 'p' => 'anual'],
                    ['n' => 'Paneles de administración', 's' => 'paneles-de-administracion', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'panel']], 'p' => 'anual'],
                    ['n' => 'Licencias de software', 's' => 'licencias-de-software', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'licencia']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Respaldos de información', 's' => 'respaldos-de-informacion', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'respaldo']], 'o' => true, 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Credenciales de acceso', 's' => 'credenciales-de-acceso', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'sin_secretos' => true], 'o' => true, 'p' => 'trimestral', 'x' => 'restringida'],
                ],
            ],

            'IX' => [
                'nombre'      => 'Marcas, propiedad intelectual y activos digitales',
                'descripcion' => 'Registros de marca, derechos de autor, manuales, dominios, correos y redes corporativas.',
                'icono'       => 'award',
                'conceptos'   => [
                    ['n' => 'Títulos de registro de marca', 's' => 'titulos-de-registro-de-marca', 't' => 'documento', 'o' => true, 'p' => 'anual', 'b' => 'Ley Federal de Protección a la Propiedad Industrial'],
                    ['n' => 'Solicitudes de marca en trámite', 's' => 'solicitudes-de-marca-en-tramite', 't' => 'documento', 'p' => 'trimestral'],
                    ['n' => 'Derechos de autor', 's' => 'derechos-de-autor', 't' => 'documento', 'p' => 'anual', 'b' => 'Ley Federal del Derecho de Autor'],
                    ['n' => 'Manuales operativos', 's' => 'manuales-operativos', 't' => 'documento', 'p' => 'anual'],
                    ['n' => 'Manuales técnicos', 's' => 'manuales-tecnicos', 't' => 'documento', 'p' => 'anual'],
                    ['n' => 'Diseños y desarrollos tecnológicos', 's' => 'disenos-y-desarrollos-tecnologicos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'software_propio']], 'p' => 'anual'],
                    ['n' => 'Dominios de internet', 's' => 'dominios-de-internet', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'dominio']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Correos electrónicos corporativos', 's' => 'correos-electronicos-corporativos', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'correo_corporativo']], 'p' => 'anual'],
                    ['n' => 'Redes sociales corporativas', 's' => 'redes-sociales-corporativas', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'red_social']], 'p' => 'anual'],
                    ['n' => 'Plataformas publicitarias y de marketing digital', 's' => 'plataformas-publicitarias-y-marketing', 't' => 'inventario', 'c' => ['tabla' => 'dc_activos_digitales', 'filtros' => ['tipo' => 'plataforma_marketing']], 'p' => 'anual'],
                ],
            ],

            'X' => [
                'nombre'      => 'Proveedores estratégicos y contactos operativos',
                'descripcion' => 'Padrón de proveedores clasificado: telecomunicaciones, tecnología, contratistas y asesores.',
                'icono'       => 'truck',
                'conceptos'   => [
                    ['n' => 'Proveedores de telecomunicaciones', 's' => 'proveedores-de-telecomunicaciones', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'telecomunicaciones'], 'o' => true, 'p' => 'semestral'],
                    ['n' => 'Proveedores tecnológicos', 's' => 'proveedores-tecnologicos', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'tecnologia'], 'o' => true, 'p' => 'semestral'],
                    ['n' => 'Contratistas', 's' => 'contratistas', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'contratistas'], 'p' => 'semestral'],
                    ['n' => 'Programadores (proveedores)', 's' => 'proveedores-programadores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'programadores'], 'p' => 'semestral'],
                    ['n' => 'Desarrolladores', 's' => 'proveedores-desarrolladores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'desarrolladores'], 'p' => 'semestral'],
                    ['n' => 'Capacitadores (proveedores)', 's' => 'proveedores-capacitadores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'capacitadores'], 'p' => 'semestral'],
                    ['n' => 'Asesores', 's' => 'proveedores-asesores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'asesores'], 'p' => 'semestral'],
                    ['n' => 'Contadores (proveedores)', 's' => 'proveedores-contadores', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'contadores'], 'p' => 'semestral'],
                    ['n' => 'Despachos externos', 's' => 'despachos-externos', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'despachos'], 'p' => 'semestral'],
                    ['n' => 'Contactos estratégicos vinculados a la operación', 's' => 'contactos-estrategicos-operacion', 't' => 'sistema', 'c' => ['fuente' => 'finanzas.proveedores', 'clasificacion' => 'estrategicos'], 'p' => 'semestral'],
                ],
            ],

            'XI' => [
                'nombre'      => 'Cuentas bancarias, firmas y productos financieros',
                'descripcion' => 'Instituciones, cuentas, firmas autorizadas, créditos, terminales y accesos. Nunca credenciales.',
                'icono'       => 'credit-card',
                'conceptos'   => [
                    ['n' => 'Institución financiera', 's' => 'institucion-financiera', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_bancaria']], 'o' => true, 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Número de cuenta (solo últimos 4 dígitos)', 's' => 'numero-de-cuenta', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_bancaria'], 'solo_ultimos_4' => true], 'o' => true, 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'CLABE interbancaria (solo últimos 4 dígitos)', 's' => 'clabe-interbancaria', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_bancaria'], 'solo_ultimos_4' => true, 'sin_secretos' => true], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Tipo de cuenta', 's' => 'tipo-de-cuenta', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_bancaria']], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Saldos actualizados', 's' => 'saldos-actualizados', 't' => 'documento', 'p' => 'mensual', 'x' => 'critica'],
                    ['n' => 'Estados de cuenta históricos', 's' => 'estados-de-cuenta-historicos', 't' => 'documento', 'o' => true, 'p' => 'mensual', 'x' => 'critica'],
                    ['n' => 'Contratos de apertura', 's' => 'contratos-de-apertura', 't' => 'documento', 'p' => 'evento', 'x' => 'critica'],
                    ['n' => 'Créditos y financiamientos', 's' => 'creditos-y-financiamientos', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'linea_credito']], 'p' => 'trimestral', 'x' => 'critica'],
                    ['n' => 'Líneas de crédito vigentes', 's' => 'lineas-de-credito-vigentes', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'linea_credito']], 'p' => 'trimestral', 'x' => 'critica'],
                    ['n' => 'Instrumentos financieros', 's' => 'instrumentos-financieros', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_inversion']], 'p' => 'trimestral', 'x' => 'critica'],
                    ['n' => 'Cuentas de inversión', 's' => 'cuentas-de-inversion', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'cuenta_inversion']], 'p' => 'trimestral', 'x' => 'critica'],
                    ['n' => 'Terminales punto de venta', 's' => 'terminales-punto-de-venta', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'terminal_pv']], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Plataformas de cobro electrónico', 's' => 'plataformas-de-cobro-electronico', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'usuario_sistema']], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Firmas autorizadas', 's' => 'firmas-autorizadas', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'firma_autorizada']], 'o' => true, 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Poderes para manejo de recursos', 's' => 'poderes-para-manejo-de-recursos', 't' => 'inventario', 'c' => ['tabla' => 'dc_poderes', 'filtros' => ['tipo_poder' => 'actos_de_dominio']], 'o' => true, 'p' => 'semestral', 'x' => 'restringida'],
                    ['n' => 'Usuarios bancarios', 's' => 'usuarios-bancarios', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'usuario_sistema'], 'sin_secretos' => true], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Tokens físicos o digitales', 's' => 'tokens-fisicos-o-digitales', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'token'], 'sin_secretos' => true], 'p' => 'trimestral', 'x' => 'restringida'],
                    ['n' => 'Aplicaciones y accesos electrónicos', 's' => 'aplicaciones-y-accesos-electronicos', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'filtros' => ['tipo' => 'usuario_sistema'], 'sin_secretos' => true], 'p' => 'trimestral', 'x' => 'restringida'],
                ],
            ],

            'XII' => [
                'nombre'      => 'Confidencialidad, conservación y no retención',
                'descripcion' => 'Política de confidencialidad, NDA firmados, bitácora de accesos, entrega-recepción y offboarding.',
                'icono'       => 'shield',
                'conceptos'   => [
                    ['n' => 'Política de confidencialidad de información corporativa', 's' => 'politica-de-confidencialidad', 't' => 'plantilla', 'o' => true, 'p' => 'anual'],
                    ['n' => 'Convenios de confidencialidad (NDA) firmados', 's' => 'convenios-de-confidencialidad-nda', 't' => 'documento', 'o' => true, 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Bitácora de accesos a información corporativa', 's' => 'bitacora-de-accesos', 't' => 'inventario', 'c' => ['tabla' => 'dc_accesos_log'], 'o' => true, 'p' => 'mensual', 'x' => 'restringida'],
                    ['n' => 'Actas de entrega-recepción', 's' => 'actas-de-entrega-recepcion', 't' => 'plantilla', 'o' => true, 'p' => 'evento', 'x' => 'restringida'],
                    ['n' => 'Checklist de offboarding y revocación de accesos', 's' => 'checklist-de-offboarding', 't' => 'inventario', 'c' => ['tabla' => 'dc_inventario_accesos', 'vista' => 'offboarding'], 'o' => true, 'p' => 'evento', 'x' => 'restringida'],
                    ['n' => 'Registro de transferencias de información autorizadas', 's' => 'registro-de-transferencias-autorizadas', 't' => 'inventario', 'c' => ['tabla' => 'dc_entregas'], 'p' => 'evento', 'x' => 'restringida'],
                ],
            ],

            'XIII' => [
                'nombre'      => 'Títulos de concesión, permisos y derechos de uso',
                'descripcion' => 'El único apartado donde un descuido apaga la operación: concesiones, permisos, derechos de vía y sus vigencias.',
                'icono'       => 'key',
                'conceptos'   => [
                    ['n' => 'Títulos de concesión', 's' => 'titulos-de-concesion', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'titulo_concesion']], 'o' => true, 'p' => 'anual', 'b' => 'Ley Federal de Telecomunicaciones y Radiodifusión'],
                    ['n' => 'Permisos regulatorios', 's' => 'permisos-regulatorios', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'permiso']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Autorizaciones gubernamentales', 's' => 'autorizaciones-gubernamentales', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'autorizacion']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Derechos de vía', 's' => 'derechos-de-via', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'derecho_via']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Convenios de uso de infraestructura', 's' => 'convenios-de-uso-de-infraestructura', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'convenio_infraestructura']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Contratos de arrendamiento de sitios', 's' => 'contratos-de-arrendamiento-de-sitios', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'arrendamiento_sitio']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Permisos para postes, torres, ductos y redes', 's' => 'permisos-postes-torres-ductos-redes', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'permiso']], 'o' => true, 'p' => 'anual'],
                    ['n' => 'Convenios con entidades públicas o privadas', 's' => 'convenios-con-entidades-publicas-o-privadas', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['tipo' => 'convenio_infraestructura']], 'p' => 'anual'],
                    ['n' => 'Expedientes regulatorios', 's' => 'expedientes-regulatorios', 't' => 'documento', 'p' => 'anual', 'x' => 'restringida'],
                    ['n' => 'Trámites en proceso', 's' => 'tramites-en-proceso', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['estado_tramite' => 'en_tramite']], 'p' => 'mensual'],
                    ['n' => 'Renovaciones pendientes', 's' => 'renovaciones-pendientes', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'filtros' => ['estado_tramite' => 'en_renovacion']], 'p' => 'mensual'],
                    ['n' => 'Obligaciones de cumplimiento regulatorio', 's' => 'obligaciones-de-cumplimiento-regulatorio', 't' => 'inventario', 'c' => ['tabla' => 'dc_concesiones', 'vista' => 'obligaciones'], 'o' => true, 'p' => 'trimestral'],
                ],
            ],

            'XIV' => [
                'nombre'      => 'Reserva de derechos y trazabilidad',
                'descripcion' => 'Qué se pidió, quién lo pidió, qué se entregó y con qué acuse. El expediente del expediente.',
                'icono'       => 'archive',
                'conceptos'   => [
                    ['n' => 'Registro de solicitudes de información recibidas', 's' => 'registro-de-solicitudes-recibidas', 't' => 'inventario', 'c' => ['tabla' => 'dc_solicitudes'], 'o' => true, 'p' => 'evento', 'x' => 'restringida'],
                    ['n' => 'Paquetes entregados con acuse y hash', 's' => 'paquetes-entregados-con-acuse-y-hash', 't' => 'inventario', 'c' => ['tabla' => 'dc_entregas'], 'o' => true, 'p' => 'evento', 'x' => 'restringida'],
                ],
            ],
        ];
    }
}
