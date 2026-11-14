<template>
  <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
    <div class="mkt-leads">
      <mkt-encabezado-pantalla
        titulo="Leads"
        con-selector-empresa
        @contexto="(c) => (contexto = c)"
      >
        <template v-if="estado !== 'sin_permiso'" #filtros>
          <q-input
            v-model="filtros.search"
            dense
            outlined
            clearable
            placeholder="Buscar"
            class="mkt-filtro--corto"
            aria-label="Buscar leads"
            @update:model-value="buscarConPausa"
          >
            <template #prepend><q-icon name="search" size="18px" /></template>
          </q-input>
          <q-select
            v-model="filtros.status"
            :options="opcionesEstado"
            emit-value
            map-options
            dense
            outlined
            clearable
            options-dense
            label="Estado"
            class="mkt-filtro--corto"
            @update:model-value="recargar"
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps" class="mkt-leads__opcion">
                <q-item-section>{{ scope.opt.label }}</q-item-section>
              </q-item>
            </template>
          </q-select>
          <q-select
            v-model="filtros.source_id"
            :options="fuentes"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            dense
            outlined
            clearable
            options-dense
            label="Fuente"
            class="mkt-filtro--corto"
            @update:model-value="recargar"
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps" class="mkt-leads__opcion">
                <q-item-section>{{ scope.opt.name }}</q-item-section>
              </q-item>
            </template>
          </q-select>
          <q-input
            v-for="campo in camposFecha"
            :key="campo.clave"
            :model-value="fechaVisible(filtros[campo.clave])"
            dense
            outlined
            readonly
            :label="campo.etiqueta"
            placeholder="dd/mm/aaaa"
            class="mkt-leads__fecha mkt-filtro--corto"
          >
            <template #append>
              <q-icon
                v-if="filtros[campo.clave]"
                name="cancel"
                class="cursor-pointer"
                role="button"
                :aria-label="`Quitar fecha ${campo.etiqueta}`"
                @click.stop="limpiarFecha(campo.clave)"
              />
              <q-icon name="event" class="cursor-pointer" />
            </template>
            <q-popup-proxy cover transition-show="scale" transition-hide="scale">
              <q-date
                v-model="filtros[campo.clave]"
                mask="YYYY-MM-DD"
                :locale="localeFecha"
                minimal
                @update:model-value="recargar"
              />
            </q-popup-proxy>
          </q-input>
          <mkt-boton v-if="hayFiltros" tipo="enlace" etiqueta="Limpiar filtros" @click="limpiarFiltros" />
        </template>
        <template #acciones>
          <mkt-boton
            v-if="puedeCrear && estado !== 'sin_permiso'"
            tipo="crear"
            etiqueta="Crear lead"
            @click="abrirAlta"
          />
          <!-- Menú de tres puntos de la tabla en la fila del título, como el Listado de Clientes. -->
          <mkt-menu-tabla
            v-if="estado !== 'sin_permiso' && estado !== 'error'"
            :columnas="columnas.filter((c) => c.label)"
            v-model:ocultas="columnasOcultas"
            con-actualizar
            @actualizar="cargarLeads()"
            @expandir="$refs.tabla && $refs.tabla.expandir()"
          />
        </template>

        <!-- Sin permiso -->
        <mkt-estado-vacio
          v-if="estado === 'sin_permiso'"
          icono="lock"
          titulo="No puedes ver los leads"
          texto="No tienes acceso a los leads. Si lo necesitas, pídelo a Irving."
        />

        <!-- Error -->
        <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar los leads" :texto="mensajeError" />

        <mkt-tabla-ejecutiva
          v-else
          ref="tabla"
          :menu="false"
          v-model:ocultas="columnasOcultas"
          :columnas="columnas"
          :filas="leads"
          :cargando="estado === 'cargando'"
          v-model:pagination="paginacion"
          :rows-per-page-options="[25, 50, 100]"
          vacio-icono="person_search"
          :vacio-titulo="hayFiltros ? 'Ningún lead coincide con los filtros' : 'Aún no hay leads'"
          :vacio-texto="hayFiltros ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Los leads llegan por tus formularios web, por WhatsApp o capturándolos aquí.'"
          @request="alPedirPagina"
          @actualizar="cargarLeads()"
          @row-click="(_e, fila) => irAFicha(fila.id)"
        >

          <template #body-cell-full_name="props">
            <q-td :props="props">
              <div class="mkt-leads__persona">
                <span class="mkt-leads__avatar" aria-hidden="true">{{ iniciales(props.row.full_name) }}</span>
                <div class="mkt-leads__persona-textos">
                  <a class="mkt-leads__nombre" :href="`/marketing/leads/${props.row.id}`" :title="props.row.full_name || 'Sin nombre'" @click.stop>{{ props.row.full_name || "Sin nombre" }}</a>
                  <span class="mkt-leads__contacto">{{ props.row.phone || props.row.email || "Sin contacto" }}</span>
                </div>
              </div>
            </q-td>
          </template>

          <template #body-cell-source="props">
            <q-td :props="props">
              <lead-source-chip v-if="props.row.source" :source="props.row.source" />
              <span v-else class="mkt-leads__suave">—</span>
            </q-td>
          </template>

          <template #body-cell-score="props">
            <q-td :props="props"><lead-score-badge :score="props.row.score" /></q-td>
          </template>

          <template #body-cell-status="props">
            <q-td :props="props"><lead-status-badge :status="props.row.status" /></q-td>
          </template>

          <template #body-cell-acciones="props">
            <q-td :props="props" class="mkt-leads__acciones">
              <q-btn flat dense round icon="chevron_right" :aria-label="`Abrir ficha de ${props.row.full_name || 'lead'}`" @click.stop="irAFicha(props.row.id)">
                <q-tooltip>Abrir ficha</q-tooltip>
              </q-btn>
            </q-td>
          </template>
        </mkt-tabla-ejecutiva>
      </mkt-encabezado-pantalla>

      <!-- Alta de lead -->
      <mkt-dialogo v-model="altaAbierta" titulo="Nuevo lead" :guardando="guardando" :color-marca="contexto?.empresa_activa?.color_primario">
        <q-banner v-if="errorAlta" rounded dense class="mkt-leads__aviso" role="alert">{{ errorAlta }}</q-banner>
        <q-input v-model="nuevo.full_name" label="Nombre completo *" outlined dense autofocus maxlength="150"
          :error="!!errores.full_name" :error-message="errores.full_name" />
        <q-input v-model="nuevo.phone" label="Teléfono" outlined dense maxlength="30"
          :error="!!errores.phone" :error-message="errores.phone" />
        <q-input v-model="nuevo.email" label="Correo electrónico" type="email" outlined dense
          :error="!!errores.email" :error-message="errores.email" />
        <q-select v-model="nuevo.source_id" :options="fuentes" option-value="id" option-label="name" emit-value map-options
          label="Fuente *" outlined dense options-dense :error="!!errores.source_id" :error-message="errores.source_id">
          <template #option="scope">
            <q-item v-bind="scope.itemProps" class="mkt-leads__opcion">
              <q-item-section>{{ scope.opt.name }}</q-item-section>
            </q-item>
          </template>
        </q-select>
        <template #acciones>
          <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="altaAbierta = false" />
          <mkt-boton tipo="crear" etiqueta="Crear lead" :cargando="guardando" @click="guardarLead" />
        </template>
      </mkt-dialogo>
    </div>
  </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import {
  MktRaiz,
  MktEncabezadoPantalla,
  MktTarjetaPanel,
  MktTablaEjecutiva,
  MktEstadoVacio,
  MktDialogo,
  MktBoton,
  ESTADOS_LEAD,
  formatoFecha,
  formatoNumero,
  iniciales,
  mensajeDeError,
  erroresPorCampo,
  puedePermiso,
} from "./kit";
import LeadSourceChip from "./LeadSourceChip.vue";
import LeadScoreBadge from "./LeadScoreBadge.vue";
import LeadStatusBadge from "./LeadStatusBadge.vue";

/** Calendario de los filtros Desde / Hasta en español (q-date). */
const LOCALE_FECHA = Object.freeze({
  days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
  daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
  months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
  monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
  firstDayOfWeek: 1,
});

const NUEVO = () => ({ full_name: "", phone: "", email: "", source_id: null });

/**
 * Leads (/marketing/leads) — Fase 1: rediseño con el kit; mismos endpoints
 * (GET/POST /api/marketing/leads, GET /api/marketing/lead-sources).
 */
export default {
  name: "MarketingLeadsView",
  components: {
    MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktTablaEjecutiva, MktEstadoVacio, MktDialogo, MktBoton,
    LeadSourceChip, LeadScoreBadge, LeadStatusBadge,
  },
  data() {
    return {
      columnasOcultas: [],
      contexto: null,
      estado: "cargando", // cargando | listo | error | sin_permiso
      mensajeError: "",
      leads: [],
      fuentes: [],
      filtros: { search: "", status: null, source_id: null, captured_from: "", captured_to: "" },
      paginacion: { page: 1, rowsPerPage: 25, rowsNumber: 0, sortBy: "captured_at", descending: true },
      altaAbierta: false,
      guardando: false,
      nuevo: NUEVO(),
      errores: {},
      errorAlta: "",
      peticion: 0,
      temporizador: null,
      camposFecha: [
        { clave: "captured_from", etiqueta: "Desde" },
        { clave: "captured_to", etiqueta: "Hasta" },
      ],
      localeFecha: LOCALE_FECHA,
      opcionesEstado: ESTADOS_LEAD.map((e) => ({ label: e.label, value: e.value })),
      columnas: [
        { name: "full_name", label: "Lead", field: "full_name", align: "left", sortable: true },
        { name: "source", label: "Fuente", field: "source", align: "left", ocultarEnMovil: true },
        { name: "city", label: "Ciudad", field: "city", align: "left", ocultarEnMovil: true, format: (v) => v || "—" },
        { name: "score", label: "Puntuación", field: "score", align: "center", sortable: true },
        { name: "status", label: "Estado", field: "status", align: "left" },
        { name: "captured_at", label: "Capturado", field: "captured_at", align: "left", sortable: true, format: (v) => formatoFecha(v) },
        { name: "acciones", label: "", field: "id", align: "right" },
      ],
    };
  },
  computed: {
    puedeCrear() {
      return puedePermiso(this.$store, "marketing.leads.create");
    },
    hayFiltros() {
      const f = this.filtros;
      return !!(f.search || f.status || f.source_id || f.captured_from || f.captured_to);
    },
    tituloTabla() {
      const n = this.paginacion.rowsNumber;
      return this.estado === "cargando" && !n ? "Leads" : `${formatoNumero(n)} ${n === 1 ? "lead" : "leads"}`;
    },
  },
  mounted() {
    this.cargarLeads();
    this.cargarFuentes();
  },
  beforeUnmount() {
    clearTimeout(this.temporizador);
  },
  methods: {
    iniciales,
    /** YYYY-MM-DD (lo que manda la API) → dd/mm/aaaa (formato del sistema). */
    fechaVisible(v) {
      const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || "");
      return m ? `${m[3]}/${m[2]}/${m[1]}` : "";
    },
    limpiarFecha(campo) {
      this.filtros[campo] = "";
      this.recargar();
    },
    async cargarLeads() {
      const id = ++this.peticion;
      this.estado = "cargando";
      const p = this.paginacion;
      try {
        const { data } = await axios.get("/api/marketing/leads", {
          params: {
            page: p.page,
            per_page: p.rowsPerPage,
            sort: p.sortBy,
            order: p.descending ? "desc" : "asc",
            ...Object.fromEntries(Object.entries(this.filtros).filter(([, v]) => v !== null && v !== "")),
          },
        });
        if (id !== this.peticion) return;
        this.leads = Array.isArray(data?.data) ? data.data : [];
        this.paginacion = { ...this.paginacion, rowsNumber: data?.meta?.total ?? data?.total ?? this.leads.length };
        this.estado = "listo";
      } catch (e) {
        if (id !== this.peticion) return;
        if (e?.response?.status === 403) {
          this.estado = "sin_permiso";
          return;
        }
        this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
        this.estado = "error";
      }
    },
    async cargarFuentes() {
      try {
        const { data } = await axios.get("/api/marketing/lead-sources");
        this.fuentes = Array.isArray(data) ? data : data?.data || [];
      } catch (e) {
        this.fuentes = [];
      }
    },
    alPedirPagina({ pagination }) {
      this.paginacion = { ...this.paginacion, ...pagination };
      this.cargarLeads();
    },
    recargar() {
      this.paginacion = { ...this.paginacion, page: 1 };
      this.cargarLeads();
    },
    buscarConPausa() {
      clearTimeout(this.temporizador);
      this.temporizador = setTimeout(() => this.recargar(), 400);
    },
    limpiarFiltros() {
      this.filtros = { search: "", status: null, source_id: null, captured_from: "", captured_to: "" };
      this.recargar();
    },
    irAFicha(id) {
      window.location.href = `/marketing/leads/${id}`;
    },
    abrirAlta() {
      this.nuevo = NUEVO();
      this.errores = {};
      this.errorAlta = "";
      this.altaAbierta = true;
    },
    async guardarLead() {
      if (this.guardando) return;
      this.errores = {};
      this.errorAlta = "";
      if (!this.nuevo.full_name.trim()) this.errores.full_name = "Escribe el nombre.";
      if (!this.nuevo.source_id) this.errores.source_id = "Elige la fuente.";
      if (Object.keys(this.errores).length) return;
      this.guardando = true;
      try {
        await axios.post("/api/marketing/leads", this.nuevo);
        this.altaAbierta = false;
        message(`Lead «${this.nuevo.full_name}» creado.`);
        this.recargar();
      } catch (e) {
        this.errores = erroresPorCampo(e);
        if (!Object.keys(this.errores).length) this.errorAlta = mensajeDeError(e, "No se pudo crear el lead.");
      } finally {
        this.guardando = false;
      }
    },
  },
};
</script>

<style scoped>
.mkt-leads { min-width: 0; }
.mkt-leads__fecha { cursor: pointer; }
.mkt-leads__fecha :deep(input) { cursor: pointer; }
.mkt-leads__opcion { --bs-gutter-x: 0; }
.mkt-leads__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-leads__persona { display: flex; align-items: center; gap: 10px; min-width: 0; max-width: 260px; }
.mkt-leads__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
  color: var(--mkt-avatar-texto, #4f46e5);
  background: var(--mkt-avatar-fondo, #eef2ff);
}
.mkt-leads__persona-textos { display: flex; flex-direction: column; min-width: 0; }
.mkt-leads__nombre,
.mkt-leads__contacto { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mkt-leads__nombre { font-weight: 500; text-decoration: none; }
.mkt-leads__nombre:hover { text-decoration: underline; }
.mkt-leads__contacto { font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-leads__suave { color: var(--mkt-texto-suave); }
.mkt-leads__acciones { white-space: nowrap; }
.mkt-leads :deep(tbody tr) { cursor: pointer; }
.mkt-leads__aviso {
  color: var(--mkt-peligro);
  background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent);
}
</style>
