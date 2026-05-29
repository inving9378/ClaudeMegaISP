<template>
  <div class="q-pa-md" style="max-width:800px;margin:0 auto">
    <div class="row items-center q-mb-md">
      <q-btn flat dense icon="arrow_back" to="/marketing/video-templates" class="q-mr-sm" />
      <div class="text-h5 text-weight-bold">Generar Video</div>
    </div>

    <!-- Step 1: Seleccionar plantilla -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium q-mb-sm">1. Plantilla</div>
        <q-select
          v-model="selectedTemplateId"
          :options="templateOptions"
          emit-value map-options
          label="Selecciona una plantilla"
          outlined dense
          @update:model-value="onTemplateChange"
          :loading="loadingTemplates"
        />
        <div v-if="selectedTemplate" class="q-mt-sm row q-gutter-sm text-caption text-grey-7">
          <span><q-icon name="aspect_ratio" /> {{ selectedTemplate.aspect_ratios?.[0] }}</span>
          <span><q-icon name="schedule" /> ~{{ selectedTemplate.duration_seconds }}s</span>
          <span><q-icon name="timer" /> Render ~{{ selectedTemplate.estimated_render_seconds }}s</span>
          <span v-if="selectedTemplate.requires_voiceover"><q-icon name="mic" /> Incluye narración</span>
        </div>
      </q-card-section>
    </q-card>

    <!-- Step 2: Variables -->
    <q-card flat bordered class="q-mb-md" v-if="selectedTemplate">
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium q-mb-sm">2. Contenido</div>

        <div v-for="varDef in templateVariables" :key="varDef.key" class="q-mb-md">
          <!-- Textarea para voiceover_text -->
          <q-input
            v-if="varDef.key === 'voiceover_text'"
            v-model="variables[varDef.key]"
            :label="varDef.label + (varDef.required ? ' *' : '')"
            type="textarea"
            outlined dense
            :hint="varDef.example ? 'Ej: ' + varDef.example : varDef.default ?? ''"
            autogrow
          />
          <!-- Otros campos -->
          <q-input
            v-else
            v-model="variables[varDef.key]"
            :label="varDef.label + (varDef.required ? ' *' : '')"
            outlined dense
            :hint="varDef.example ? 'Ej: ' + varDef.example : varDef.default ?? ''"
          />
        </div>
      </q-card-section>
    </q-card>

    <!-- Step 3: Opciones -->
    <q-card flat bordered class="q-mb-md" v-if="selectedTemplate">
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium q-mb-sm">3. Opciones</div>
        <div class="row q-gutter-md">
          <q-select
            v-model="options.tts_driver"
            :options="[{label:'OpenAI (nova)', value:'openai'},{label:'Piper (local)', value:'piper'}]"
            emit-value map-options
            label="Motor de voz (TTS)"
            outlined dense style="min-width:200px"
          />
        </div>
      </q-card-section>
    </q-card>

    <!-- Step 4: Overrides opcionales -->
    <q-card flat bordered class="q-mb-md" v-if="selectedTemplate">
      <q-expansion-item
        icon="tune"
        label="Personalizar para este video (opcional)"
        caption="Logo o colores diferentes al Brand Kit global"
        dense
      >
        <q-card-section class="q-pt-none">
          <!-- Override logo -->
          <q-checkbox v-model="useLogoOverride" label="Usar logo diferente para este video" dense class="q-mb-sm" />
          <div v-if="useLogoOverride" class="q-mb-md q-ml-lg">
            <q-file
              v-model="overrides.logoFile"
              label="Logo para este video"
              accept=".png,.jpg,.jpeg,.svg,.webp"
              :max-file-size="5 * 1024 * 1024"
              outlined dense
              clearable
            >
              <template #prepend><q-icon name="image" /></template>
            </q-file>
            <div v-if="overrides.logoFile" class="q-mt-xs">
              <img
                :src="logoPreviewUrl"
                style="max-height:60px;max-width:120px;object-fit:contain"
              />
            </div>
          </div>

          <!-- Override colors -->
          <q-checkbox v-model="useColorOverride" label="Usar colores diferentes para este video" dense class="q-mb-sm" />
          <div v-if="useColorOverride" class="row q-gutter-md q-ml-lg q-mb-sm">
            <div>
              <div class="text-caption text-grey-7 q-mb-xs">Primario</div>
              <div class="row items-center q-gutter-xs">
                <input type="color" v-model="overrides.primary_color" style="width:36px;height:36px;border:none;padding:2px;cursor:pointer" />
                <q-input v-model="overrides.primary_color" dense outlined style="width:100px" />
              </div>
            </div>
            <div>
              <div class="text-caption text-grey-7 q-mb-xs">Secundario</div>
              <div class="row items-center q-gutter-xs">
                <input type="color" v-model="overrides.secondary_color" style="width:36px;height:36px;border:none;padding:2px;cursor:pointer" />
                <q-input v-model="overrides.secondary_color" dense outlined style="width:100px" />
              </div>
            </div>
          </div>
        </q-card-section>
      </q-expansion-item>
    </q-card>

    <!-- Acción -->
    <div class="row q-gutter-sm">
      <q-btn
        unelevated color="primary"
        label="Generar video"
        icon="movie"
        :loading="generating"
        :disable="!selectedTemplateId || generating"
        @click="generate"
      />
      <q-btn flat color="grey" label="Ver cola de renders" to="/marketing/video-queue" />
    </div>

    <!-- Resultado -->
    <q-card v-if="renderResult" flat bordered class="q-mt-md bg-green-1">
      <q-card-section>
        <div class="row items-center q-gutter-sm">
          <q-icon name="check_circle" color="positive" size="32px" />
          <div>
            <div class="text-weight-medium">¡Video encolado correctamente!</div>
            <div class="text-caption text-grey-7">ID: {{ renderResult.generated_content_id }}</div>
          </div>
          <q-space />
          <q-btn
            flat color="primary" label="Ver progreso"
            :href="'/marketing/video-queue?id=' + renderResult.generated_content_id"
          />
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script>
export default {
  name: 'MarketingVideoGeneratorView',

  data() {
    return {
      loadingTemplates: false,
      generating: false,
      templates: [],
      selectedTemplateId: null,
      selectedTemplate: null,
      templateVariables: [],
      variables: {},
      options: {
        tts_driver: 'openai',
      },
      renderResult: null,
      useLogoOverride: false,
      useColorOverride: false,
      overrides: {
        logoFile: null,
        primary_color: '#0066CC',
        secondary_color: '#FF6600',
      },
    };
  },

  computed: {
    templateOptions() {
      return this.templates.map(t => ({
        label: `${t.name}`,
        value: t.id,
      }));
    },
    logoPreviewUrl() {
      if (!this.overrides.logoFile) return null;
      return URL.createObjectURL(this.overrides.logoFile);
    },
  },

  mounted() {
    this.loadTemplates().then(() => {
      const params = new URLSearchParams(window.location.search);
      const id = params.get('template_id');
      if (id) {
        this.selectedTemplateId = parseInt(id);
        this.onTemplateChange(this.selectedTemplateId);
      }
    });
  },

  methods: {
    async loadTemplates() {
      this.loadingTemplates = true;
      try {
        const res = await axios.get('/api/marketing/video-templates');
        this.templates = res.data.data ?? [];
      } finally {
        this.loadingTemplates = false;
      }
    },

    async onTemplateChange(id) {
      const tpl = this.templates.find(t => t.id === id);
      this.selectedTemplate = tpl ?? null;
      this.variables = {};
      this.templateVariables = [];
      this.renderResult = null;

      if (!tpl) return;

      try {
        const res = await axios.get(`/api/marketing/video-templates/${id}/variables`);
        this.templateVariables = res.data.variables ?? [];
        const defaults = res.data.default_variables ?? {};
        this.variables = { ...defaults };
      } catch (e) {
        this.$q.notify({ type: 'warning', message: 'No se pudieron cargar las variables' });
      }
    },

    async generate() {
      if (!this.selectedTemplateId) return;

      // Validate required
      const missing = this.templateVariables
        .filter(v => v.required && !this.variables[v.key])
        .map(v => v.label);

      if (missing.length) {
        this.$q.notify({ type: 'warning', message: 'Faltan campos requeridos: ' + missing.join(', ') });
        return;
      }

      this.generating = true;
      this.renderResult = null;

      try {
        let res;

        // If there's a logo file override, send multipart
        if (this.useLogoOverride && this.overrides.logoFile) {
          const fd = new FormData();
          fd.append('template_id', this.selectedTemplateId);
          fd.append('variables',   JSON.stringify(this.variables));
          fd.append('options',     JSON.stringify(this.options));
          fd.append('overrides[logo_file]', this.overrides.logoFile);
          if (this.useColorOverride) {
            fd.append('overrides[primary_color]',   this.overrides.primary_color);
            fd.append('overrides[secondary_color]', this.overrides.secondary_color);
          }
          res = await axios.post('/api/marketing/generated-content/render', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
          });
        } else {
          const payload = {
            template_id: this.selectedTemplateId,
            variables:   this.variables,
            options:     this.options,
          };
          if (this.useColorOverride) {
            payload.overrides = {
              primary_color:   this.overrides.primary_color,
              secondary_color: this.overrides.secondary_color,
            };
          }
          res = await axios.post('/api/marketing/generated-content/render', payload);
        }

        this.renderResult = res.data;
        this.$q.notify({ type: 'positive', message: '¡Video encolado! Puedes ver el progreso en la cola.' });
      } catch (e) {
        const msg = e.response?.data?.error ?? 'Error al encolar el render';
        this.$q.notify({ type: 'negative', message: msg });
      } finally {
        this.generating = false;
      }
    },
  },
};
</script>
