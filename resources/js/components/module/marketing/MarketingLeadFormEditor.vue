<template>
  <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
    <div class="mkt-editor">
      <mkt-encabezado-pantalla
        :titulo="esEdicion ? 'Editar formulario' : 'Nuevo formulario'"
        con-selector-empresa
        @contexto="(c) => (contexto = c)"
      >
        <template #acciones>
          <mkt-boton tipo="volver" etiqueta="Formularios" href="/marketing/lead-forms" :deshabilitado="guardando" />
          <template v-if="estado === 'listo'">
            <mkt-boton tipo="secundario" etiqueta="Ver público" :deshabilitado="!esEdicion || !form.slug" @click="vistaPrevia" />
            <mkt-boton v-if="puedeGuardar" tipo="guardar" etiqueta="Guardar" :cargando="guardando" @click="guardar" />
          </template>
        </template>
      </mkt-encabezado-pantalla>

      <div v-if="estado === 'cargando'" class="mkt-editor__rejilla">
        <mkt-tarjeta-panel titulo="Configuración" tono="lila" icono-fa="fa-cog" cargando />
        <mkt-tarjeta-panel titulo="Campos del formulario" tono="verde" icono-fa="fa-list" cargando />
      </div>

      <mkt-tarjeta-panel v-else-if="estado === 'no_encontrado'">
        <mkt-estado-vacio icono="search_off" titulo="No encontrado" texto="Este formulario no existe o ya se eliminó." />
      </mkt-tarjeta-panel>

      <mkt-tarjeta-panel v-else-if="estado === 'sin_permiso'">
        <mkt-estado-vacio icono="lock" titulo="No puedes editar formularios" texto="No tienes acceso a los formularios. Si lo necesitas, pídelo a Irving." />
      </mkt-tarjeta-panel>

      <mkt-tarjeta-panel v-else-if="estado === 'error'">
        <mkt-estado-vacio icono="cloud_off" titulo="No se pudo cargar el formulario" :texto="mensajeError" />
      </mkt-tarjeta-panel>

      <template v-else>
        <q-banner v-if="errorGeneral" rounded dense class="mkt-editor__aviso" role="alert">
          <template #avatar><q-icon name="error_outline" /></template>
          {{ errorGeneral }}
        </q-banner>

        <div class="mkt-editor__rejilla">
          <div class="mkt-editor__columna">
            <mkt-tarjeta-panel titulo="Configuración" tono="lila" icono-fa="fa-cog">
              <div class="mkt-editor__campos">
                <q-input v-model="form.name" label="Nombre del formulario *" outlined dense maxlength="100"
                  :error="!!errores.name" :error-message="errores.name" @update:model-value="slugAutomatico" />
                <q-input v-model="form.slug" label="Identificador (dirección) *" outlined dense maxlength="60" prefix="lead-form/"
                  :error="!!errores.slug" :error-message="errores.slug" hint="Solo minúsculas, números y guiones"
                  @update:model-value="slugTocado = true; errores.slug = ''" />
                <q-input v-model="form.description" label="Descripción" outlined dense type="textarea" autogrow />
                <q-select v-model="form.assign_to_source_id" :options="fuentes" option-value="id" option-label="name" emit-value map-options
                  label="Fuente de los leads *" outlined dense clearable options-dense
                  :error="!!errores.assign_to_source_id" :error-message="errores.assign_to_source_id">
                  <template #option="scope">
                    <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.name }}</q-item-section></q-item>
                  </template>
                </q-select>
                <q-toggle v-model="form.auto_scoring" color="positive" label="Calificar automáticamente con IA" />
                <q-toggle v-model="form.active" color="positive" label="Formulario activo" />
              </div>
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel titulo="Después de enviar" tono="azul" icono-fa="fa-paper-plane">
              <div class="mkt-editor__campos">
                <q-input v-model="form.thank_you_message" label="Mensaje de agradecimiento" outlined dense type="textarea" autogrow />
                <q-input v-model="form.thank_you_redirect_url" label="Redirigir a una dirección (opcional)" outlined dense
                  :error="!!errores.thank_you_redirect_url" :error-message="errores.thank_you_redirect_url" />
              </div>
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel titulo="Límite de envíos" subtitulo="Protege el formulario de envíos masivos" tono="crema" icono-fa="fa-sliders-h">
              <div class="mkt-editor__dos">
                <q-input v-model.number="form.rate_limit_per_minute" label="Por minuto" outlined dense type="number" min="1" />
                <q-input v-model.number="form.rate_limit_per_hour" label="Por hora" outlined dense type="number" min="1" />
              </div>
            </mkt-tarjeta-panel>
          </div>

          <div class="mkt-editor__columna">
            <mkt-tarjeta-panel titulo="Campos del formulario" :subtitulo="`${form.fields_schema.length} ${form.fields_schema.length === 1 ? 'campo' : 'campos'}`" tono="verde" icono-fa="fa-list">
              <template #acciones>
                <mkt-boton tipo="secundario" chico icono-fa="fa-plus" etiqueta="Agregar campo" @click="agregarCampo" />
              </template>
              <p v-if="errores.fields_schema" class="mkt-editor__error-campo" role="alert">{{ errores.fields_schema }}</p>
              <mkt-estado-vacio v-if="!form.fields_schema.length" icono="playlist_add" titulo="Sin campos" texto="Agrega al menos un campo para que el formulario pueda capturar leads." />
              <ol v-else class="mkt-editor__lista">
                <li v-for="(campo, i) in form.fields_schema" :key="campo._uid" class="mkt-editor__campo">
                  <div class="mkt-editor__campo-fila">
                    <q-select v-model="campo.type" :options="tiposCampo" emit-value map-options label="Tipo" outlined dense options-dense class="mkt-editor__tipo">
                      <template #option="scope">
                        <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                      </template>
                    </q-select>
                    <q-input v-model="campo.label" label="Etiqueta *" outlined dense class="mkt-editor__etiqueta" />
                    <q-input v-model="campo.key" label="Clave" outlined dense class="mkt-editor__clave" />
                  </div>
                  <div class="mkt-editor__campo-pie">
                    <q-toggle v-model="campo.required" color="positive" label="Obligatorio" dense />
                    <div class="mkt-editor__campo-botones">
                      <q-btn flat dense round icon="arrow_upward" :disable="i === 0" :aria-label="`Subir ${campo.label}`" @click="mover(i, -1)" />
                      <q-btn flat dense round icon="arrow_downward" :disable="i === form.fields_schema.length - 1" :aria-label="`Bajar ${campo.label}`" @click="mover(i, 1)" />
                      <q-btn flat dense round color="negative" icon="delete" :aria-label="`Quitar ${campo.label}`" @click="quitar(i)" />
                    </div>
                  </div>
                  <q-input v-if="campo.type === 'select' || campo.type === 'radio'" v-model="campo._optionsRaw" label="Opciones (una por línea)"
                    outlined dense type="textarea" autogrow @update:model-value="(v) => (campo.options = String(v || '').split('\n').map((s) => s.trim()).filter(Boolean))" />
                </li>
              </ol>
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel titulo="Vista previa" subtitulo="Así lo verá quien llene el formulario" tono="gris" icono-fa="fa-eye">
              <div class="mkt-editor__previa">
                <div v-if="form.name" class="mkt-editor__previa-titulo">{{ form.name }}</div>
                <p v-if="form.description" class="mkt-editor__previa-desc">{{ form.description }}</p>
                <template v-for="campo in form.fields_schema" :key="`p-${campo._uid}`">
                  <q-select v-if="campo.type === 'select'" :label="campo.label + (campo.required ? ' *' : '')" :options="campo.options || []" outlined dense readonly />
                  <div v-else-if="campo.type === 'radio'" class="mkt-editor__previa-grupo">
                    <span>{{ campo.label }}{{ campo.required ? " *" : "" }}</span>
                    <q-radio v-for="op in campo.options || []" :key="op" :model-value="null" :val="op" :label="op" dense disable />
                  </div>
                  <q-checkbox v-else-if="campo.type === 'checkbox'" :model-value="false" :label="campo.label" disable />
                  <q-input v-else :label="campo.label + (campo.required ? ' *' : '')" :type="campo.type === 'textarea' ? 'textarea' : campo.type" outlined dense readonly
                    :stack-label="campo.type === 'date'" />
                </template>
                <mkt-boton v-if="form.fields_schema.length" tipo="secundario" etiqueta="Enviar" deshabilitado class="mkt-editor__previa-enviar" />
              </div>
            </mkt-tarjeta-panel>
          </div>
        </div>
      </template>
    </div>
  </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import {
  MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktBoton,
  mensajeDeError, erroresPorCampo, puedePermiso,
} from "./kit";

let contador = 0;
const uid = () => `c${Date.now()}-${++contador}`;
const CAMPOS_BASE = () => [
  { _uid: uid(), key: "full_name", type: "text", label: "Nombre completo", required: true, _optionsRaw: "" },
  { _uid: uid(), key: "phone", type: "tel", label: "Teléfono", required: true, _optionsRaw: "" },
  { _uid: uid(), key: "email", type: "email", label: "Correo electrónico", required: false, _optionsRaw: "" },
];

/**
 * Editor de formulario (/marketing/lead-forms/create y /{id}/edit) — Fase 1: rediseño con el kit.
 * Mismos endpoints: GET/POST/PUT /api/marketing/lead-forms, GET /api/marketing/lead-sources.
 * El error de identificador repetido (422 `slug`) se muestra en su campo.
 */
export default {
  name: "MarketingLeadFormEditor",
  components: { MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktBoton },
  props: { formId: { type: [String, Number], default: null } },
  data() {
    return {
      contexto: null,
      estado: "cargando", // cargando | listo | no_encontrado | sin_permiso | error
      mensajeError: "",
      guardando: false,
      fuentes: [],
      errores: {},
      errorGeneral: "",
      slugTocado: false,
      form: {
        name: "", slug: "", description: "", assign_to_source_id: null,
        auto_scoring: true, active: true,
        thank_you_message: "¡Gracias! Nos pondremos en contacto pronto.",
        thank_you_redirect_url: "",
        rate_limit_per_minute: 5, rate_limit_per_hour: 30,
        fields_schema: CAMPOS_BASE(),
        styling: {},
      },
      tiposCampo: [
        { label: "Texto", value: "text" },
        { label: "Correo", value: "email" },
        { label: "Teléfono", value: "tel" },
        { label: "Número", value: "number" },
        { label: "Fecha", value: "date" },
        { label: "Texto largo", value: "textarea" },
        { label: "Lista desplegable", value: "select" },
        { label: "Opción única", value: "radio" },
        { label: "Casilla", value: "checkbox" },
      ],
    };
  },
  computed: {
    id() {
      // El id lo da el servidor (form-id; vacío = alta nueva): con la navegación SPA, location.pathname
      // todavía es la página anterior. La URL solo es respaldo si falta la prop.
      if (this.formId !== null && this.formId !== undefined) return Number(this.formId) > 0 ? String(Number(this.formId)) : "";
      return (window.location.pathname.match(/lead-forms\/(\d+)(\/|$)/) || [])[1] || "";
    },
    esEdicion() {
      return !!this.id;
    },
    puedeGuardar() {
      return puedePermiso(this.$store, this.esEdicion ? "marketing.forms.update" : "marketing.forms.create");
    },
  },
  mounted() {
    this.cargarFuentes();
    if (this.esEdicion) this.cargarFormulario();
    else this.estado = "listo";
  },
  methods: {
    async cargarFormulario() {
      this.estado = "cargando";
      try {
        const { data } = await axios.get(`/api/marketing/lead-forms/${this.id}`);
        const f = data?.data ?? data;
        const campos = (Array.isArray(f.fields_schema) ? f.fields_schema : []).map((c) => ({
          ...c,
          _uid: uid(),
          _optionsRaw: (c.options || []).join("\n"),
        }));
        this.form = { ...this.form, ...f, fields_schema: campos };
        this.slugTocado = true;
        this.estado = "listo";
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
    async cargarFuentes() {
      try {
        const { data } = await axios.get("/api/marketing/lead-sources");
        this.fuentes = Array.isArray(data) ? data : data?.data || [];
      } catch (e) {
        this.fuentes = [];
      }
    },
    slugAutomatico(nombre) {
      if (this.esEdicion || this.slugTocado) return;
      this.form.slug = String(nombre || "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "")
        .slice(0, 60);
    },
    agregarCampo() {
      this.form.fields_schema.push({ _uid: uid(), key: `campo_${Date.now()}`, type: "text", label: "Nuevo campo", required: false, _optionsRaw: "" });
    },
    quitar(i) {
      this.form.fields_schema.splice(i, 1);
    },
    mover(i, dir) {
      const arr = this.form.fields_schema;
      const j = i + dir;
      if (j < 0 || j >= arr.length) return;
      [arr[i], arr[j]] = [arr[j], arr[i]];
    },
    validarLocal() {
      const e = {};
      if (!String(this.form.name || "").trim()) e.name = "Escribe el nombre del formulario.";
      if (!/^[a-z0-9-]+$/.test(String(this.form.slug || ""))) e.slug = "Usa solo minúsculas, números y guiones.";
      if (!this.esEdicion && !this.form.assign_to_source_id) e.assign_to_source_id = "Elige la fuente de los leads.";
      if (!this.form.fields_schema.length) e.fields_schema = "Agrega al menos un campo.";
      return e;
    },
    async guardar() {
      if (this.guardando) return;
      this.errorGeneral = "";
      this.errores = this.validarLocal();
      if (Object.keys(this.errores).length) return;
      this.guardando = true;
      try {
        const payload = { ...this.form };
        payload.fields_schema = payload.fields_schema.map(({ _optionsRaw, _uid, ...c }) => c);
        if (this.esEdicion) await axios.put(`/api/marketing/lead-forms/${this.id}`, payload);
        else await axios.post("/api/marketing/lead-forms", payload);
        message("Formulario guardado.");
        window.location.href = "/marketing/lead-forms";
      } catch (e) {
        this.errores = erroresPorCampo(e);
        // Un 422 de un campo que no está en pantalla (o cualquier otro error) se muestra arriba.
        const visibles = ["name", "slug", "assign_to_source_id", "thank_you_redirect_url", "fields_schema"];
        if (!Object.keys(this.errores).some((k) => visibles.includes(k))) {
          this.errorGeneral = mensajeDeError(e, "No se pudo guardar el formulario.");
        }
        this.guardando = false;
      }
    },
    vistaPrevia() {
      if (this.form.slug) window.open(`/public/marketing/lead-form/${encodeURIComponent(this.form.slug)}`, "_blank", "noopener");
    },
  },
};
</script>

<style scoped>
.mkt-editor { min-width: 0; }
/* Bloques como la ficha de Cliente: dos columnas, separación de 14 px. */
.mkt-editor__rejilla {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
  gap: 14px;
  align-items: start;
}
.mkt-editor__columna { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.mkt-editor__campos { display: flex; flex-direction: column; gap: var(--mkt-esp-1); min-width: 0; }
.mkt-editor__campos > * { min-width: 0; max-width: 100%; }
/* El texto de ayuda de un campo no se sale de la tarjeta a 375 px. */
.mkt-editor :deep(.q-field__bottom) { white-space: normal; overflow-wrap: anywhere; }
.mkt-editor :deep(.q-field__messages) { min-width: 0; }
.mkt-editor__dos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--mkt-esp-1); }
.mkt-editor__opcion { --bs-gutter-x: 0; }
.mkt-editor__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-editor__lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-editor__campo {
  display: flex;
  flex-direction: column;
  gap: var(--mkt-esp-1);
  padding: 12px;
  border: 1px solid var(--mkt-borde);
  border-radius: var(--mkt-radio);
  background: var(--mkt-fondo-suave);
  min-width: 0;
}
.mkt-editor__campo-fila { display: grid; grid-template-columns: 160px minmax(0, 2fr) minmax(0, 1fr); gap: var(--mkt-esp-1); }
.mkt-editor__campo-pie { display: flex; align-items: center; justify-content: space-between; gap: var(--mkt-esp-1); flex-wrap: wrap; }
.mkt-editor__campo-botones { display: flex; gap: 2px; }
.mkt-editor__error-campo { margin: 0 0 var(--mkt-esp-1); font-size: 13px; color: var(--mkt-peligro); }
.mkt-editor__aviso { color: var(--mkt-peligro); background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent); }
.mkt-editor__previa { display: flex; flex-direction: column; gap: var(--mkt-esp-1); max-width: 480px; }
.mkt-editor__previa-titulo { font-size: 18px; font-weight: 600; color: var(--mkt-texto); }
.mkt-editor__previa-desc { margin: 0 0 var(--mkt-esp-1); font-size: 14px; color: var(--mkt-texto-suave); white-space: pre-line; }
.mkt-editor__previa-grupo { display: flex; flex-direction: column; gap: 2px; font-size: 14px; color: var(--mkt-texto); }
.mkt-editor__previa-enviar { align-self: flex-start; margin-top: var(--mkt-esp-1); }
@media (max-width: 1023px) {
  .mkt-editor__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
  .mkt-editor__campo-fila { grid-template-columns: minmax(0, 1fr); }
}
</style>
