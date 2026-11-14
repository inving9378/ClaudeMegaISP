<template>
  <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
    <div class="mkt-formularios">
      <mkt-encabezado-pantalla
        titulo="Formularios de captura"
        con-selector-empresa
        @contexto="(c) => (contexto = c)"
      >
        <template #acciones>
          <mkt-boton
            v-if="puede('marketing.forms.create') && estado === 'listo'"
            tipo="crear"
            etiqueta="Nuevo formulario"
            href="/marketing/lead-forms/create"
          />
        </template>

        <mkt-estado-vacio
          v-if="estado === 'sin_permiso'"
          icono="lock"
          titulo="No puedes ver los formularios"
          texto="No tienes acceso a los formularios. Si lo necesitas, pídelo a Irving."
        />

        <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar los formularios" :texto="mensajeError" />

        <mkt-tabla-ejecutiva
          v-else
          :columnas="columnas"
          :filas="formularios"
          :cargando="estado === 'cargando'"
          vacio-icono="dynamic_form"
          vacio-titulo="Aún no hay formularios"
          vacio-texto="Crea tu primer formulario para capturar leads desde tu sitio web."
          @actualizar="cargar"
        >

          <template #body-cell-name="props">
            <q-td :props="props">
              <div class="mkt-formularios__nombre">{{ props.row.name }}</div>
              <span class="mkt-formularios__slug">{{ props.row.slug }}</span>
            </q-td>
          </template>

          <template #body-cell-active="props">
            <q-td :props="props">
              <mkt-insignia-estado :texto="props.row.active ? 'Activo' : 'Inactivo'" :tono="props.row.active ? 'exito' : 'neutro'" />
            </q-td>
          </template>

          <template #body-cell-fuente="props">
            <q-td :props="props">
              <lead-source-chip v-if="props.row.assign_to_source" :source="props.row.assign_to_source" />
              <span v-else class="mkt-formularios__suave">—</span>
            </q-td>
          </template>

          <template #body-cell-acciones="props">
            <q-td :props="props" class="mkt-formularios__acciones">
              <q-btn flat dense round icon="code" :aria-label="`Código de ${props.row.name}`" :loading="cargandoCodigo === props.row.id" @click.stop="mostrarCodigo(props.row)">
                <q-tooltip>Código para tu sitio</q-tooltip>
              </q-btn>
              <q-btn flat dense round icon="open_in_new" :aria-label="`Ver ${props.row.name}`" @click.stop="vistaPrevia(props.row)">
                <q-tooltip>Ver formulario público</q-tooltip>
              </q-btn>
              <q-btn v-if="puede('marketing.forms.update')" flat dense round icon="edit" :aria-label="`Editar ${props.row.name}`" :href="`/marketing/lead-forms/${props.row.id}/edit`">
                <q-tooltip>Editar</q-tooltip>
              </q-btn>
              <q-btn v-if="puede('marketing.forms.delete')" flat dense round color="negative" icon="delete" :aria-label="`Eliminar ${props.row.name}`" @click.stop="confirmarBorrado(props.row)">
                <q-tooltip>Eliminar</q-tooltip>
              </q-btn>
            </q-td>
          </template>
        </mkt-tabla-ejecutiva>
      </mkt-encabezado-pantalla>

      <!-- Código de integración -->
      <mkt-dialogo v-model="codigoAbierto" :titulo="`Código de «${elegido?.name || ''}»`" :ancho="680" :color-marca="contexto?.empresa_activa?.color_primario">
        <q-banner v-if="codigoAproximado" rounded dense class="mkt-formularios__nota">
          No se pudo leer el código del servidor; este se armó con la dirección actual.
        </q-banner>
        <div v-for="c in bloquesCodigo" :key="c.clave" class="mkt-formularios__codigo">
          <div class="mkt-formularios__codigo-cabeza">
            <div>
              <div class="mkt-formularios__codigo-titulo">{{ c.titulo }}</div>
              <div class="mkt-formularios__codigo-ayuda">{{ c.ayuda }}</div>
            </div>
            <mkt-boton tipo="secundario" chico etiqueta="Copiar" icono-fa="fa-copy" @click="copiar(c.valor)" />
          </div>
          <pre class="mkt-formularios__pre">{{ c.valor }}</pre>
        </div>
        <template #acciones>
          <mkt-boton tipo="volver" etiqueta="Cerrar" @click="codigoAbierto = false" />
        </template>
      </mkt-dialogo>

      <!-- Confirmar borrado -->
      <mkt-dialogo v-model="borradoAbierto" titulo="¿Eliminar formulario?" :guardando="borrando" :ancho="440" :color-marca="contexto?.empresa_activa?.color_primario">
        <p class="mkt-formularios__texto">
          Se eliminará «{{ elegido?.name }}» y su dirección pública dejará de recibir envíos. Los leads ya capturados se conservan.
        </p>
        <template #acciones>
          <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="borrando" @click="borradoAbierto = false" />
          <mkt-boton tipo="eliminar" etiqueta="Eliminar" :cargando="borrando" @click="borrar" />
        </template>
      </mkt-dialogo>
    </div>
  </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import {
  MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktTarjetaKpi, MktTablaEjecutiva, MktEstadoVacio, MktInsigniaEstado,
  MktDialogo, MktBoton, formatoNumero, formatoFecha, mensajeDeError, puedePermiso,
} from "./kit";
import LeadSourceChip from "./LeadSourceChip.vue";

/**
 * Formularios de captura (/marketing/lead-forms) — Fase 1: rediseño con el kit; mismos endpoints
 * (GET/DELETE /api/marketing/lead-forms, GET …/{id}/embed-code).
 */
export default {
  name: "MarketingLeadFormsView",
  components: {
    MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktTarjetaKpi, MktTablaEjecutiva, MktEstadoVacio, MktInsigniaEstado,
    MktDialogo, MktBoton, LeadSourceChip,
  },
  data() {
    return {
      contexto: null,
      estado: "cargando",
      mensajeError: "",
      formularios: [],
      elegido: null,
      codigoAbierto: false,
      codigo: {},
      codigoAproximado: false,
      cargandoCodigo: null,
      borradoAbierto: false,
      borrando: false,
      columnas: [
        { name: "name", label: "Formulario", field: "name", align: "left", sortable: true },
        { name: "active", label: "Estado", field: "active", align: "left" },
        { name: "campos", label: "Campos", field: (r) => (Array.isArray(r.fields_schema) ? r.fields_schema.length : 0), align: "right", ocultarEnMovil: true },
        { name: "fuente", label: "Fuente", field: "assign_to_source", align: "left", ocultarEnMovil: true },
        { name: "envios", label: "Envíos", field: "submissions_count", align: "right", format: (v) => formatoNumero(v) },
        { name: "creado", label: "Creado", field: "created_at", align: "left", ocultarEnMovil: true, format: (v) => formatoFecha(v) },
        { name: "acciones", label: "", field: "id", align: "right" },
      ],
    };
  },
  computed: {
    activos() {
      return this.formularios.filter((f) => f.active).length;
    },
    bloquesCodigo() {
      const c = this.codigo || {};
      return [
        { clave: "iframe", titulo: "iFrame", ayuda: "Pégalo donde quieras que aparezca el formulario.", valor: c.iframe || "" },
        { clave: "script", titulo: "Script", ayuda: "Inserta el formulario y ajusta su alto solo.", valor: c.script || "" },
        { clave: "url", titulo: "Dirección directa", ayuda: "Para compartir en redes o por WhatsApp.", valor: c.url || "" },
      ];
    },
  },
  mounted() {
    this.cargar();
  },
  methods: {
    puede(p) {
      return puedePermiso(this.$store, p);
    },
    async cargar() {
      this.estado = "cargando";
      try {
        const { data } = await axios.get("/api/marketing/lead-forms");
        const lista = data?.data ?? data;
        this.formularios = Array.isArray(lista) ? lista : [];
        this.estado = "listo";
      } catch (e) {
        if (e?.response?.status === 403) {
          this.estado = "sin_permiso";
          return;
        }
        this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
        this.estado = "error";
      }
    },
    vistaPrevia(f) {
      window.open(`/public/marketing/lead-form/${encodeURIComponent(f.slug)}`, "_blank", "noopener");
    },
    async mostrarCodigo(f) {
      this.elegido = f;
      this.cargandoCodigo = f.id;
      this.codigoAproximado = false;
      try {
        const { data } = await axios.get(`/api/marketing/lead-forms/${f.id}/embed-code`);
        this.codigo = data;
      } catch (e) {
        const base = window.location.origin;
        const url = `${base}/public/marketing/lead-form/${f.slug}`;
        this.codigo = {
          url,
          iframe: `<iframe src="${url}" width="100%" height="500" frameborder="0" scrolling="no"></iframe>`,
          script: `<div id="megaisp-lead-form" data-form-slug="${f.slug}"></div>\n<script src="${base}/public/marketing/embed.js" async><\/script>`,
        };
        this.codigoAproximado = true;
      } finally {
        this.cargandoCodigo = null;
      }
      this.codigoAbierto = true;
    },
    copiar(texto) {
      if (!navigator.clipboard) {
        message("Tu navegador no permite copiar; selecciona el texto y cópialo a mano.", "warning");
        return;
      }
      navigator.clipboard.writeText(texto).then(
        () => message("Copiado al portapapeles."),
        () => message("No se pudo copiar; selecciona el texto y cópialo a mano.", "warning"),
      );
    },
    confirmarBorrado(f) {
      this.elegido = f;
      this.borradoAbierto = true;
    },
    async borrar() {
      if (!this.elegido || this.borrando) return;
      this.borrando = true;
      try {
        await axios.delete(`/api/marketing/lead-forms/${this.elegido.id}`);
        this.formularios = this.formularios.filter((f) => f.id !== this.elegido.id);
        this.borradoAbierto = false;
        message(`Formulario «${this.elegido.name}» eliminado.`);
      } catch (e) {
        message(mensajeDeError(e, "No se pudo eliminar el formulario."), "error");
      } finally {
        this.borrando = false;
      }
    },
  },
};
</script>

<style scoped>
.mkt-formularios { min-width: 0; }
.mkt-formularios__nombre { font-weight: 500; }
.mkt-formularios__slug {
  font-size: 12px;
  padding: 1px 6px;
  border-radius: 4px;
  color: var(--mkt-texto-suave);
  background: var(--mkt-fondo-suave);
}
.mkt-formularios__suave { color: var(--mkt-texto-suave); }
.mkt-formularios__acciones { white-space: nowrap; }
.mkt-formularios__codigo { display: flex; flex-direction: column; gap: 6px; }
.mkt-formularios__codigo-cabeza { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--mkt-esp-1); }
.mkt-formularios__codigo-cabeza > div { min-width: 0; overflow-wrap: anywhere; }
.mkt-formularios__codigo-titulo { font-weight: 600; font-size: 14px; color: var(--mkt-texto); }
.mkt-formularios__codigo-ayuda { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-formularios__pre {
  margin: 0;
  padding: 12px;
  font-size: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-all;
  color: var(--mkt-texto);
  background: var(--mkt-fondo-suave);
  border: 1px solid var(--mkt-borde);
  border-radius: 6px;
  max-height: 160px;
  overflow: auto;
}
.mkt-formularios__nota { color: var(--mkt-texto); background: color-mix(in srgb, var(--mkt-aviso) 14%, transparent); }
.mkt-formularios__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
</style>
