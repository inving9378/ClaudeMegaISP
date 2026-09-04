<template>
    <q-dialog v-model="dialogo" full-width>
        <q-card style="max-width: 900px">
            <q-card-section class="row items-center">
                <div class="text-h6">
                    <i class="bi bi-person-x"></i>
                    Checklist de offboarding
                </div>
                <q-space />
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <q-select
                    dense
                    outlined
                    use-input
                    hide-selected
                    fill-input
                    input-debounce="300"
                    v-model="colaborador"
                    :options="opcionesColaborador"
                    option-value="id"
                    option-label="label"
                    label="Buscar colaborador (nombre o usuario)"
                    @filter="buscarColaboradores"
                    @update:model-value="alElegirColaborador"
                >
                    <template #no-option>
                        <q-item>
                            <q-item-section class="text-grey">Sin coincidencias</q-item-section>
                        </q-item>
                    </template>
                </q-select>
            </q-card-section>

            <q-separator v-if="colaborador" />

            <q-card-section v-if="colaborador" style="max-height: 55vh" class="scroll">
                <q-inner-loading :showing="cargando">
                    <q-spinner size="34px" color="primary" />
                </q-inner-loading>

                <div v-if="!cargando && sinPendientes" class="text-center text-grey q-pa-lg">
                    <i class="bi bi-check2-circle" style="font-size: 30px"></i>
                    <div class="q-mt-sm">{{ colaborador.label }} no tiene accesos ni activos digitales pendientes de revocar.</div>
                </div>

                <template v-else>
                    <div v-if="accesos.length" class="q-mb-md">
                        <div class="text-subtitle2 q-mb-xs">Accesos ({{ accesos.length }})</div>
                        <q-list bordered separator>
                            <q-item v-for="a in accesos" :key="'acceso-' + a.id">
                                <q-item-section>
                                    <q-item-label>
                                        {{ a.institucion_o_sistema }}
                                        <q-badge outline color="primary" class="q-ml-xs" :label="etiquetaTipoAcceso(a.tipo)" />
                                    </q-item-label>
                                    <q-item-label caption>
                                        <span v-if="a.identificador_publico">····{{ a.identificador_publico }} · </span>
                                        {{ a.ubicacion_resguardo || 'sin resguardo registrado' }}
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section side>
                                    <q-btn
                                        v-hasPermission="'documentacion-corporativa.offboarding.gestionar'"
                                        dense
                                        outline
                                        color="negative"
                                        icon="block"
                                        label="Revocar"
                                        :loading="revocando === 'acceso-' + a.id"
                                        @click="revocar('acceso', a)"
                                    />
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </div>

                    <div v-if="activos.length">
                        <div class="text-subtitle2 q-mb-xs">Activos digitales ({{ activos.length }})</div>
                        <q-list bordered separator>
                            <q-item v-for="a in activos" :key="'activo-' + a.id">
                                <q-item-section>
                                    <q-item-label>
                                        {{ a.nombre }}
                                        <q-badge outline color="deep-orange" class="q-ml-xs" :label="etiquetaTipoActivo(a.tipo)" />
                                    </q-item-label>
                                    <q-item-label caption>
                                        {{ a.proveedor || 'sin proveedor' }}
                                        <span v-if="a.url"> · {{ a.url }}</span>
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section side>
                                    <q-btn
                                        v-hasPermission="'documentacion-corporativa.offboarding.gestionar'"
                                        dense
                                        outline
                                        color="negative"
                                        icon="block"
                                        label="Revocar"
                                        :loading="revocando === 'activo-' + a.id"
                                        @click="revocar('activo', a)"
                                    />
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </div>
                </template>

                <q-separator class="q-my-md" />

                <div>
                    <div class="text-subtitle2 q-mb-xs">
                        Otros pendientes ({{ otrosCompletados }}/{{ otrosItems.length }})
                    </div>
                    <q-list bordered separator>
                        <q-item v-for="item in otrosItems" :key="'otro-' + item.clave">
                            <q-item-section side top>
                                <q-checkbox
                                    v-hasPermission="'documentacion-corporativa.offboarding.gestionar'"
                                    v-model="item.completado"
                                    :disable="guardandoOtro === item.clave"
                                    @update:model-value="guardarOtroItem(item)"
                                />
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>
                                    {{ item.etiqueta }}
                                    <q-badge v-if="item.completado" outline color="positive" class="q-ml-xs" label="Completado" />
                                </q-item-label>
                                <q-item-label caption v-if="item.completado">
                                    Completado el {{ item.fecha }}
                                    <span v-if="item.evidencia_path"> · <i class="bi bi-paperclip"></i> evidencia adjunta</span>
                                </q-item-label>

                                <div
                                    v-hasPermission="'documentacion-corporativa.offboarding.gestionar'"
                                    class="row q-col-gutter-sm q-mt-xs"
                                >
                                    <q-input
                                        v-if="item.completado"
                                        class="col-12 col-md-4"
                                        dense
                                        outlined
                                        type="date"
                                        v-model="item.fecha"
                                        label="Fecha"
                                        @blur="guardarOtroItem(item)"
                                    />
                                    <q-input
                                        class="col-12 col-md-5"
                                        dense
                                        outlined
                                        v-model="item.notas"
                                        label="Nota"
                                        @blur="guardarOtroItem(item)"
                                    />
                                    <q-file
                                        class="col-12 col-md-3"
                                        dense
                                        outlined
                                        v-model="item._archivo"
                                        label="Evidencia (opcional)"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        :max-file-size="10 * 1024 * 1024"
                                        @update:model-value="guardarOtroItem(item)"
                                        @rejected="onEvidenciaRechazada"
                                    >
                                        <template #prepend><i class="bi bi-paperclip"></i></template>
                                    </q-file>
                                </div>
                            </q-item-section>
                            <q-item-section side v-if="guardandoOtro === item.clave">
                                <q-spinner size="20px" color="primary" />
                            </q-item-section>
                        </q-item>
                    </q-list>
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<script>
/**
 * Checklist de offboarding (apartado XII, Fase 5d-1, item roadmap #815).
 *
 * Solo lista lo que YA existe en `dc_inventario_accesos`/`dc_activos_digitales`
 * (scopeDeCustodio/scopeDeResponsable, item #761) que aún no esté revocado, y
 * permite marcar cada fila como revocada UNA POR UNA. Nunca revocación
 * masiva/automática (Opción C de #667 descartada explícitamente).
 *
 * Sección "Otros pendientes" (item roadmap #840, Fase 5d-2b): los 6 ítems
 * fijos sin tabla propia (correo, VPN, WhatsApp, equipo, respaldo, finiquito
 * RH) que sirve `OffboardingOtrosItemsController`/`OffboardingOtrosItemsService`
 * (item #839). Registro manual únicamente — sin acciones automáticas.
 */
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

export default {
    name: 'DcOffboarding',

    data() {
        return {
            dialogo: false,
            cargando: false,
            revocando: null,
            colaborador: null,
            opcionesColaborador: [],
            accesos: [],
            activos: [],
            otrosItems: [],
            guardandoOtro: null,
        };
    },

    computed: {
        sinPendientes() {
            return this.accesos.length === 0 && this.activos.length === 0;
        },

        otrosCompletados() {
            return this.otrosItems.filter((item) => item.completado).length;
        },
    },

    methods: {
        /** Punto de entrada: botón "Checklist de offboarding" del header. */
        abrir() {
            this.dialogo = true;
            this.colaborador = null;
            this.accesos = [];
            this.activos = [];
            this.otrosItems = [];
        },

        async buscarColaboradores(val, update) {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/offboarding/data/colaboradores', {
                    params: { q: val },
                });
                update(() => { this.opcionesColaborador = data; });
            } catch (e) {
                update(() => { this.opcionesColaborador = []; });
            }
        },

        async alElegirColaborador() {
            if (!this.colaborador) {
                this.accesos = [];
                this.activos = [];
                this.otrosItems = [];
                return;
            }
            await this.cargarPendientes();
        },

        async cargarPendientes() {
            this.cargando = true;
            try {
                const [pendientes, otros] = await Promise.all([
                    axios.get('/documentacion-corporativa/api/offboarding/pendientes', {
                        params: { user_id: this.colaborador.id },
                    }),
                    axios.get('/documentacion-corporativa/api/offboarding/otros-items', {
                        params: { user_id: this.colaborador.id },
                    }),
                ]);
                this.accesos = pendientes.data.accesos;
                this.activos = pendientes.data.activos;
                this.otrosItems = otros.data.map((item) => ({ ...item, _archivo: null }));
            } catch (e) {
                this.aviso('No se pudo cargar el checklist de este colaborador.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        async guardarOtroItem(item) {
            if (!this.colaborador || this.guardandoOtro === item.clave) {
                return;
            }

            this.guardandoOtro = item.clave;
            try {
                const formData = new FormData();
                formData.append('user_id', this.colaborador.id);
                formData.append('item_clave', item.clave);
                formData.append('completado', item.completado ? '1' : '0');
                if (item.fecha) formData.append('fecha', item.fecha);
                if (item.notas) formData.append('notas', item.notas);
                if (item._archivo) formData.append('evidencia', item._archivo);

                const { data } = await axios.post('/documentacion-corporativa/api/offboarding/otros-items', formData);

                Object.assign(item, data);
                item._archivo = null;
                this.aviso(`"${item.etiqueta}" actualizado.`, 'positive');
            } catch (e) {
                this.aviso(`No se pudo guardar "${item.etiqueta}". Intenta de nuevo.`, 'negative');
            } finally {
                this.guardandoOtro = null;
            }
        },

        onEvidenciaRechazada() {
            this.aviso('El archivo no cumple con el tipo o tamaño permitido (máx. 10MB).', 'negative');
        },

        async revocar(tipo, fila) {
            if (!confirm(`¿Marcar "${tipo === 'acceso' ? fila.institucion_o_sistema : fila.nombre}" como revocado? No se puede deshacer desde aquí.`)) {
                return;
            }

            const clave = `${tipo}-${fila.id}`;
            this.revocando = clave;
            try {
                await axios.post('/documentacion-corporativa/api/offboarding/revocar', { tipo, id: fila.id });
                if (tipo === 'acceso') {
                    this.accesos = this.accesos.filter((a) => a.id !== fila.id);
                } else {
                    this.activos = this.activos.filter((a) => a.id !== fila.id);
                }
                this.aviso('Marcado como revocado.', 'positive');
            } catch (e) {
                this.aviso('No se pudo revocar. Intenta de nuevo.', 'negative');
            } finally {
                this.revocando = null;
            }
        },

        etiquetaTipoAcceso(tipo) {
            return ETIQUETAS_TIPO_INVENTARIO_ACCESO[tipo] || tipo;
        },

        etiquetaTipoActivo(tipo) {
            return ETIQUETAS_TIPO_ACTIVO_DIGITAL[tipo] || tipo;
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
