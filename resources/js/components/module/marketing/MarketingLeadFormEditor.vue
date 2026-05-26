<template>
  <div class="q-pa-md">
    <!-- Header -->
    <div class="row items-center q-mb-lg q-gutter-md">
      <q-btn flat round icon="arrow_back" @click="() => window.location.href = '/marketing/lead-forms'" />
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ formId ? 'Editar formulario' : 'Nuevo formulario' }}</div>
      </div>
      <div class="col-auto q-gutter-xs">
        <q-btn flat label="Preview" icon="open_in_new" @click="preview" :disable="!form.slug" />
        <q-btn unelevated color="primary" label="Guardar" icon="save" :loading="saving" @click="save" />
      </div>
    </div>

    <div class="row q-gutter-md">
      <!-- Settings panel -->
      <div class="col-12 col-md-4">
        <q-card flat bordered>
          <q-card-section class="text-subtitle2 text-grey-6">Configuración</q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-input v-model="form.name" label="Nombre del formulario *" outlined dense
              @update:model-value="autoSlug" />
            <q-input v-model="form.slug" label="Slug (URL) *" outlined dense prefix="lead-form/">
              <template #hint><span class="text-caption text-grey-6">Solo letras, números y guiones</span></template>
            </q-input>
            <q-input v-model="form.description" label="Descripción" outlined dense type="textarea" rows="2" />
            <q-select v-model="form.assign_to_source_id" :options="sources" emit-value map-options
              option-value="id" option-label="name" label="Fuente de lead" outlined dense clearable />
            <q-toggle v-model="form.auto_scoring" label="Scoring automático con IA" />
            <q-toggle v-model="form.active" label="Formulario activo" />
          </q-card-section>

          <q-separator />
          <q-card-section class="text-subtitle2 text-grey-6">Mensaje de confirmación</q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-input v-model="form.thank_you_message" label="Mensaje tras enviar" outlined dense type="textarea" rows="3" />
            <q-input v-model="form.thank_you_redirect_url" label="Redirigir a URL (opcional)" outlined dense />
          </q-card-section>

          <q-separator />
          <q-card-section class="text-subtitle2 text-grey-6">Rate limiting</q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-input v-model.number="form.rate_limit_per_minute" label="Envíos por minuto" outlined dense type="number" min="1" />
            <q-input v-model.number="form.rate_limit_per_hour" label="Envíos por hora" outlined dense type="number" min="1" />
          </q-card-section>
        </q-card>
      </div>

      <!-- Field builder -->
      <div class="col">
        <q-card flat bordered>
          <q-card-section class="row items-center">
            <div class="text-subtitle2 text-grey-6 col">Campos del formulario</div>
            <q-btn flat dense icon="add" label="Agregar campo" color="primary" @click="addField" />
          </q-card-section>

          <q-list separator>
            <q-item v-if="form.fields_schema.length === 0" class="text-grey-6 text-center q-pa-lg">
              <q-item-section>No hay campos. Agrega al menos uno.</q-item-section>
            </q-item>

            <q-item v-for="(field, idx) in form.fields_schema" :key="field.key" class="q-pa-md">
              <q-item-section>
                <div class="row q-gutter-sm items-center">
                  <q-select v-model="field.type" :options="fieldTypes" emit-value map-options
                    label="Tipo" outlined dense style="min-width:140px" />
                  <q-input v-model="field.label" label="Etiqueta *" outlined dense class="col" />
                  <q-input v-model="field.key" label="Clave" outlined dense style="min-width:130px"
                    :hint="field.key" />
                  <q-toggle v-model="field.required" label="Req." dense />
                  <div class="q-gutter-xs">
                    <q-btn flat round dense icon="arrow_upward" size="sm" :disable="idx === 0" @click="moveField(idx, -1)" />
                    <q-btn flat round dense icon="arrow_downward" size="sm" :disable="idx === form.fields_schema.length - 1" @click="moveField(idx, 1)" />
                    <q-btn flat round dense icon="delete" size="sm" color="negative" @click="removeField(idx)" />
                  </div>
                </div>
                <div v-if="field.type === 'select' || field.type === 'radio'" class="q-mt-sm">
                  <q-input v-model="field._optionsRaw" label="Opciones (una por línea)" outlined dense type="textarea" rows="3"
                    @update:model-value="v => field.options = v.split('\n').map(s=>s.trim()).filter(Boolean)" />
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>

        <!-- Live preview -->
        <q-card flat bordered class="q-mt-md">
          <q-card-section class="text-subtitle2 text-grey-6">Vista previa</q-card-section>
          <q-card-section>
            <div v-for="field in form.fields_schema" :key="field.key" class="q-mb-sm">
              <q-input v-if="['text','email','tel','number','date','textarea'].includes(field.type)"
                :label="field.label + (field.required ? ' *' : '')"
                :type="field.type === 'textarea' ? undefined : field.type"
                outlined dense readonly />
              <q-select v-else-if="field.type === 'select'"
                :label="field.label + (field.required ? ' *' : '')"
                :options="field.options || []" outlined dense readonly />
              <div v-else-if="field.type === 'checkbox'" class="row items-center q-gutter-xs">
                <q-checkbox :label="field.label" disable />
              </div>
            </div>
            <q-btn v-if="form.fields_schema.length" unelevated color="primary" label="Enviar" class="q-mt-sm" disable />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

const DEFAULT_FIELDS = [
  { key: 'full_name', type: 'text', label: 'Nombre completo', required: true, _optionsRaw: '' },
  { key: 'phone', type: 'tel', label: 'Teléfono', required: true, _optionsRaw: '' },
  { key: 'email', type: 'email', label: 'Correo electrónico', required: false, _optionsRaw: '' },
];

export default {
  name: 'MarketingLeadFormEditor',
  props: { formId: { type: [String, Number], default: null } },
  data() {
    return {
      saving: false,
      sources: [],
      form: {
        name: '', slug: '', description: '', assign_to_source_id: null,
        auto_scoring: true, active: true,
        thank_you_message: '¡Gracias! Nos pondremos en contacto pronto.',
        thank_you_redirect_url: '',
        rate_limit_per_minute: 5, rate_limit_per_hour: 30,
        fields_schema: JSON.parse(JSON.stringify(DEFAULT_FIELDS)),
        styling: {},
      },
      fieldTypes: [
        { label: 'Texto', value: 'text' },
        { label: 'Email', value: 'email' },
        { label: 'Teléfono', value: 'tel' },
        { label: 'Número', value: 'number' },
        { label: 'Fecha', value: 'date' },
        { label: 'Área de texto', value: 'textarea' },
        { label: 'Lista desplegable', value: 'select' },
        { label: 'Opción múltiple', value: 'radio' },
        { label: 'Casilla', value: 'checkbox' },
      ],
    };
  },
  mounted() {
    const id = this.formId || (window.location.pathname.match(/lead-forms\/(\d+)/) || [])[1];
    if (id && id !== 'create') this.loadForm(id);
    this.loadSources();
  },
  methods: {
    async loadForm(id) {
      const { data } = await axios.get('/api/marketing/lead-forms/' + id);
      const f = data.data ?? data;
      // Restore _optionsRaw for select/radio fields
      (f.fields_schema || []).forEach(field => {
        field._optionsRaw = (field.options || []).join('\n');
      });
      this.form = { ...this.form, ...f };
    },
    async loadSources() {
      try {
        const { data } = await axios.get('/api/marketing/lead-sources');
        this.sources = data.data ?? data;
      } catch {}
    },
    autoSlug(val) {
      if (!this.formId) {
        this.form.slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      }
    },
    addField() {
      this.form.fields_schema.push({ key: 'campo_' + Date.now(), type: 'text', label: 'Nuevo campo', required: false, _optionsRaw: '' });
    },
    removeField(idx) { this.form.fields_schema.splice(idx, 1); },
    moveField(idx, dir) {
      const arr = this.form.fields_schema;
      const tmp = arr[idx]; arr[idx] = arr[idx + dir]; arr[idx + dir] = tmp;
    },
    async save() {
      this.saving = true;
      try {
        const payload = { ...this.form };
        // Clean internal _optionsRaw from payload
        payload.fields_schema = payload.fields_schema.map(({ _optionsRaw, ...f }) => f);
        const id = this.formId || (window.location.pathname.match(/lead-forms\/(\d+)/) || [])[1];
        if (id && id !== 'create') {
          await axios.put('/api/marketing/lead-forms/' + id, payload);
        } else {
          await axios.post('/api/marketing/lead-forms', payload);
        }
        this.$q?.notify({ type: 'positive', message: 'Formulario guardado' });
        window.location.href = '/marketing/lead-forms';
      } catch (e) {
        const msg = e.response?.data?.message || 'Error al guardar';
        this.$q?.notify({ type: 'negative', message: msg });
      } finally { this.saving = false; }
    },
    preview() {
      if (this.form.slug) window.open('/public/marketing/lead-form/' + this.form.slug, '_blank');
    },
  }
}
</script>
