<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col text-h5 text-weight-bold">Formularios de captura</div>
      <div class="col-auto">
        <q-btn icon="add" color="primary" label="Nuevo formulario" unelevated @click="goToEditor()" />
      </div>
    </div>

    <div v-if="loading" class="text-center q-pa-xl"><q-spinner size="60px" color="primary" /></div>

    <div v-else-if="forms.length === 0" class="text-center q-pa-xl text-grey-6">
      <q-icon name="dynamic_form" size="80px" class="q-mb-md" />
      <div class="text-h6">No hay formularios aún</div>
      <div class="text-body2 q-mb-md">Crea tu primer formulario para capturar leads desde tu sitio web.</div>
      <q-btn color="primary" label="Crear formulario" unelevated @click="goToEditor()" />
    </div>

    <div v-else class="row q-gutter-md">
      <div v-for="form in forms" :key="form.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered class="cursor-pointer" @click="goToEditor(form.id)">
          <q-card-section>
            <div class="row items-center no-wrap">
              <div class="col">
                <div class="text-subtitle1 text-weight-medium">{{ form.name }}</div>
                <div class="text-caption text-grey-6">{{ form.slug }}</div>
              </div>
              <q-badge :color="form.active ? 'positive' : 'grey-5'" :label="form.active ? 'Activo' : 'Inactivo'" />
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section class="row q-gutter-md text-center">
            <div class="col">
              <div class="text-h6 text-weight-bold">{{ form.submissions_count ?? 0 }}</div>
              <div class="text-caption text-grey-6">Envíos</div>
            </div>
            <div class="col">
              <div class="text-h6 text-weight-bold">{{ (form.fields_schema || []).length }}</div>
              <div class="text-caption text-grey-6">Campos</div>
            </div>
            <div class="col" v-if="form.assign_to_source">
              <lead-source-chip :source="form.assign_to_source" />
            </div>
          </q-card-section>

          <q-card-actions>
            <q-btn flat dense icon="code" label="Embed" color="primary" @click.stop="showEmbed(form)" />
            <q-btn flat dense icon="open_in_new" label="Preview" color="grey" @click.stop="previewForm(form)" />
            <q-space />
            <q-btn flat dense round icon="edit" color="grey" @click.stop="goToEditor(form.id)" />
            <q-btn flat dense round icon="delete" color="negative" @click.stop="confirmDelete(form)" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Embed dialog -->
    <q-dialog v-model="embedDialog" max-width="700px">
      <q-card style="min-width:600px">
        <q-card-section class="row items-center">
          <div class="text-h6">Código de integración</div>
          <q-space />
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-card-section v-if="selectedForm">
          <q-tabs v-model="embedTab" align="left" class="q-mb-md">
            <q-tab name="iframe" label="iFrame" icon="web" />
            <q-tab name="script" label="Script" icon="code" />
            <q-tab name="url" label="URL directa" icon="link" />
          </q-tabs>
          <q-tab-panels v-model="embedTab" animated>
            <q-tab-panel name="iframe">
              <div class="text-caption text-grey-7 q-mb-xs">Pega este código en tu sitio web:</div>
              <q-input :value="embedCodes.iframe" type="textarea" outlined readonly rows="4" class="text-caption" />
              <q-btn flat dense icon="content_copy" label="Copiar" class="q-mt-sm" @click="copy(embedCodes.iframe)" />
            </q-tab-panel>
            <q-tab-panel name="script">
              <div class="text-caption text-grey-7 q-mb-xs">Script con auto-resize:</div>
              <q-input :value="embedCodes.script" type="textarea" outlined readonly rows="4" class="text-caption" />
              <q-btn flat dense icon="content_copy" label="Copiar" class="q-mt-sm" @click="copy(embedCodes.script)" />
            </q-tab-panel>
            <q-tab-panel name="url">
              <div class="text-caption text-grey-7 q-mb-xs">URL directa del formulario:</div>
              <q-input :value="embedCodes.url" outlined readonly />
              <q-btn flat dense icon="content_copy" label="Copiar" class="q-mt-sm" @click="copy(embedCodes.url)" />
            </q-tab-panel>
          </q-tab-panels>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Delete confirm -->
    <q-dialog v-model="deleteDialog">
      <q-card>
        <q-card-section class="text-h6">¿Eliminar formulario?</q-card-section>
        <q-card-section>Esta acción no se puede deshacer. Los leads capturados se conservarán.</q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="negative" label="Eliminar" :loading="deleting" @click="deleteForm" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import axios from 'axios';
export default {
  name: 'MarketingLeadFormsView',
  data() {
    return {
      forms: [], loading: false, deleting: false,
      embedDialog: false, deleteDialog: false,
      selectedForm: null, embedTab: 'iframe', embedCodes: {},
    };
  },
  mounted() { this.loadForms(); },
  methods: {
    async loadForms() {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/marketing/lead-forms');
        this.forms = data.data ?? data;
      } finally { this.loading = false; }
    },
    goToEditor(id) {
      window.location.href = id ? '/marketing/lead-forms/' + id + '/edit' : '/marketing/lead-forms/create';
    },
    previewForm(form) {
      window.open('/public/marketing/lead-form/' + form.slug, '_blank');
    },
    async showEmbed(form) {
      this.selectedForm = form;
      this.embedTab = 'iframe';
      this.embedCodes = {};
      try {
        const { data } = await axios.get('/api/marketing/lead-forms/' + form.id + '/embed-code');
        this.embedCodes = data;
      } catch {
        const base = window.location.origin;
        const url = base + '/public/marketing/lead-form/' + form.slug;
        this.embedCodes = {
          url,
          iframe: `<iframe src="${url}" width="100%" height="500" frameborder="0" scrolling="no"></iframe>`,
          script: `<div id="mktform-${form.slug}"></div>\n<script src="${base}/public/marketing/embed.js" data-form="${form.slug}" data-target="mktform-${form.slug}"><\/script>`,
        };
      }
      this.embedDialog = true;
    },
    confirmDelete(form) { this.selectedForm = form; this.deleteDialog = true; },
    async deleteForm() {
      if (!this.selectedForm) return;
      this.deleting = true;
      try {
        await axios.delete('/api/marketing/lead-forms/' + this.selectedForm.id);
        this.forms = this.forms.filter(f => f.id !== this.selectedForm.id);
        this.deleteDialog = false;
        this.$q?.notify({ type: 'positive', message: 'Formulario eliminado' });
      } catch {
        this.$q?.notify({ type: 'negative', message: 'Error al eliminar' });
      } finally { this.deleting = false; }
    },
    copy(text) {
      navigator.clipboard?.writeText(text).then(() => {
        this.$q?.notify({ type: 'positive', message: 'Copiado al portapapeles' });
      });
    },
  }
}
</script>
