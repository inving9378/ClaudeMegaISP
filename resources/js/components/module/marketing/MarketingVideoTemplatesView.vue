<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col text-h5 text-weight-bold">Plantillas de Video</div>
      <div class="col-auto">
        <q-btn
          icon="add_circle"
          color="primary"
          label="Generar video"
          unelevated
          :href="'/marketing/video-generator'"
        />
      </div>
    </div>

    <!-- Filtros -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-gutter-sm">
        <q-input
          v-model="filters.search"
          dense outlined clearable
          placeholder="Buscar plantilla..."
          style="min-width:220px"
          @update:model-value="load"
        />
        <q-select
          v-model="filters.category"
          :options="categoryOptions"
          emit-value map-options dense outlined
          label="Categoría" clearable style="min-width:170px"
          @update:model-value="load"
        />
        <q-select
          v-model="filters.aspect_ratio"
          :options="ratioOptions"
          emit-value map-options dense outlined
          label="Formato" clearable style="min-width:130px"
          @update:model-value="load"
        />
      </q-card-section>
    </q-card>

    <!-- Grid de plantillas -->
    <div v-if="loading" class="text-center q-pa-xl">
      <q-spinner size="48px" color="primary" />
    </div>

    <div v-else-if="templates.length === 0" class="text-center q-pa-xl text-grey-6">
      <q-icon name="video_library" size="64px" class="q-mb-md" />
      <div>No se encontraron plantillas.</div>
    </div>

    <div v-else class="row q-gutter-md">
      <div
        v-for="tpl in templates"
        :key="tpl.id"
        class="col-xs-12 col-sm-6 col-md-4 col-lg-3"
      >
        <q-card flat bordered class="cursor-pointer template-card" @click="selectTemplate(tpl)">
          <!-- Thumbnail / placeholder -->
          <div class="template-thumb bg-grey-3 row items-center justify-center" style="height:160px">
            <q-img
              v-if="tpl.preview_thumbnail"
              :src="'/storage/' + tpl.preview_thumbnail"
              style="height:160px"
              fit="cover"
            />
            <div v-else class="column items-center text-grey-5">
              <q-icon name="videocam" size="48px" />
              <div class="text-caption q-mt-xs">{{ ratioLabel(tpl.aspect_ratios?.[0]) }}</div>
            </div>
          </div>

          <q-card-section class="q-pb-xs">
            <div class="text-subtitle2 text-weight-medium ellipsis">{{ tpl.name }}</div>
            <div class="text-caption text-grey-6 ellipsis-2-lines" style="min-height:32px">{{ tpl.description }}</div>
          </q-card-section>

          <q-card-section class="q-pt-xs q-pb-sm">
            <q-chip dense color="primary" text-color="white" size="sm" class="q-mr-xs">
              {{ categoryLabel(tpl.category) }}
            </q-chip>
            <q-chip dense outline color="secondary" size="sm" class="q-mr-xs">
              {{ tpl.aspect_ratios?.[0] }}
            </q-chip>
            <q-chip v-if="tpl.requires_voiceover" dense outline color="green" size="sm" icon="mic">
              TTS
            </q-chip>
          </q-card-section>

          <q-card-actions align="right" class="q-pt-none">
            <q-btn
              flat dense color="primary" label="Usar plantilla"
              @click.stop="goGenerate(tpl)"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Dialog detalle -->
    <q-dialog v-model="detailDialog" max-width="560px">
      <q-card v-if="selected" style="min-width:460px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ selected.name }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section>
          <div class="text-body2 text-grey-7 q-mb-sm">{{ selected.description }}</div>
          <q-separator class="q-my-sm" />
          <div class="row q-gutter-sm text-caption">
            <div><strong>Categoría:</strong> {{ categoryLabel(selected.category) }}</div>
            <div><strong>Formato:</strong> {{ selected.aspect_ratios?.join(', ') }}</div>
            <div><strong>Duración:</strong> ~{{ selected.duration_seconds }}s</div>
            <div><strong>Render est.:</strong> ~{{ selected.estimated_render_seconds }}s</div>
          </div>
          <q-separator class="q-my-sm" />
          <div class="text-subtitle2 q-mb-xs">Variables requeridas:</div>
          <q-chip
            v-for="v in requiredVars"
            :key="v.key"
            dense color="orange-1" text-color="orange-9" size="sm" class="q-mr-xs q-mb-xs"
          >
            {{ v.key }} — {{ v.label }}
          </q-chip>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="grey" label="Cerrar" v-close-popup />
          <q-btn unelevated color="primary" label="Usar esta plantilla" @click="goGenerate(selected)" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
export default {
  name: 'MarketingVideoTemplatesView',

  data() {
    return {
      loading: false,
      templates: [],
      selected: null,
      detailDialog: false,
      filters: {
        search: '',
        category: null,
        aspect_ratio: null,
      },
      categoryOptions: [
        { label: 'Promo de Plan',          value: 'plan_promo' },
        { label: 'Anuncio de Cobertura',   value: 'coverage_announcement' },
        { label: 'Testimonio',             value: 'testimonial' },
        { label: 'Recordatorio de Pago',   value: 'payment_reminder' },
        { label: 'Bienvenida',             value: 'welcome' },
        { label: 'Oferta Flash',           value: 'flash_promo' },
        { label: 'Aviso de Mantenimiento', value: 'maintenance_notice' },
        { label: 'Genérico',               value: 'generic' },
      ],
      ratioOptions: [
        { label: 'Vertical 9:16',   value: '9:16' },
        { label: 'Cuadrado 1:1',    value: '1:1' },
        { label: 'Horizontal 16:9', value: '16:9' },
      ],
    };
  },

  computed: {
    requiredVars() {
      return (this.selected?.schema?.variables ?? []).filter(v => v.required);
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const params = {};
        if (this.filters.search)       params.search = this.filters.search;
        if (this.filters.category)     params.category = this.filters.category;
        if (this.filters.aspect_ratio) params.aspect_ratio = this.filters.aspect_ratio;

        const res = await axios.get('/api/marketing/video-templates', { params });
        this.templates = res.data.data ?? [];
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al cargar plantillas' });
      } finally {
        this.loading = false;
      }
    },

    selectTemplate(tpl) {
      this.selected = tpl;
      this.detailDialog = true;
    },

    goGenerate(tpl) {
      window.location.href = `/marketing/video-generator?template_id=${tpl.id}`;
    },

    categoryLabel(cat) {
      return this.categoryOptions.find(o => o.value === cat)?.label ?? cat;
    },

    ratioLabel(ratio) {
      return this.ratioOptions.find(o => o.value === ratio)?.label ?? ratio;
    },
  },
};
</script>

<style scoped>
.template-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  transform: translateY(-2px);
  transition: all .2s;
}
</style>
