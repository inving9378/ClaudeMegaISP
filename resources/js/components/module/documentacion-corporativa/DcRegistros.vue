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
            return { vigente: 'positive', por_vencer: 'warning', vencido: 'negative' }[estado] || 'grey-6';
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
