<template>
    <div>
        <!-- Lista de registros del concepto ------------------------------------>
        <q-dialog v-model="dialogoLista" full-width>
            <q-card style="max-width: 1000px">
                <q-card-section class="row items-center">
                    <div class="text-h6">{{ conceptoNombre }}</div>
                    <q-space />
                    <q-btn
                        flat
                        dense
                        icon="add"
                        color="primary"
                        label="Agregar"
                        class="q-mr-sm"
                        @click="nuevaFicha"
                    />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 65vh" class="scroll">
                    <q-inner-loading :showing="cargando">
                        <q-spinner size="34px" color="primary" />
                    </q-inner-loading>

                    <div v-if="!cargando && filas.length === 0" class="text-center text-grey q-pa-lg">
                        <i class="bi bi-inbox" style="font-size: 30px"></i>
                        <div class="q-mt-sm">Sin registros todavía.</div>
                    </div>

                    <q-list v-else bordered separator>
                        <q-item v-for="fila in filas" :key="fila.id">
                            <q-item-section>
                                <q-item-label>
                                    <span v-for="(col, idx) in configuracion.columnas" :key="col.name">
                                        <span v-if="idx > 0"> · </span>
                                        <strong v-if="idx === 0">{{ valorColumna(col, fila) }}</strong>
                                        <span v-else>{{ col.label }}: {{ valorColumna(col, fila) }}</span>
                                    </span>
                                </q-item-label>
                                <q-item-label v-if="fila.estado" caption>
                                    <q-badge :color="colorEstadoVigencia(fila.estado)" :label="fila.estado" outline />
                                </q-item-label>
                            </q-item-section>
                            <q-item-section side>
                                <div class="row items-center">
                                    <q-btn flat dense round icon="edit" size="sm" @click="editarFicha(fila)" />
                                    <q-btn flat dense round icon="delete" size="sm" color="negative" @click="confirmarEliminar(fila)" />
                                </div>
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-dialog>

        <!-- Alta / edición de un registro --------------------------------------->
        <q-dialog v-model="dialogoFicha">
            <q-card style="min-width: 420px; max-width: 560px">
                <q-card-section class="row items-center">
                    <div class="text-h6">{{ form.id ? 'Editar registro' : 'Nuevo registro' }}</div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 60vh" class="scroll">
                    <div class="row q-col-gutter-sm">
                        <div v-if="campoTipoFijo" class="col-12 text-caption text-grey">
                            {{ campoTipoFijo.label }}: <strong>{{ etiquetaFijo(campoTipoFijo) }}</strong>
                        </div>
                        <div v-if="recurso === 'activos_digitales' && form.titularidad_estado === 'titularidad_a_regularizar'" class="col-12">
                            <q-banner dense class="bg-warning text-dark rounded-borders">
                                <template v-slot:avatar>
                                    <q-icon name="warning" color="dark" />
                                </template>
                                Titularidad a regularizar
                            </q-banner>
                        </div>
                        <template v-for="campo in camposEditables">
                            <q-select
                                v-if="campo.tipo === 'seleccion'"
                                :key="campo.key"
                                class="col-12"
                                outlined
                                dense
                                v-model="form[campo.key]"
                                :options="campo.opciones"
                                emit-value
                                map-options
                                :label="campo.label"
                            />
                            <q-checkbox
                                v-else-if="campo.tipo === 'booleano'"
                                :key="campo.key"
                                class="col-12"
                                dense
                                v-model="form[campo.key]"
                                :label="campo.label"
                            />
                            <q-input
                                v-else-if="campo.tipo === 'solo_lectura'"
                                :key="campo.key"
                                class="col-12"
                                outlined
                                dense
                                readonly
                                autogrow
                                type="textarea"
                                v-model="form[campo.key]"
                                :label="campo.label"
                            />
                            <q-input
                                v-else
                                :key="campo.key"
                                class="col-12"
                                :class="{ 'col-md-6': campo.tipo === 'fecha' || campo.tipo === 'decimal' || campo.tipo === 'entero' }"
                                outlined
                                dense
                                :type="tipoInput(campo.tipo)"
                                :autogrow="campo.tipo === 'texto_largo'"
                                v-model="form[campo.key]"
                                :label="campo.label"
                            />
                        </template>
                    </div>
                </q-card-section>

                <q-separator />

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" label="Guardar" :loading="guardando" @click="guardarFicha" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
/**
 * Fase 2c (item roadmap #736) — CRUD de los 5 registros estructurados que el
 * catálogo declara tipo `inventario` (accionistas, capital, actas, poderes,
 * contratos). Un solo componente genérico (config por recurso), mismo
 * criterio que `RegistroEstructuradoController` en el backend: el patrón es
 * idéntico en los 5, cinco componentes casi iguales habría sido la
 * abstracción equivocada en sentido contrario.
 *
 * `documento_id` NO tiene UI de enlace todavía (el repositorio documental,
 * Fase 2a / item #734, no está integrado a main a la fecha de este item) —
 * el campo queda nullable sin capturarse desde aquí, como anota el propio
 * item #736 punto 6.
 */
const ETIQUETAS_TIPO_ACTA = {
    asamblea_ordinaria: 'Asamblea ordinaria',
    asamblea_extraordinaria: 'Asamblea extraordinaria',
    consejo: 'Sesión de consejo',
};

const ETIQUETAS_TIPO_CONTRATO = {
    cliente: 'Cliente',
    proveedor: 'Proveedor',
    convenio_comercial: 'Convenio comercial',
    arrendamiento: 'Arrendamiento',
    servicios: 'Servicios',
    mantenimiento: 'Mantenimiento',
    suministro: 'Suministro',
    interconexion: 'Interconexión',
};

/** Fase 3.3 (item #752) — enums de DcActivo/DcActivoDigital/DcInventarioAcceso, en español. */
const ETIQUETAS_CATEGORIA_ACTIVO = {
    torre: 'Torre',
    antena: 'Antena',
    posteria: 'Postería',
    fibra: 'Fibra óptica',
    red_troncal: 'Red troncal',
    equipo_transmision: 'Equipo de transmisión',
    vehiculo: 'Vehículo',
    computo: 'Cómputo',
    herramienta: 'Herramienta',
    centro_distribucion: 'Centro de distribución',
    bodega: 'Bodega',
    otro: 'Otro',
};

const ETIQUETAS_ESTADO_ACTIVO = {
    activo: 'Activo',
    baja: 'Baja',
    mantenimiento: 'Mantenimiento',
};

const ETIQUETAS_TIPO_ACTIVO_DIGITAL = {
    sistema: 'Sistema',
    plataforma: 'Plataforma',
    software_propio: 'Software propio',
    servidor: 'Servidor',
    base_datos: 'Base de datos',
    app_movil: 'Aplicación móvil',
    sitio_web: 'Sitio web',
    panel: 'Panel de administración',
    licencia: 'Licencia',
    respaldo: 'Respaldo',
    dominio: 'Dominio',
    correo_corporativo: 'Correo corporativo',
    red_social: 'Red social',
    plataforma_marketing: 'Plataforma de marketing',
};

const ETIQUETAS_TIPO_INVENTARIO_ACCESO = {
    cuenta_bancaria: 'Cuenta bancaria',
    linea_credito: 'Línea de crédito',
    cuenta_inversion: 'Cuenta de inversión',
    terminal_pv: 'Terminal punto de venta',
    usuario_sistema: 'Usuario de sistema',
    firma_autorizada: 'Firma autorizada',
    token: 'Token',
};

function opcionesDe(etiquetas) {
    return Object.entries(etiquetas).map(([value, label]) => ({ value, label }));
}

function formatoMoneda(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    return '$' + Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2 });
}

/** tabla (config.tabla del concepto) => recurso (segmento de la URL del CRUD). */
const TABLA_A_RECURSO = {
    dc_accionistas: 'accionistas',
    dc_capital_variaciones: 'capital_variaciones',
    dc_actas: 'actas',
    dc_poderes: 'poderes',
    dc_contratos: 'contratos',
    dc_activos: 'activos',
    dc_activos_digitales: 'activos_digitales',
    dc_inventario_accesos: 'inventario_accesos',
};

/** Config por recurso: columnas de la lista + campos del formulario. */
const RECURSOS = {
    accionistas: {
        columnas: [
            { name: 'nombre_razon_social', label: 'Nombre / razón social', field: 'nombre_razon_social' },
            { name: 'porcentaje', label: 'Porcentaje', field: (r) => r.porcentaje + '%' },
            { name: 'num_acciones', label: 'Acciones', field: (r) => (r.num_acciones ?? '—') },
            { name: 'fecha_alta', label: 'Alta', field: (r) => r.fecha_alta || '—' },
        ],
        campos: [
            { key: 'nombre_razon_social', label: 'Nombre / razón social', tipo: 'texto', requerido: true },
            { key: 'porcentaje', label: 'Porcentaje (%)', tipo: 'decimal', requerido: true },
            { key: 'num_acciones', label: 'Número de acciones', tipo: 'entero' },
            { key: 'tipo_serie', label: 'Serie', tipo: 'texto' },
            { key: 'fecha_alta', label: 'Fecha de alta', tipo: 'fecha', requerido: true },
            { key: 'fecha_baja', label: 'Fecha de baja', tipo: 'fecha' },
        ],
    },
    capital_variaciones: {
        columnas: [
            { name: 'fecha', label: 'Fecha', field: (r) => r.fecha || '—' },
            { name: 'tipo', label: 'Tipo', field: (r) => (r.tipo === 'aumento' ? 'Aumento' : 'Disminución') },
            { name: 'monto', label: 'Monto', field: (r) => formatoMoneda(r.monto) },
            { name: 'capital_resultante', label: 'Capital resultante', field: (r) => formatoMoneda(r.capital_resultante) },
        ],
        campos: [
            { key: 'fecha', label: 'Fecha', tipo: 'fecha', requerido: true },
            {
                key: 'tipo',
                label: 'Tipo de variación',
                tipo: 'seleccion',
                requerido: true,
                opciones: [
                    { value: 'aumento', label: 'Aumento' },
                    { value: 'disminucion', label: 'Disminución' },
                ],
            },
            { key: 'monto', label: 'Monto', tipo: 'decimal', requerido: true },
            { key: 'capital_resultante', label: 'Capital resultante', tipo: 'decimal', requerido: true },
            { key: 'nota', label: 'Nota', tipo: 'texto_largo' },
        ],
    },
    actas: {
        columnas: [
            { name: 'fecha', label: 'Fecha', field: (r) => r.fecha || '—' },
            { name: 'folio', label: 'Folio', field: (r) => r.folio || '—' },
            { name: 'protocolizada', label: 'Protocolizada', field: (r) => (r.protocolizada ? 'Sí' : 'No') },
        ],
        campos: [
            { key: 'fecha', label: 'Fecha', tipo: 'fecha', requerido: true },
            { key: 'folio', label: 'Folio', tipo: 'texto' },
            { key: 'resumen', label: 'Resumen', tipo: 'texto_largo' },
            { key: 'protocolizada', label: 'Protocolizada', tipo: 'booleano' },
        ],
    },
    poderes: {
        columnas: [
            { name: 'apoderado', label: 'Apoderado', field: 'apoderado' },
            { name: 'tipo_poder', label: 'Tipo de poder', field: (r) => r.tipo_poder || '—' },
            { name: 'vigencia_fin', label: 'Vigencia', field: (r) => r.vigencia_fin || 'Indefinida' },
        ],
        campos: [
            { key: 'apoderado', label: 'Apoderado', tipo: 'texto', requerido: true },
            { key: 'tipo_poder', label: 'Tipo de poder', tipo: 'texto', requerido: true },
            { key: 'alcance', label: 'Alcance', tipo: 'texto_largo' },
            { key: 'fecha_otorgamiento', label: 'Fecha de otorgamiento', tipo: 'fecha', requerido: true },
            { key: 'vigencia_fin', label: 'Vigencia hasta', tipo: 'fecha' },
            { key: 'revocado', label: 'Revocado', tipo: 'booleano' },
        ],
    },
    contratos: {
        columnas: [
            { name: 'contraparte', label: 'Contraparte', field: 'contraparte' },
            { name: 'fecha_inicio', label: 'Inicio', field: (r) => r.fecha_inicio || '—' },
            { name: 'fecha_fin', label: 'Fin', field: (r) => r.fecha_fin || 'Indefinido' },
            { name: 'monto', label: 'Monto', field: (r) => formatoMoneda(r.monto) },
        ],
        campos: [
            { key: 'contraparte', label: 'Contraparte', tipo: 'texto', requerido: true },
            { key: 'objeto', label: 'Objeto', tipo: 'texto_largo' },
            { key: 'fecha_inicio', label: 'Fecha de inicio', tipo: 'fecha', requerido: true },
            { key: 'fecha_fin', label: 'Fecha de fin', tipo: 'fecha' },
            { key: 'monto', label: 'Monto', tipo: 'decimal' },
        ],
    },
    activos: {
        columnas: [
            { name: 'nombre', label: 'Nombre', field: 'nombre' },
            { name: 'categoria', label: 'Categoría', field: (r) => ETIQUETAS_CATEGORIA_ACTIVO[r.categoria] || r.categoria },
            { name: 'ubicacion', label: 'Ubicación', field: (r) => r.ubicacion || '—' },
            { name: 'estado', label: 'Estado', field: (r) => ETIQUETAS_ESTADO_ACTIVO[r.estado] || (r.estado || '—') },
        ],
        campos: [
            { key: 'categoria', label: 'Categoría', tipo: 'seleccion', requerido: true, opciones: opcionesDe(ETIQUETAS_CATEGORIA_ACTIVO) },
            { key: 'nombre', label: 'Nombre', tipo: 'texto', requerido: true },
            { key: 'descripcion', label: 'Descripción', tipo: 'texto_largo' },
            { key: 'identificador', label: 'Identificador', tipo: 'texto' },
            { key: 'ubicacion', label: 'Ubicación', tipo: 'texto' },
            { key: 'lat', label: 'Latitud', tipo: 'decimal' },
            { key: 'lng', label: 'Longitud', tipo: 'decimal' },
            { key: 'fecha_adquisicion', label: 'Fecha de adquisición', tipo: 'fecha' },
            { key: 'valor_adquisicion', label: 'Valor de adquisición', tipo: 'decimal' },
            { key: 'estado', label: 'Estado', tipo: 'seleccion', opciones: opcionesDe(ETIQUETAS_ESTADO_ACTIVO) },
            { key: 'notas', label: 'Notas', tipo: 'texto_largo' },
        ],
    },
    activos_digitales: {
        columnas: [
            { name: 'nombre', label: 'Nombre', field: 'nombre' },
            { name: 'tipo', label: 'Tipo', field: (r) => ETIQUETAS_TIPO_ACTIVO_DIGITAL[r.tipo] || r.tipo },
            { name: 'titular', label: 'Titular', field: (r) => r.titular || '—' },
            { name: 'vigencia_fin', label: 'Vigencia', field: (r) => r.vigencia_fin || 'Indefinida' },
        ],
        campos: [
            { key: 'tipo', label: 'Tipo', tipo: 'seleccion', requerido: true, opciones: opcionesDe(ETIQUETAS_TIPO_ACTIVO_DIGITAL) },
            { key: 'nombre', label: 'Nombre', tipo: 'texto', requerido: true },
            { key: 'descripcion', label: 'Descripción', tipo: 'texto_largo' },
            { key: 'proveedor', label: 'Proveedor', tipo: 'texto' },
            { key: 'titular', label: 'Titular', tipo: 'texto', requerido: true },
            { key: 'url', label: 'URL', tipo: 'texto' },
            { key: 'fecha_alta', label: 'Fecha de alta', tipo: 'fecha' },
            { key: 'vigencia_fin', label: 'Vigencia hasta', tipo: 'fecha' },
            { key: 'costo_periodico', label: 'Costo periódico', tipo: 'decimal' },
            { key: 'periodicidad_costo', label: 'Periodicidad del costo', tipo: 'texto' },
            { key: 'notas', label: 'Notas', tipo: 'texto_largo' },
        ],
    },
    inventario_accesos: {
        columnas: [
            { name: 'institucion_o_sistema', label: 'Institución / sistema', field: 'institucion_o_sistema' },
            { name: 'tipo', label: 'Tipo', field: (r) => ETIQUETAS_TIPO_INVENTARIO_ACCESO[r.tipo] || r.tipo },
            { name: 'titular', label: 'Titular', field: (r) => r.titular || '—' },
            { name: 'custodio_user_id', label: 'Custodio', field: (r) => (r.custodio_user_id ?? '—') },
        ],
        campos: [
            { key: 'tipo', label: 'Tipo', tipo: 'seleccion', requerido: true, opciones: opcionesDe(ETIQUETAS_TIPO_INVENTARIO_ACCESO) },
            { key: 'institucion_o_sistema', label: 'Institución / sistema', tipo: 'texto', requerido: true },
            { key: 'identificador_publico', label: 'Identificador (últimos 4 caracteres)', tipo: 'texto' },
            { key: 'titular', label: 'Titular', tipo: 'texto' },
            { key: 'ubicacion_resguardo', label: 'Ubicación de resguardo', tipo: 'texto' },
            { key: 'fecha_ultima_revision', label: 'Última revisión', tipo: 'fecha' },
            { key: 'notas', label: 'Notas', tipo: 'texto_largo' },
        ],
    },
};

export default {
    name: 'DcRegistros',

    data() {
        return {
            dialogoLista: false,
            dialogoFicha: false,
            cargando: false,
            guardando: false,
            recurso: '',
            filtro: {},
            conceptoNombre: '',
            filas: [],
            form: {},
        };
    },

    computed: {
        configuracion() {
            return RECURSOS[this.recurso] || { columnas: [], campos: [] };
        },

        /** Campos del formulario + el campo "tipo" dinámico (actas/contratos), según el filtro del concepto. */
        camposFormulario() {
            const campos = [...this.configuracion.campos];

            if (this.recurso === 'actas') {
                campos.unshift(this.campoTipoDinamico(this.filtro.tipo, ETIQUETAS_TIPO_ACTA));
            }
            if (this.recurso === 'contratos') {
                campos.unshift(this.campoTipoDinamico(this.filtro.tipo, ETIQUETAS_TIPO_CONTRATO));
            }
            // Credencial: SOLO en edición (en alta no existe fila todavía), y SIEMPRE
            // de solo lectura con el texto que ya calcula el backend (nunca un input
            // editable de secreto — regla de credenciales del item #665).
            if (this.recurso === 'inventario_accesos' && this.form.id) {
                campos.push({ key: 'credencial_leyenda', label: 'Credencial', tipo: 'solo_lectura' });
            }

            return campos;
        },

        /** El campo "tipo" fijo por el filtro del concepto (si aplica), fuera del loop editable. */
        campoTipoFijo() {
            return this.camposFormulario.find((c) => c.tipo === 'fijo') || null;
        },

        camposEditables() {
            return this.camposFormulario.filter((c) => c.tipo !== 'fijo');
        },
    },

    methods: {
        /** Punto de entrada: fila "inventario" de un concepto en DcExpediente. */
        esRecursoGestionable(concepto) {
            const tabla = concepto.metricas && concepto.metricas.tabla;
            return concepto.tipo_resolvedor === 'inventario' && !!TABLA_A_RECURSO[tabla];
        },

        abrirParaConcepto(concepto) {
            this.recurso = TABLA_A_RECURSO[concepto.metricas.tabla];
            this.filtro = (concepto.metricas && concepto.metricas.filtros) || {};
            this.conceptoNombre = concepto.nombre;
            this.dialogoLista = true;
            this.cargar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const { data } = await axios.get(`/documentacion-corporativa/api/registros/${this.recurso}`);
                this.filas = (data.data || []).filter((fila) => this.cumpleFiltro(fila));
            } catch (e) {
                this.aviso('No se pudieron cargar los registros.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        /** Mismo criterio que `InventarioResolver::resolver()`: {columna: valor|[valores]}. */
        cumpleFiltro(fila) {
            return Object.entries(this.filtro).every(([columna, valor]) => (
                Array.isArray(valor) ? valor.includes(fila[columna]) : fila[columna] === valor
            ));
        },

        campoTipoDinamico(valorFiltro, etiquetas) {
            if (typeof valorFiltro === 'string') {
                return { key: 'tipo', label: 'Tipo', tipo: 'fijo', valorFijo: valorFiltro };
            }
            const permitidos = Array.isArray(valorFiltro) ? valorFiltro : Object.keys(etiquetas);
            return {
                key: 'tipo',
                label: 'Tipo',
                tipo: 'seleccion',
                requerido: true,
                opciones: permitidos.map((v) => ({ value: v, label: etiquetas[v] || v })),
            };
        },

        etiquetaFijo(campo) {
            const etiquetas = this.recurso === 'actas' ? ETIQUETAS_TIPO_ACTA : ETIQUETAS_TIPO_CONTRATO;
            return etiquetas[campo.valorFijo] || campo.valorFijo;
        },

        formVacio() {
            const vacio = { id: null };
            this.camposFormulario.forEach((campo) => {
                if (campo.tipo === 'fijo') {
                    vacio[campo.key] = campo.valorFijo;
                } else if (campo.tipo === 'booleano') {
                    vacio[campo.key] = false;
                } else {
                    vacio[campo.key] = null;
                }
            });
            return vacio;
        },

        nuevaFicha() {
            this.form = this.formVacio();
            this.dialogoFicha = true;
        },

        editarFicha(fila) {
            this.form = { ...fila };
            this.dialogoFicha = true;
        },

        async guardarFicha() {
            this.guardando = true;
            const payload = { ...this.form };
            delete payload.id;
            // Campos calculados que el backend no acepta en la validación.
            delete payload.estado;
            delete payload.created_at;
            delete payload.updated_at;
            delete payload.deleted_at;
            delete payload.empresa_id;
            // DcActivoDigital/DcInventarioAcceso (Fase 3.3): calculados en el backend,
            // nunca se mandan de vuelta (titularidad_estado no es fillable; credencial/
            // credencial_leyenda son la constante de asteriscos + su leyenda, jamás datos reales).
            delete payload.titularidad_estado;
            delete payload.credencial;
            delete payload.credencial_leyenda;

            try {
                if (this.form.id) {
                    await axios.put(`/documentacion-corporativa/api/registros/${this.recurso}/${this.form.id}`, payload);
                } else {
                    await axios.post(`/documentacion-corporativa/api/registros/${this.recurso}`, payload);
                }
                this.aviso('Registro guardado.', 'positive');
                this.dialogoFicha = false;
                this.cargar();
                this.$emit('guardado');
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo guardar el registro.', 'negative');
            } finally {
                this.guardando = false;
            }
        },

        confirmarEliminar(fila) {
            this.$q.dialog({
                title: 'Eliminar registro',
                message: '¿Eliminar este registro? Esta acción no se puede deshacer desde aquí.',
                cancel: true,
                persistent: true,
            }).onOk(() => this.eliminarFila(fila));
        },

        async eliminarFila(fila) {
            try {
                await axios.delete(`/documentacion-corporativa/api/registros/${this.recurso}/${fila.id}`);
                this.aviso('Registro eliminado.', 'positive');
                this.cargar();
                this.$emit('guardado');
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo eliminar el registro.', 'negative');
            }
        },

        valorColumna(col, fila) {
            return typeof col.field === 'function' ? col.field(fila) : (fila[col.field] ?? '—');
        },

        colorEstadoVigencia(estado) {
            return {
                vigente: 'positive', por_vencer: 'warning', vencido: 'negative',
                activo: 'positive', baja: 'grey-6', mantenimiento: 'warning',
            }[estado] || 'grey-6';
        },

        tipoInput(tipo) {
            return { fecha: 'date', decimal: 'number', entero: 'number', texto_largo: 'textarea' }[tipo] || 'text';
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
