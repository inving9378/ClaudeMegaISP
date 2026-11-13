<template>
    <mkt-raiz :color-marca="colorMarca">
        <div class="mkt-empresas">
            <mkt-encabezado-pantalla
                ref="encabezado"
                titulo="Empresas y equipo"
                subtitulo="Marcas del grupo Meganet y quién trabaja en cada una"
                icono="domain"
                con-selector-empresa
                @contexto="alCargarContexto"
            >
                <template #acciones>
                    <q-btn
                        v-if="puedeGestionar && estado === 'listo'"
                        unelevated
                        no-caps
                        color="primary"
                        icon="add"
                        label="Nueva empresa"
                        @click="abrirFormulario(null)"
                    />
                </template>
            </mkt-encabezado-pantalla>

            <!-- Sin permiso -->
            <mkt-tarjeta-panel v-if="estado === 'sin_permiso'">
                <mkt-estado-vacio
                    icono="lock"
                    titulo="No puedes administrar empresas"
                    texto="Esta pantalla es para quien tiene rol Administrador en la empresa activa y el permiso «Marketing: administrar empresas». Si lo necesitas, pídelo a un administrador de Marketing."
                >
                    <template #accion>
                        <q-btn outline no-caps color="primary" icon="arrow_back" label="Volver a Marketing" href="/marketing" />
                    </template>
                </mkt-estado-vacio>
            </mkt-tarjeta-panel>

            <!-- Error -->
            <mkt-tarjeta-panel v-else-if="estado === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudieron cargar las empresas" :texto="mensajeError">
                    <template #accion>
                        <q-btn outline no-caps color="primary" icon="refresh" label="Reintentar" @click="cargarEmpresas" />
                    </template>
                </mkt-estado-vacio>
            </mkt-tarjeta-panel>

            <template v-else>
                <!-- KPIs -->
                <section class="mkt-empresas__kpis" aria-label="Resumen">
                    <mkt-tarjeta-kpi etiqueta="Empresas" :valor="kpis.total" icono="domain" :cargando="estado === 'cargando'" />
                    <mkt-tarjeta-kpi etiqueta="Activas" :valor="kpis.activas" icono="check_circle" :cargando="estado === 'cargando'" />
                    <mkt-tarjeta-kpi etiqueta="Miembros en equipos" :valor="kpis.miembros" icono="groups" :cargando="estado === 'cargando'" />
                    <mkt-tarjeta-kpi etiqueta="Administradores" :valor="kpis.admins" icono="admin_panel_settings" :cargando="estado === 'cargando'" />
                </section>

                <!-- Empresas -->
                <mkt-tarjeta-panel
                    titulo="Empresas"
                    subtitulo="Solo aparecen las empresas donde eres administrador. Elige una para ver su equipo."
                >
                    <mkt-tabla-ejecutiva
                        :columnas="columnas"
                        :filas="empresas"
                        :cargando="estado === 'cargando'"
                        vacio-icono="domain_add"
                        vacio-titulo="Aún no administras ninguna empresa"
                        vacio-texto="Crea la primera marca del grupo; quedarás como su administrador."
                        @row-click="(_ev, row) => seleccionar(row)"
                    >
                        <template v-if="puedeGestionar" #vacio-accion>
                            <q-btn unelevated no-caps color="primary" icon="add" label="Nueva empresa" @click="abrirFormulario(null)" />
                        </template>

                        <template #body-cell-nombre="props">
                            <q-td :props="props" class="mkt-empresas__celda-nombre" :class="{ 'mkt-empresas__celda-nombre--elegida': props.row.id === seleccionadaId }">
                                <div class="mkt-empresas__nombre">
                                    <span class="mkt-empresas__muestras" aria-hidden="true">
                                        <span :style="{ background: colorDe(props.row.color_primario, '#0066CC') }"></span>
                                        <span :style="{ background: colorDe(props.row.color_secundario, '#e9e9ef') }"></span>
                                    </span>
                                    <span class="mkt-empresas__nombre-texto">{{ props.row.nombre }}</span>
                                    <mkt-insignia-estado v-if="props.row.es_principal" texto="Principal" tono="primario" />
                                </div>
                                <div class="mkt-empresas__slug-movil">{{ props.row.slug }}</div>
                            </q-td>
                        </template>

                        <template #body-cell-slug="props">
                            <q-td :props="props"><code class="mkt-empresas__slug">{{ props.row.slug }}</code></q-td>
                        </template>

                        <template #body-cell-activo="props">
                            <q-td :props="props">
                                <mkt-insignia-estado :texto="props.row.activo ? 'Activa' : 'Inactiva'" :tono="props.row.activo ? 'exito' : 'neutro'" />
                            </q-td>
                        </template>

                        <template #body-cell-acciones="props">
                            <q-td :props="props" class="mkt-empresas__acciones">
                                <q-btn
                                    flat
                                    dense
                                    no-caps
                                    color="primary"
                                    icon="groups"
                                    :label="props.row.id === seleccionadaId ? 'Viendo equipo' : 'Ver equipo'"
                                    :aria-pressed="props.row.id === seleccionadaId ? 'true' : 'false'"
                                    @click.stop="seleccionar(props.row)"
                                />
                                <q-btn
                                    v-if="puedeGestionar"
                                    flat
                                    dense
                                    round
                                    color="primary"
                                    icon="edit"
                                    :aria-label="`Editar ${props.row.nombre}`"
                                    @click.stop="abrirFormulario(props.row)"
                                >
                                    <q-tooltip>Editar</q-tooltip>
                                </q-btn>
                            </q-td>
                        </template>
                    </mkt-tabla-ejecutiva>
                </mkt-tarjeta-panel>

                <!-- Equipo de la empresa elegida -->
                <equipo-empresa-panel
                    v-if="seleccionada"
                    :key="seleccionada.id"
                    ref="equipo"
                    :empresa="seleccionada"
                    :auth-user-id="authUserId"
                    :puede-editar="puedeGestionar"
                    @cambio="cargarEmpresas({ silencioso: true })"
                />
            </template>

            <empresa-form-dialog v-model="formAbierto" :empresa="empresaEnEdicion" @guardada="alGuardar" />
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import {
    MktRaiz,
    MktEncabezadoPantalla,
    MktTarjetaKpi,
    MktTarjetaPanel,
    MktTablaEjecutiva,
    MktEstadoVacio,
    MktInsigniaEstado,
    esColorHex,
    mensajeDeError,
} from "../kit";
import EmpresaFormDialog from "./EmpresaFormDialog.vue";
import EquipoEmpresaPanel from "./EquipoEmpresaPanel.vue";

/**
 * Pantalla «Empresas y equipo» (/marketing/empresas) — Fase 0, contrato 2.6 rutas 3–10.
 * Solo escribe marketing_empresas / marketing_empresa_usuario a través del API; nunca roles ni permisos del sistema.
 */
export default {
    name: "MarketingEmpresasEquipo",
    components: {
        MktRaiz,
        MktEncabezadoPantalla,
        MktTarjetaKpi,
        MktTarjetaPanel,
        MktTablaEjecutiva,
        MktEstadoVacio,
        MktInsigniaEstado,
        EmpresaFormDialog,
        EquipoEmpresaPanel,
    },
    props: {
        authUserId: { type: Number, default: null },
    },
    data() {
        return {
            estado: "cargando", // cargando | listo | error | sin_permiso
            empresas: [],
            mensajeError: "",
            contexto: null,
            seleccionadaId: null,
            formAbierto: false,
            empresaEnEdicion: null,
            columnas: [
                { name: "nombre", label: "Empresa", field: "nombre", align: "left", sortable: true },
                { name: "slug", label: "Identificador", field: "slug", align: "left", ocultarEnMovil: true },
                { name: "activo", label: "Estado", field: "activo", align: "left" },
                { name: "miembros", label: "Miembros", field: "miembros", align: "right", sortable: true },
                { name: "admins", label: "Admins", field: "admins", align: "right", sortable: true, ocultarEnMovil: true },
                { name: "acciones", label: "", field: "id", align: "right" },
            ],
        };
    },
    computed: {
        puedeGestionar() {
            return this.contexto?.capacidades?.gestionar_empresas === true;
        },
        colorMarca() {
            return this.contexto?.empresa_activa?.color_primario || null;
        },
        seleccionada() {
            return this.empresas.find((e) => e.id === this.seleccionadaId) || null;
        },
        kpis() {
            const activas = this.empresas.filter((e) => e.activo);
            const suma = (lista, campo) => lista.reduce((s, e) => s + (Number(e[campo]) || 0), 0);
            return {
                total: this.empresas.length,
                activas: activas.length,
                miembros: suma(this.empresas, "miembros"),
                admins: suma(this.empresas, "admins"),
            };
        },
    },
    mounted() {
        this.cargarEmpresas();
    },
    methods: {
        colorDe(v, defecto) {
            return esColorHex(v) ? v : defecto;
        },
        alCargarContexto(ctx) {
            this.contexto = ctx;
            if (ctx?.capacidades?.gestionar_empresas === false) {
                this.estado = "sin_permiso";
                return;
            }
            this.elegirPorDefecto();
        },
        async cargarEmpresas({ silencioso = false } = {}) {
            if (this.estado === "sin_permiso") return;
            if (!silencioso) this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/empresas");
                this.empresas = Array.isArray(data?.data) ? data.data : [];
                if (this.estado !== "sin_permiso") this.estado = "listo";
                this.elegirPorDefecto();
            } catch (e) {
                if (silencioso) return; // el panel de equipo ya mostró su propio resultado
                const status = e?.response?.status;
                if (status === 403) {
                    this.estado = "sin_permiso";
                } else {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            }
        },
        elegirPorDefecto() {
            if (this.seleccionada || !this.empresas.length) return;
            const activa = this.contexto?.empresa_activa?.id;
            const preferida = this.empresas.find((e) => e.id === activa) || this.empresas[0];
            this.seleccionadaId = preferida.id;
        },
        seleccionar(fila) {
            this.seleccionadaId = fila.id;
            this.$nextTick(() => {
                const el = this.$refs.equipo?.$el;
                if (el && el.scrollIntoView && window.matchMedia?.("(max-width: 1023px)").matches) {
                    el.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        },
        abrirFormulario(empresa) {
            this.empresaEnEdicion = empresa;
            this.formAbierto = true;
        },
        async alGuardar(empresa, { creada } = {}) {
            if (creada) {
                message(`«${empresa?.nombre || "Empresa"}» creada. Ya eres su administrador.`);
                await this.cargarEmpresas({ silencioso: true });
                if (empresa?.id) this.seleccionadaId = empresa.id;
                // La empresa nueva ya puede elegirse en el selector.
                this.$refs.encabezado?.recargarContexto();
                return;
            }
            message("Cambios guardados.");
            if (empresa?.id) {
                this.empresas = this.empresas.map((e) => (e.id === empresa.id ? { ...e, ...empresa } : e));
            }
            await this.cargarEmpresas({ silencioso: true });
            if (empresa?.id && empresa.id === this.contexto?.empresa_activa?.id) {
                this.$refs.encabezado?.recargarContexto();
            }
        },
    },
};
</script>

<style scoped>
.mkt-empresas {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-3);
    padding: var(--mkt-esp-3);
    max-width: 1440px;
    margin: 0 auto;
}
/* El encabezado trae su propio margen inferior; aquí manda el gap. */
.mkt-empresas > :first-child { margin-bottom: 0; }
.mkt-empresas__kpis {
    display: grid;
    gap: var(--mkt-esp-2);
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.mkt-empresas__nombre { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.mkt-empresas__nombre-texto { font-weight: 600; color: var(--mkt-texto); }
.mkt-empresas__muestras { display: inline-flex; }
.mkt-empresas__muestras > span {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid var(--mkt-fondo-tarjeta);
}
.mkt-empresas__muestras > span + span { margin-left: -5px; }
.mkt-empresas__slug {
    font-size: 13px;
    padding: 1px 6px;
    border-radius: 4px;
    color: var(--mkt-texto-suave);
    background: var(--mkt-fondo-suave);
}
.mkt-empresas__slug-movil { display: none; font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-empresas__celda-nombre { box-shadow: inset 3px 0 0 transparent; }
.mkt-empresas__celda-nombre--elegida { box-shadow: inset 3px 0 0 var(--mkt-marca); }
.mkt-empresas__acciones { white-space: nowrap; }
.mkt-empresas :deep(tbody tr) { cursor: pointer; }

@media (max-width: 1023px) {
    .mkt-empresas__kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 599px) {
    .mkt-empresas { padding: var(--mkt-esp-2) 0; gap: var(--mkt-esp-2); }
    .mkt-empresas__kpis { gap: var(--mkt-esp-1); }
    .mkt-empresas__slug-movil { display: block; }
}
</style>
