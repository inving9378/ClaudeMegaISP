<template>
  <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
    <div class="mkt-lead-ficha">
      <!-- Contexto de la empresa (oculto: solo Meganet). -->
      <mkt-selector-empresa v-show="false" @cargado="(c) => (contexto = c)" />

      <!-- Un solo grupo de pestañas (Leads · Conversaciones · Formularios de captura), arriba como en la ficha de Cliente. -->
      <mkt-navegacion-modulo />

      <!-- Cargando -->
      <div v-if="estado === 'cargando'" class="mkt-lead-ficha__rejilla">
        <mkt-tarjeta-panel titulo="Datos de contacto" tono="lila" icono-fa="fa-address-card" cargando />
        <mkt-tarjeta-panel titulo="Actividad" tono="verde" icono-fa="fa-history" cargando />
      </div>

      <!-- No encontrado / sin permiso / error -->
      <mkt-encabezado-pantalla v-else-if="estado === 'no_encontrado'" titulo="Lead no encontrado" :con-pestanas="false">
        <mkt-estado-vacio icono="search_off" titulo="No encontrado" texto="Este lead no existe o ya se eliminó." />
      </mkt-encabezado-pantalla>

      <mkt-encabezado-pantalla v-else-if="estado === 'sin_permiso'" titulo="Lead" :con-pestanas="false">
        <mkt-estado-vacio icono="lock" titulo="No puedes ver este lead" texto="No tienes acceso a los leads. Si lo necesitas, pídelo a Irving." />
      </mkt-encabezado-pantalla>

      <mkt-encabezado-pantalla v-else-if="estado === 'error'" titulo="Lead" :con-pestanas="false">
        <mkt-estado-vacio icono="cloud_off" titulo="No se pudo cargar el lead" :texto="mensajeError" />
      </mkt-encabezado-pantalla>

      <template v-else-if="lead">
        <!-- Tarjeta de encabezado, como la de la ficha de Cliente -->
        <mkt-ficha-encabezado :titulo="titulo" :numero="`#${lead.id || id}`">
          <template #estado>
            <lead-status-badge :status="lead.status" />
          </template>
          <template #datos>
            <span v-if="lead.phone"><i class="fa fa-phone-alt me-1" aria-hidden="true"></i>{{ lead.phone }}</span>
            <span v-if="lead.email"><i class="fa fa-envelope me-1" aria-hidden="true"></i>{{ lead.email }}</span>
            <span v-if="lead.neighborhood || lead.city"><i class="fa fa-map-marker-alt me-1" aria-hidden="true"></i>{{ [lead.neighborhood, lead.city].filter(Boolean).join(", ") }}</span>
            <span><i class="fa fa-calendar me-1" aria-hidden="true"></i>Capturado {{ formatoFecha(lead.captured_at) }}</span>
            <span>{{ subtitulo }}</span>
          </template>
          <template #cifras>
            <div class="mkt-ficha__cifra mkt-lead-ficha__puntuacion">
              <span>Puntuación</span>
              <lead-score-badge :score="lead.score" />
            </div>
          </template>
          <template #acciones>
            <mkt-boton tipo="volver" etiqueta="Leads" href="/marketing/leads" />
            <mkt-boton v-if="puede('marketing.leads.update')" tipo="secundario" etiqueta="Editar" @click="abrirEdicion" />
            <mkt-boton
              v-if="puede('marketing.leads.score')"
              tipo="principal"
              etiqueta="Volver a calificar"
              :cargando="calificando"
              @click="recalificar"
            />
          </template>
        </mkt-ficha-encabezado>

        <div class="mkt-lead-ficha__rejilla">
          <div class="mkt-lead-ficha__columna">
            <mkt-tarjeta-panel titulo="Datos de contacto" tono="lila" icono-fa="fa-address-card">
              <dl class="mkt-lead-ficha__lista">
                <template v-for="d in datos" :key="d.etiqueta">
                  <dt>{{ d.etiqueta }}</dt>
                  <dd>{{ d.valor }}</dd>
                </template>
              </dl>
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel titulo="Seguimiento" tono="azul" icono-fa="fa-tasks">
              <dl class="mkt-lead-ficha__lista">
                <dt>Estado</dt>
                <dd><lead-status-badge :status="lead.status" /></dd>
                <dt>Puntuación</dt>
                <dd><lead-score-badge :score="lead.score" /></dd>
                <dt>Fuente</dt>
                <dd>
                  <lead-source-chip v-if="lead.source" :source="lead.source" />
                  <span v-else class="mkt-lead-ficha__suave">—</span>
                </dd>
                <dt>Capturado</dt>
                <dd>{{ formatoFecha(lead.captured_at) }}</dd>
                <dt>Última actividad</dt>
                <dd>{{ formatoFecha(lead.last_activity_at) }}</dd>
              </dl>
              <div v-if="lead.score_reason" class="mkt-lead-ficha__bloque">
                <div class="mkt-lead-ficha__bloque-titulo">Razón de la puntuación</div>
                <p>{{ lead.score_reason }}</p>
              </div>
              <div v-if="lead.notes" class="mkt-lead-ficha__bloque">
                <div class="mkt-lead-ficha__bloque-titulo">Notas</div>
                <p>{{ lead.notes }}</p>
              </div>
              <div v-if="lead.lost_reason" class="mkt-lead-ficha__bloque">
                <div class="mkt-lead-ficha__bloque-titulo">Motivo de pérdida</div>
                <p>{{ lead.lost_reason }}</p>
              </div>
            </mkt-tarjeta-panel>
          </div>

          <mkt-tarjeta-panel titulo="Actividad" subtitulo="Lo más reciente primero" tono="verde" icono-fa="fa-history" :cargando="cargandoActividad">
            <mkt-estado-vacio v-if="errorActividad" icono="cloud_off" titulo="No se pudo cargar la actividad" :texto="errorActividad" />
            <lead-activity-timeline v-else :activities="actividades" />
          </mkt-tarjeta-panel>
        </div>
      </template>

      <!-- Edición -->
      <mkt-dialogo v-model="edicionAbierta" titulo="Editar lead" :guardando="guardando" :color-marca="contexto?.empresa_activa?.color_primario">
        <q-banner v-if="errorEdicion" rounded dense class="mkt-lead-ficha__aviso" role="alert">{{ errorEdicion }}</q-banner>
        <q-input v-model="edicion.full_name" label="Nombre completo *" outlined dense maxlength="150" :error="!!errores.full_name" :error-message="errores.full_name" />
        <div class="mkt-lead-ficha__dos">
          <q-input v-model="edicion.phone" label="Teléfono" outlined dense :error="!!errores.phone" :error-message="errores.phone" />
          <q-input v-model="edicion.whatsapp" label="WhatsApp" outlined dense :error="!!errores.whatsapp" :error-message="errores.whatsapp" />
        </div>
        <q-input v-model="edicion.email" label="Correo electrónico" type="email" outlined dense :error="!!errores.email" :error-message="errores.email" />
        <div class="mkt-lead-ficha__dos">
          <q-input v-model="edicion.city" label="Ciudad" outlined dense :error="!!errores.city" :error-message="errores.city" />
          <q-select v-model="edicion.status" :options="opcionesEstado" emit-value map-options label="Estado" outlined dense options-dense :error="!!errores.status" :error-message="errores.status">
            <template #option="scope">
              <q-item v-bind="scope.itemProps" class="mkt-lead-ficha__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
            </template>
          </q-select>
        </div>
        <q-input v-if="edicion.status === 'lost'" v-model="edicion.lost_reason" label="Motivo de pérdida" outlined dense :error="!!errores.lost_reason" :error-message="errores.lost_reason" />
        <q-input v-model="edicion.notes" label="Notas" type="textarea" autogrow outlined dense :error="!!errores.notes" :error-message="errores.notes" />
        <template #acciones>
          <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="edicionAbierta = false" />
          <mkt-boton tipo="guardar" etiqueta="Guardar cambios" :cargando="guardando" @click="guardar" />
        </template>
      </mkt-dialogo>
    </div>
  </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import {
  MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktNavegacionModulo, MktDialogo,
  MktBoton, MktFichaEncabezado, MktSelectorEmpresa,
  ESTADOS_LEAD, formatoFecha, mensajeDeError, erroresPorCampo, puedePermiso,
} from "./kit";
import LeadSourceChip from "./LeadSourceChip.vue";
import LeadScoreBadge from "./LeadScoreBadge.vue";
import LeadStatusBadge from "./LeadStatusBadge.vue";
import LeadActivityTimeline from "./LeadActivityTimeline.vue";

const CAMPOS_EDICION = ["full_name", "phone", "whatsapp", "email", "city", "status", "lost_reason", "notes"];

/**
 * Ficha de lead (/marketing/leads/{id}) — Fase 1: rediseño con el kit.
 * Mismos endpoints: GET leads/{id}, GET leads/{id}/activities, POST leads/{id}/score, PUT leads/{id}.
 * 404 (no existe o es de otra empresa) → «No encontrado», nunca datos ajenos.
 */
export default {
  name: "MarketingLeadDetail",
  components: {
    MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktNavegacionModulo, MktDialogo,
    MktBoton, MktFichaEncabezado, MktSelectorEmpresa,
    LeadSourceChip, LeadScoreBadge, LeadStatusBadge, LeadActivityTimeline,
  },
  props: { leadId: { type: [String, Number], default: null } },
  data() {
    return {
      contexto: null,
      estado: "cargando", // cargando | listo | no_encontrado | sin_permiso | error
      mensajeError: "",
      lead: null,
      actividades: [],
      cargandoActividad: false,
      errorActividad: "",
      calificando: false,
      edicionAbierta: false,
      edicion: {},
      errores: {},
      errorEdicion: "",
      guardando: false,
      opcionesEstado: ESTADOS_LEAD.map((e) => ({ label: e.label, value: e.value })),
    };
  },
  computed: {
    id() {
      // El id lo da el servidor: con la navegación SPA, location.pathname todavía es la página anterior.
      if (Number(this.leadId) > 0) return String(Number(this.leadId));
      const ultimo = window.location.pathname.split("/").filter(Boolean).pop() ?? "";
      return /^\d+$/.test(ultimo) ? ultimo : "";
    },
    titulo() {
      if (this.estado === "no_encontrado") return "Lead no encontrado";
      return this.lead?.full_name || this.lead?.phone || "Lead";
    },
    subtitulo() {
      if (!this.lead) return "Ficha del lead";
      const asignado = this.lead.assigned_user?.name;
      return asignado ? `Asignado a ${asignado}` : "Sin asignar";
    },
    datos() {
      const l = this.lead || {};
      const direccion = [l.address, l.neighborhood, l.city, l.state, l.postal_code].filter(Boolean).join(", ");
      return [
        { icono: "phone", etiqueta: "Teléfono", valor: l.phone || "—" },
        { icono: "chat", etiqueta: "WhatsApp", valor: l.whatsapp || "—" },
        { icono: "email", etiqueta: "Correo", valor: l.email || "—" },
        { icono: "location_on", etiqueta: "Dirección", valor: direccion || "—" },
        { icono: "dynamic_form", etiqueta: "Formulario", valor: l.lead_form?.name || "—" },
        { icono: "person", etiqueta: "Asignado a", valor: l.assigned_user?.name || "Sin asignar" },
      ];
    },
  },
  mounted() {
    this.cargar();
  },
  methods: {
    formatoFecha,
    puede(p) {
      return puedePermiso(this.$store, p);
    },
    async cargar() {
      if (!this.id) {
        this.estado = "no_encontrado";
        return;
      }
      this.estado = "cargando";
      try {
        const { data } = await axios.get(`/api/marketing/leads/${this.id}`);
        this.lead = data?.data ?? data;
        this.estado = "listo";
        this.cargarActividad();
      } catch (e) {
        const s = e?.response?.status;
        if (s === 404) this.estado = "no_encontrado";
        else if (s === 403) this.estado = "sin_permiso";
        else {
          this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
          this.estado = "error";
        }
      }
    },
    async cargarActividad() {
      this.cargandoActividad = true;
      this.errorActividad = "";
      try {
        const { data } = await axios.get(`/api/marketing/leads/${this.id}/activities`);
        const lista = data?.data ?? data;
        this.actividades = Array.isArray(lista) ? lista : [];
      } catch (e) {
        this.errorActividad = mensajeDeError(e, "Intenta de nuevo en un momento.");
      } finally {
        this.cargandoActividad = false;
      }
    },
    async recalificar() {
      if (this.calificando) return;
      this.calificando = true;
      try {
        await axios.post(`/api/marketing/leads/${this.id}/score`);
        message("Calificación en cola. Actualiza en unos segundos para ver la nueva puntuación.");
      } catch (e) {
        message(mensajeDeError(e, "No se pudo pedir la calificación."), "error");
      } finally {
        this.calificando = false;
      }
    },
    abrirEdicion() {
      this.edicion = Object.fromEntries(CAMPOS_EDICION.map((k) => [k, this.lead?.[k] ?? (k === "status" ? "new" : "")]));
      this.errores = {};
      this.errorEdicion = "";
      this.edicionAbierta = true;
    },
    async guardar() {
      if (this.guardando) return;
      this.errores = {};
      this.errorEdicion = "";
      if (!String(this.edicion.full_name || "").trim()) {
        this.errores = { full_name: "Escribe el nombre." };
        return;
      }
      this.guardando = true;
      try {
        const payload = Object.fromEntries(
          Object.entries(this.edicion).map(([k, v]) => [k, typeof v === "string" && v.trim() === "" ? null : v]),
        );
        const { data } = await axios.put(`/api/marketing/leads/${this.id}`, payload);
        this.lead = { ...this.lead, ...(data?.data ?? data) };
        this.edicionAbierta = false;
        message("Cambios guardados.");
        this.cargarActividad();
      } catch (e) {
        this.errores = erroresPorCampo(e);
        if (!Object.keys(this.errores).length) this.errorEdicion = mensajeDeError(e, "No se pudieron guardar los cambios.");
      } finally {
        this.guardando = false;
      }
    },
  },
};
</script>

<style scoped>
/* Ficha de lead = ficha de Cliente: pestañas → tarjeta de encabezado → bloques con cabecera de color. */
.mkt-lead-ficha { min-width: 0; }
.mkt-lead-ficha__rejilla {
  display: grid;
  grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
  gap: 14px;
  align-items: start;
}
.mkt-lead-ficha__columna { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.mkt-lead-ficha__suave { color: var(--mkt-texto-suave); }
.mkt-lead-ficha__puntuacion :deep(.mkt-score) { font-size: 0.95rem; }
/* Etiqueta : valor, como los campos de la ficha de Cliente (etiqueta en mayúsculas pequeñas y gris). */
.mkt-lead-ficha__lista {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 10px 18px;
  margin: 0;
}
.mkt-lead-ficha__lista dt {
  grid-column: span 1;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: #6b7280;
  align-self: center;
}
.mkt-lead-ficha__lista dd { margin: 0; font-size: 14px; color: var(--mkt-texto); overflow-wrap: anywhere; min-width: 0; }
.mkt-lead-ficha__bloque { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--mkt-borde); }
.mkt-lead-ficha__bloque-titulo { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; color: #6b7280; margin-bottom: 4px; }
.mkt-lead-ficha__bloque p { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); white-space: pre-line; overflow-wrap: anywhere; }
.mkt-lead-ficha__dos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--mkt-esp-1); }
.mkt-lead-ficha__opcion { --bs-gutter-x: 0; }
.mkt-lead-ficha__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-lead-ficha__aviso { color: var(--mkt-peligro); background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent); }
@media (max-width: 1023px) {
  .mkt-lead-ficha__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
  .mkt-lead-ficha__lista { grid-template-columns: minmax(0, 1fr); gap: 2px; }
  .mkt-lead-ficha__lista dd { margin-bottom: 8px; }
  .mkt-lead-ficha__dos { grid-template-columns: minmax(0, 1fr); }
}
</style>
