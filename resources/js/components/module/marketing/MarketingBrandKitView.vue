<template>
  <div class="q-pa-md" style="max-width:860px;margin:0 auto">
    <div class="row items-center q-mb-lg">
      <div class="col text-h5 text-weight-bold">Brand Kit</div>
      <div class="col-auto text-caption text-grey-6">Configura la identidad visual para tus videos</div>
    </div>

    <div v-if="loading" class="text-center q-pa-xl">
      <q-spinner size="48px" color="primary" />
    </div>

    <template v-else>

      <!-- ── Sección 1: Logo ──────────────────────────────────────── -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">Logo de la empresa</div>
          <div class="row q-gutter-md items-center">

            <!-- Preview -->
            <div
              class="logo-preview row items-center justify-center bg-grey-2 rounded-borders"
              style="width:180px;height:180px;border:2px dashed #ccc;flex-shrink:0"
              @dragover.prevent
              @drop.prevent="handleLogoDrop"
            >
              <img
                v-if="logoUrl"
                :src="logoUrl"
                style="max-width:160px;max-height:160px;object-fit:contain"
                alt="Logo"
              />
              <div v-else class="column items-center text-grey-5 q-pa-sm text-center">
                <q-icon name="image" size="40px" />
                <div class="text-caption q-mt-xs">Sin logo configurado</div>
                <div class="text-caption">Arrastra aquí</div>
              </div>
            </div>

            <!-- Acciones -->
            <div class="column q-gutter-sm">
              <q-file
                v-model="logoFile"
                accept=".png,.jpg,.jpeg,.svg,.webp"
                :max-file-size="5 * 1024 * 1024"
                style="display:none"
                ref="logoInput"
                @update:model-value="onLogoFileSelected"
              />
              <q-btn
                unelevated color="primary" icon="upload" label="Subir logo"
                :loading="uploadingLogo"
                @click="$refs.logoInput.pickFiles()"
              />
              <q-btn
                v-if="logoUrl"
                flat color="negative" icon="delete" label="Eliminar logo"
                :loading="deletingLogo"
                @click="confirmDeleteLogo = true"
              />
              <div class="text-caption text-grey-6">PNG, JPG, SVG, WebP — máx 5 MB</div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- ── Sección 2: Colores ───────────────────────────────────── -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div class="row items-center q-mb-sm">
            <div class="col text-subtitle1 text-weight-medium">Colores de marca</div>
            <div class="col-auto">
              <q-btn-dropdown flat dense label="Paletas sugeridas" icon="palette" color="primary">
                <q-list>
                  <q-item v-for="p in palettes" :key="p.name" clickable v-close-popup @click="applyPalette(p)">
                    <q-item-section avatar>
                      <div class="row q-gutter-xs">
                        <div :style="{width:'14px',height:'14px',background:p.primary,borderRadius:'2px'}" />
                        <div :style="{width:'14px',height:'14px',background:p.secondary,borderRadius:'2px'}" />
                      </div>
                    </q-item-section>
                    <q-item-section>{{ p.name }}</q-item-section>
                  </q-item>
                </q-list>
              </q-btn-dropdown>
            </div>
          </div>

          <div class="row q-gutter-md q-mb-md">
            <div class="col-xs-12 col-sm-4">
              <div class="text-caption text-grey-7 q-mb-xs">Color primario</div>
              <div class="row items-center q-gutter-sm">
                <input type="color" v-model="form.colors.primary" style="width:40px;height:40px;border:none;padding:2px;cursor:pointer" />
                <q-input v-model="form.colors.primary" dense outlined style="width:110px" :rules="[hexRule]" />
              </div>
            </div>
            <div class="col-xs-12 col-sm-4">
              <div class="text-caption text-grey-7 q-mb-xs">Color secundario</div>
              <div class="row items-center q-gutter-sm">
                <input type="color" v-model="form.colors.secondary" style="width:40px;height:40px;border:none;padding:2px;cursor:pointer" />
                <q-input v-model="form.colors.secondary" dense outlined style="width:110px" :rules="[hexRule]" />
              </div>
            </div>
            <div class="col-xs-12 col-sm-4">
              <div class="text-caption text-grey-7 q-mb-xs">Color de texto</div>
              <div class="row items-center q-gutter-sm">
                <input type="color" v-model="form.colors.text" style="width:40px;height:40px;border:none;padding:2px;cursor:pointer" />
                <q-input v-model="form.colors.text" dense outlined style="width:110px" :rules="[hexRule]" />
              </div>
            </div>
          </div>

          <!-- Preview chips -->
          <div class="row q-gutter-sm">
            <q-chip :style="`background:${form.colors.primary};color:${form.colors.text}`">
              Internet de calidad
            </q-chip>
            <q-chip :style="`background:${form.colors.secondary};color:${form.colors.text}`">
              Oferta especial
            </q-chip>
            <q-chip :style="`background:#1A1A2E;color:${form.colors.text}`">
              Soporte 24/7
            </q-chip>
          </div>
        </q-card-section>
      </q-card>

      <!-- ── Sección 3: Empresa ──────────────────────────────────── -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">Información de la empresa</div>
          <div class="row q-gutter-md">
            <div class="col-xs-12 col-sm-6">
              <q-input
                v-model="form.company.name"
                label="Nombre de la empresa"
                outlined dense
                maxlength="80"
                :rules="[v => (v||'').length <= 80 || 'Máx 80 caracteres']"
              />
            </div>
            <div class="col-xs-12 col-sm-6">
              <q-input
                v-model="form.company.website"
                label="Sitio web"
                outlined dense
                placeholder="www.meganet.com.mx"
              />
            </div>
            <div class="col-xs-12 col-sm-6">
              <q-input
                v-model="form.company.phone"
                label="Teléfono principal"
                outlined dense
                placeholder="800-MEGANET"
                maxlength="30"
              />
            </div>
            <div class="col-xs-12 col-sm-6">
              <q-input
                v-model="form.company.whatsapp"
                label="WhatsApp"
                outlined dense
                placeholder="5215512345678"
                maxlength="30"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- ── Sección 4: Integraciones ───────────────────────────── -->
      <q-card flat bordered class="q-mb-xl">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">Integraciones</div>
          <div class="row q-gutter-md">

            <!-- OpenAI -->
            <div class="col-xs-12 col-sm-6">
              <q-card flat bordered>
                <q-card-section>
                  <div class="row items-center q-mb-sm">
                    <div class="col text-weight-medium">OpenAI TTS</div>
                    <q-badge :color="kit.integrations.openai_configured ? 'positive' : 'grey'" class="q-ml-sm">
                      {{ kit.integrations.openai_configured ? 'Configurado ✓' : 'No configurado' }}
                    </q-badge>
                  </div>
                  <div class="text-caption text-grey-7 q-mb-sm">
                    Para voz en off profesional en videos.<br>
                    Obtén tu key en <strong>platform.openai.com</strong>
                  </div>
                  <q-input
                    v-model="integrations.openai_api_key"
                    :type="showOpenAiKey ? 'text' : 'password'"
                    :label="kit.integrations.openai_configured ? 'Nueva key (deja vacío para no cambiar)' : 'OpenAI API Key'"
                    outlined dense
                    :append-icon="showOpenAiKey ? 'visibility_off' : 'visibility'"
                  >
                    <template #append>
                      <q-icon
                        :name="showOpenAiKey ? 'visibility_off' : 'visibility'"
                        class="cursor-pointer"
                        @click="showOpenAiKey = !showOpenAiKey"
                      />
                    </template>
                  </q-input>
                </q-card-section>
              </q-card>
            </div>

            <!-- Pexels -->
            <div class="col-xs-12 col-sm-6">
              <q-card flat bordered>
                <q-card-section>
                  <div class="row items-center q-mb-sm">
                    <div class="col text-weight-medium">Pexels (B-roll)</div>
                    <q-badge :color="kit.integrations.pexels_configured ? 'positive' : 'grey'" class="q-ml-sm">
                      {{ kit.integrations.pexels_configured ? 'Configurado ✓' : 'No configurado' }}
                    </q-badge>
                  </div>
                  <div class="text-caption text-grey-7 q-mb-sm">
                    Para descargar videos stock automáticamente.<br>
                    Key gratuita en <strong>pexels.com/api</strong>
                  </div>
                  <q-input
                    v-model="integrations.pexels_api_key"
                    :type="showPexelsKey ? 'text' : 'password'"
                    :label="kit.integrations.pexels_configured ? 'Nueva key (deja vacío para no cambiar)' : 'Pexels API Key'"
                    outlined dense
                  >
                    <template #append>
                      <q-icon
                        :name="showPexelsKey ? 'visibility_off' : 'visibility'"
                        class="cursor-pointer"
                        @click="showPexelsKey = !showPexelsKey"
                      />
                    </template>
                  </q-input>
                </q-card-section>
              </q-card>
            </div>

          </div>
        </q-card-section>
      </q-card>

    </template>

    <!-- ── Botón guardar (sticky bottom) ──────────────────────────── -->
    <div class="fixed-bottom-right q-pa-md" style="z-index:100">
      <q-btn
        unelevated color="primary" icon="save" label="Guardar cambios"
        size="md"
        :loading="saving"
        :disable="loading || saving"
        @click="save"
      />
    </div>

    <!-- Delete logo confirm -->
    <q-dialog v-model="confirmDeleteLogo">
      <q-card>
        <q-card-section class="text-h6">Eliminar logo</q-card-section>
        <q-card-section>¿Seguro que deseas eliminar el logo actual?</q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="negative" label="Eliminar" :loading="deletingLogo" @click="doDeleteLogo" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
export default {
  name: 'MarketingBrandKitView',

  data() {
    return {
      loading: true,
      saving: false,
      uploadingLogo: false,
      deletingLogo: false,
      confirmDeleteLogo: false,
      showOpenAiKey: false,
      showPexelsKey: false,
      logoUrl: null,
      logoFile: null,
      kit: {
        logo: { path: null, url: null },
        colors: { primary: '#1976D2', secondary: '#FFC107', text: '#FFFFFF' },
        company: { name: '', phone: '', whatsapp: '', website: '' },
        integrations: { openai_configured: false, pexels_configured: false },
      },
      form: {
        colors: { primary: '#1976D2', secondary: '#FFC107', text: '#FFFFFF' },
        company: { name: '', phone: '', whatsapp: '', website: '' },
      },
      integrations: {
        openai_api_key: '',
        pexels_api_key: '',
      },
      palettes: [
        { name: 'Meganet (azul + naranja)',  primary: '#0066CC', secondary: '#FF6600', text: '#FFFFFF' },
        { name: 'Corporativo (gris + naranja)', primary: '#455A64', secondary: '#FF7043', text: '#FFFFFF' },
        { name: 'Energético (rojo + amarillo)', primary: '#C62828', secondary: '#FFD600', text: '#FFFFFF' },
        { name: 'Limpio (verde + blanco)',    primary: '#2E7D32', secondary: '#FFFFFF', text: '#FFFFFF' },
      ],
    };
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const res = await axios.get('/api/marketing/brand-kit');
        this.kit = res.data;
        this.form.colors  = { ...res.data.colors };
        this.form.company = { ...res.data.company };
        this.logoUrl = res.data.logo?.url ?? null;
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al cargar Brand Kit' });
      } finally {
        this.loading = false;
      }
    },

    async save() {
      this.saving = true;
      try {
        // Save brand + company
        await axios.put('/api/marketing/brand-kit', {
          colors:  this.form.colors,
          company: this.form.company,
        });

        // Save integrations if filled
        if (this.integrations.openai_api_key || this.integrations.pexels_api_key) {
          const payload = {};
          if (this.integrations.openai_api_key) payload.openai_api_key = this.integrations.openai_api_key;
          if (this.integrations.pexels_api_key) payload.pexels_api_key = this.integrations.pexels_api_key;
          const iRes = await axios.put('/api/marketing/brand-kit/integrations', payload);
          this.kit.integrations = iRes.data;
          this.integrations.openai_api_key = '';
          this.integrations.pexels_api_key = '';
        }

        this.$q.notify({ type: 'positive', message: '¡Brand Kit guardado exitosamente!' });
        await this.load();
      } catch (e) {
        const msg = e.response?.data?.message ?? 'Error al guardar';
        this.$q.notify({ type: 'negative', message: msg });
      } finally {
        this.saving = false;
      }
    },

    async onLogoFileSelected(file) {
      if (!file) return;
      this.uploadingLogo = true;
      try {
        const fd = new FormData();
        fd.append('logo', file);
        const res = await axios.post('/api/marketing/brand-kit/logo', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.logoUrl = res.data.url + '&r=' + Date.now();
        this.$q.notify({ type: 'positive', message: 'Logo subido correctamente' });
      } catch (e) {
        const msg = e.response?.data?.message ?? 'Error al subir logo';
        this.$q.notify({ type: 'negative', message: msg });
      } finally {
        this.uploadingLogo = false;
        this.logoFile = null;
      }
    },

    handleLogoDrop(e) {
      const file = e.dataTransfer?.files?.[0];
      if (file) this.onLogoFileSelected(file);
    },

    async doDeleteLogo() {
      this.deletingLogo = true;
      try {
        await axios.delete('/api/marketing/brand-kit/logo');
        this.logoUrl = null;
        this.confirmDeleteLogo = false;
        this.$q.notify({ type: 'positive', message: 'Logo eliminado' });
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al eliminar logo' });
      } finally {
        this.deletingLogo = false;
      }
    },

    applyPalette(p) {
      this.form.colors.primary   = p.primary;
      this.form.colors.secondary = p.secondary;
      this.form.colors.text      = p.text;
      this.$q.notify({ message: `Paleta "${p.name}" aplicada — recuerda guardar`, color: 'info' });
    },

    hexRule(v) {
      return !v || /^#[0-9a-fA-F]{6}$/.test(v) || 'Formato inválido (#RRGGBB)';
    },
  },
};
</script>
