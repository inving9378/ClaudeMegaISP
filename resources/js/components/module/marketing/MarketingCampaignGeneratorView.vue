<template>
  <div class="marketing-campaign-generator">
    <!-- Header -->
    <div class="page-header d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-1">Generar campaña multivariante</h4>
        <p class="text-muted mb-0">Una campaña → {{ activeNiches.length }} videos personalizados por nicho de audiencia</p>
      </div>
      <a href="/marketing/video-queue" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-list mr-1"></i> Ver cola de videos
      </a>
    </div>

    <!-- Step wizard -->
    <div class="row">
      <div class="col-lg-8">
        <!-- Step 1: Campaign type + product info -->
        <div class="card mb-3" :class="{ 'border-primary': currentStep === 1 }">
          <div class="card-header d-flex align-items-center" style="cursor:pointer" @click="currentStep = 1">
            <span class="badge badge-primary mr-2">1</span>
            <strong>Configuración del producto</strong>
            <i class="fas fa-check-circle text-success ml-auto" v-if="step1Complete"></i>
          </div>
          <div class="card-body" v-if="currentStep === 1">
            <div class="form-group">
              <label>Tipo de campaña</label>
              <select class="form-control" v-model="form.campaign_type">
                <option value="multivariant_plan_promo">Promoción de plan de internet</option>
                <option value="multivariant_coverage" disabled>Anuncio de cobertura (próximamente)</option>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Nombre del plan</label>
                  <input class="form-control" v-model="form.plan_name" placeholder="Plan Mega 100" />
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Velocidad</label>
                  <input class="form-control" v-model="form.plan_speed" placeholder="100 MEGAS" />
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Precio (pesos/mes)</label>
                  <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                    <input class="form-control" v-model="form.plan_price" placeholder="599" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Teléfono CTA</label>
                  <input class="form-control" v-model="form.phone_cta" placeholder="55 6817 5643" />
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Característica destacada</label>
              <input class="form-control" v-model="form.highlight_feature" placeholder="Sin contratos forzados" />
            </div>
            <button class="btn btn-primary" :disabled="!step1Complete" @click="currentStep = 2">
              Siguiente <i class="fas fa-arrow-right ml-1"></i>
            </button>
          </div>
        </div>

        <!-- Step 2: Niche selection -->
        <div class="card mb-3" :class="{ 'border-primary': currentStep === 2 }">
          <div class="card-header d-flex align-items-center" style="cursor:pointer" @click="currentStep >= 2 && (currentStep = 2)">
            <span class="badge badge-primary mr-2">2</span>
            <strong>Nichos de audiencia</strong>
            <span class="badge badge-secondary ml-2">{{ selectedNiches.length }} / {{ activeNiches.length }}</span>
          </div>
          <div class="card-body" v-if="currentStep === 2">
            <p class="text-muted small mb-3">Selecciona los nichos para los que quieres generar videos. Por defecto, todos están activos.</p>
            <div class="row">
              <div v-for="niche in activeNiches" :key="niche.id" class="col-md-4 mb-3">
                <div class="card h-100"
                     :class="isNicheSelected(niche.slug) ? 'border-primary' : 'border-light'"
                     style="cursor:pointer"
                     @click="toggleNiche(niche.slug)">
                  <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2">
                      <span class="mr-2" style="font-size:1.4rem">{{ nicheIcon(niche.slug) }}</span>
                      <div>
                        <strong class="d-block">{{ niche.name }}</strong>
                        <small class="text-muted">{{ niche.emotional_tone }}</small>
                      </div>
                      <i class="fas fa-check-circle text-primary ml-auto" v-if="isNicheSelected(niche.slug)"></i>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      <span v-for="kw in (niche.vocabulary || []).slice(0,3)" :key="kw"
                            class="badge badge-light mr-1 mb-1" style="font-size:0.7rem">{{ kw }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-outline-secondary btn-sm" @click="currentStep = 1">
                <i class="fas fa-arrow-left mr-1"></i> Anterior
              </button>
              <button class="btn btn-primary" :disabled="selectedNiches.length === 0" @click="currentStep = 3">
                Siguiente <i class="fas fa-arrow-right ml-1"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Step 3: Aspect ratios -->
        <div class="card mb-3" :class="{ 'border-primary': currentStep === 3 }">
          <div class="card-header d-flex align-items-center" style="cursor:pointer" @click="currentStep >= 3 && (currentStep = 3)">
            <span class="badge badge-primary mr-2">3</span>
            <strong>Formato de video</strong>
          </div>
          <div class="card-body" v-if="currentStep === 3">
            <p class="text-muted small mb-3">Selecciona los formatos que necesitas.</p>
            <div class="d-flex flex-wrap gap-3">
              <div v-for="ar in aspectRatioOptions" :key="ar.value"
                   class="card p-3 text-center"
                   :class="selectedAspectRatios.includes(ar.value) ? 'border-primary bg-light' : 'border-light'"
                   style="cursor:pointer;min-width:120px"
                   @click="toggleAspectRatio(ar.value)">
                <div style="font-size:1.8rem">{{ ar.icon }}</div>
                <strong class="d-block">{{ ar.value }}</strong>
                <small class="text-muted">{{ ar.label }}</small>
                <i class="fas fa-check-circle text-primary mt-1" v-if="selectedAspectRatios.includes(ar.value)"></i>
              </div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-outline-secondary btn-sm" @click="currentStep = 2">
                <i class="fas fa-arrow-left mr-1"></i> Anterior
              </button>
              <button class="btn btn-primary" :disabled="selectedAspectRatios.length === 0" @click="currentStep = 4">
                Siguiente <i class="fas fa-arrow-right ml-1"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Step 4: Summary + Generate -->
        <div class="card mb-3" :class="{ 'border-primary': currentStep === 4 }">
          <div class="card-header d-flex align-items-center" style="cursor:pointer" @click="currentStep >= 4 && (currentStep = 4)">
            <span class="badge badge-success mr-2">4</span>
            <strong>Resumen y generar</strong>
          </div>
          <div class="card-body" v-if="currentStep === 4">
            <div class="alert alert-info">
              <strong>Vas a generar {{ totalVideos }} videos</strong><br>
              <small>{{ selectedNiches.length }} nichos × {{ selectedAspectRatios.length }} formatos</small><br>
              <small>Costo estimado: ~${{ estimatedCost }} USD</small><br>
              <small>Tiempo estimado: ~{{ estimatedMinutes }} minutos</small>
            </div>
            <div class="table-responsive mb-3">
              <table class="table table-sm">
                <thead><tr><th>Nicho</th><th>Formato</th><th>Voz</th></tr></thead>
                <tbody>
                  <tr v-for="niche in selectedNicheObjects" :key="niche.slug">
                    <td><span class="mr-1">{{ nicheIcon(niche.slug) }}</span>{{ niche.name }}</td>
                    <td>{{ selectedAspectRatios.join(', ') }}</td>
                    <td><span class="badge badge-light">{{ niche.preferred_voice_id }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="form-group">
              <label>Nombre de la campaña</label>
              <input class="form-control" v-model="form.name" :placeholder="`${form.plan_name} — ${formatDate(new Date())}`" />
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary" @click="currentStep = 3">
                <i class="fas fa-arrow-left mr-1"></i> Anterior
              </button>
              <button class="btn btn-success btn-lg" :disabled="generating" @click="generate">
                <span v-if="generating"><i class="fas fa-spinner fa-spin mr-1"></i> Generando...</span>
                <span v-else><i class="fas fa-magic mr-1"></i> Generar {{ totalVideos }} videos</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right sidebar: active campaigns -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <strong>Campañas recientes</strong>
            <button class="btn btn-sm btn-link float-right p-0" @click="loadCampaigns">
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': loadingCampaigns }"></i>
            </button>
          </div>
          <div class="card-body p-0">
            <div v-if="campaigns.length === 0" class="text-center text-muted py-4">
              <i class="fas fa-film fa-2x mb-2 d-block"></i>
              Sin campañas todavía
            </div>
            <div v-for="c in campaigns" :key="c.id" class="border-bottom p-3">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <strong class="d-block">{{ c.name }}</strong>
                  <small class="text-muted">{{ formatDate(c.created_at) }}</small>
                </div>
                <span class="badge" :class="campaignStatusClass(c.status)">{{ c.status }}</span>
              </div>
              <div v-if="c.status === 'generating'" class="mt-2">
                <div class="progress" style="height:6px">
                  <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width:60%"></div>
                </div>
              </div>
              <div class="mt-1">
                <small>{{ c.variants_succeeded }}/{{ (c.variant_content_ids || []).length }} videos</small>
                <a :href="`/marketing/video-queue?campaign=${c.id}`" class="btn btn-link btn-sm p-0 ml-2">Ver videos</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success modal -->
    <div class="modal fade" id="campaignCreatedModal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Campaña creada</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body text-center py-4">
            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
            <h5>¡Generando {{ totalVideos }} videos!</h5>
            <p class="text-muted">Los videos se están renderizando en segundo plano. Puedes cerrar esta ventana.</p>
          </div>
          <div class="modal-footer">
            <a href="/marketing/video-queue" class="btn btn-primary">Ver cola de videos</a>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Seguir generando</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MarketingCampaignGeneratorView',
  data() {
    return {
      currentStep: 1,
      generating: false,
      loadingCampaigns: false,
      niches: [],
      campaigns: [],
      selectedNiches: [],
      selectedAspectRatios: ['9:16'],
      form: {
        name: '',
        campaign_type: 'multivariant_plan_promo',
        plan_name: '',
        plan_speed: '',
        plan_price: '',
        phone_cta: '',
        highlight_feature: 'Sin contratos forzados',
      },
      aspectRatioOptions: [
        { value: '9:16', label: 'Reels/Stories', icon: '📱' },
        { value: '1:1',  label: 'Feed cuadrado', icon: '🟦' },
        { value: '16:9', label: 'Horizontal/YT', icon: '🖥️' },
      ],
    };
  },
  computed: {
    activeNiches() {
      return this.niches.filter(n => n.active);
    },
    step1Complete() {
      return this.form.plan_name && this.form.plan_speed && this.form.plan_price && this.form.phone_cta;
    },
    totalVideos() {
      return this.selectedNiches.length * this.selectedAspectRatios.length;
    },
    estimatedCost() {
      return (this.totalVideos * 0.05).toFixed(2);
    },
    estimatedMinutes() {
      return Math.ceil(this.totalVideos * 2);
    },
    selectedNicheObjects() {
      return this.activeNiches.filter(n => this.selectedNiches.includes(n.slug));
    },
  },
  mounted() {
    this.loadNiches();
    this.loadCampaigns();
    // Auto-select all niches once loaded
    this.$watch('activeNiches', (niches) => {
      if (this.selectedNiches.length === 0 && niches.length > 0) {
        this.selectedNiches = niches.map(n => n.slug);
      }
    });
  },
  methods: {
    async loadNiches() {
      try {
        const res = await axios.get('/api/marketing/niches');
        this.niches = res.data.data || res.data;
        if (this.selectedNiches.length === 0) {
          this.selectedNiches = this.niches.filter(n => n.active).map(n => n.slug);
        }
      } catch (e) {
        console.error('Error loading niches', e);
      }
    },
    async loadCampaigns() {
      this.loadingCampaigns = true;
      try {
        const res = await axios.get('/api/marketing/multivariant-campaigns');
        this.campaigns = (res.data.data || res.data).slice(0, 5);
      } catch (e) {
        console.error('Error loading campaigns', e);
      } finally {
        this.loadingCampaigns = false;
      }
    },
    isNicheSelected(slug) {
      return this.selectedNiches.includes(slug);
    },
    toggleNiche(slug) {
      const idx = this.selectedNiches.indexOf(slug);
      if (idx >= 0) {
        this.selectedNiches.splice(idx, 1);
      } else {
        this.selectedNiches.push(slug);
      }
    },
    toggleAspectRatio(ar) {
      const idx = this.selectedAspectRatios.indexOf(ar);
      if (idx >= 0) {
        this.selectedAspectRatios.splice(idx, 1);
      } else {
        this.selectedAspectRatios.push(ar);
      }
    },
    async generate() {
      if (this.generating) return;
      this.generating = true;

      const name = this.form.name || `${this.form.plan_name} — ${this.formatDate(new Date())}`;

      try {
        await axios.post('/api/marketing/multivariant-campaigns', {
          ...this.form,
          name,
          niche_slugs: this.selectedNiches,
          aspect_ratios: this.selectedAspectRatios,
        });

        // Show success modal
        if (typeof $ !== 'undefined') {
          $('#campaignCreatedModal').modal('show');
        }

        this.loadCampaigns();
        // Reset form
        this.currentStep = 1;
        this.form.plan_name = '';
        this.form.plan_speed = '';
        this.form.plan_price = '';
        this.form.phone_cta = '';
        this.form.name = '';
      } catch (e) {
        const msg = e.response?.data?.error || 'Error al generar la campaña';
        alert(msg);
      } finally {
        this.generating = false;
      }
    },
    nicheIcon(slug) {
      const icons = {
        gamer: '🎮', familia: '👨‍👩‍👧', home_office: '💻',
        streamer: '🎬', pequeno_negocio: '🏪', adulto_mayor: '👴',
      };
      return icons[slug] || '👤';
    },
    campaignStatusClass(status) {
      return {
        draft: 'badge-secondary',
        generating: 'badge-warning',
        ready: 'badge-success',
        partially_failed: 'badge-warning',
        failed: 'badge-danger',
      }[status] || 'badge-secondary';
    },
    formatDate(date) {
      if (!date) return '';
      const d = typeof date === 'string' ? new Date(date) : date;
      return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.gap-1 { gap: 0.25rem; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 1rem; }
</style>
