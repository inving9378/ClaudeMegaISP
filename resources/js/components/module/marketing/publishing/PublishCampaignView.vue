<template>
  <div class="q-pa-md">
    <div class="text-h5 text-weight-bold q-mb-lg">Publicar Campaña Multivariante</div>

    <q-stepper v-model="step" color="primary" animated flat>

      <!-- Step 1: Seleccionar campaña -->
      <q-step :name="1" title="Seleccionar campaña" icon="campaign" :done="step > 1">
        <div class="q-mt-md">
          <q-select
            v-model="selectedCampaign"
            :options="campaigns"
            option-value="id"
            option-label="name"
            label="Campaña multivariante"
            outlined
            use-input
            input-debounce="300"
            @filter="filterCampaigns"
            @update:model-value="loadRouting"
            emit-value
            map-options
          />
          <div v-if="selectedCampaign" class="q-mt-sm text-caption text-grey-6">
            {{ campaigns.find(c => c.id === selectedCampaign)?.status }} —
            {{ campaigns.find(c => c.id === selectedCampaign)?.variants_succeeded }} variantes
          </div>
        </div>
        <q-stepper-navigation>
          <q-btn @click="step = 2" color="primary" label="Siguiente" :disabled="!selectedCampaign || routing.length === 0" />
        </q-stepper-navigation>
      </q-step>

      <!-- Step 2: Routing automático -->
      <q-step :name="2" title="Routing por canal" icon="route" :done="step > 2">
        <div v-if="loadingRouting" class="flex flex-center q-pa-lg"><q-spinner color="primary" /></div>
        <div v-else>
          <div v-for="variant in routing" :key="variant.content_id" class="q-mb-md">
            <div class="text-subtitle2 text-weight-bold q-mb-sm">
              <q-icon name="person" class="q-mr-xs" />
              Nicho: {{ variant.niche }} ({{ variant.aspect_ratio }}, {{ variant.duration_sec }}s)
            </div>
            <div class="row q-gutter-sm">
              <div v-for="ch in variant.channels" :key="ch.channel_id" class="col-auto">
                <q-chip
                  :color="ch.can_publish_now ? 'positive' : (ch.credentials_ready ? 'warning' : 'grey-4')"
                  :text-color="ch.can_publish_now ? 'white' : 'grey-8'"
                  :icon="ch.can_publish_now ? 'check_circle' : 'pause_circle'"
                  :label="ch.channel_name"
                  dense
                  :title="ch.reason || 'Listo'"
                />
              </div>
            </div>
          </div>

          <!-- Seleccionar canales para publicar -->
          <q-separator class="q-my-md" />
          <div class="text-subtitle2 q-mb-sm">Selecciona los canales donde publicar:</div>
          <div class="row q-gutter-sm">
            <q-checkbox
              v-for="ch in availableChannels"
              :key="ch.id"
              v-model="selectedChannelIds"
              :val="ch.id"
              :label="ch.name"
              :color="ch.credentials_ready ? 'positive' : 'warning'"
            />
          </div>
        </div>
        <q-stepper-navigation>
          <q-btn @click="step = 1" label="Atrás" flat class="q-mr-sm" />
          <q-btn @click="step = 3" color="primary" label="Siguiente" :disabled="selectedChannelIds.length === 0" />
        </q-stepper-navigation>
      </q-step>

      <!-- Step 3: Caption y Hashtags -->
      <q-step :name="3" title="Caption y Hashtags" icon="edit" :done="step > 3">
        <div class="q-mt-md">
          <q-input
            v-model="caption"
            label="Caption (se aplica a todas las publicaciones)"
            type="textarea"
            outlined
            rows="4"
            counter
            maxlength="2200"
            class="q-mb-md"
          />
          <q-select
            v-model="hashtags"
            label="Hashtags"
            outlined
            use-input
            use-chips
            multiple
            hide-dropdown-icon
            input-debounce="0"
            hint="Escribe un hashtag y presiona Enter"
            new-value-mode="add-unique"
          />
        </div>
        <q-stepper-navigation>
          <q-btn @click="step = 2" label="Atrás" flat class="q-mr-sm" />
          <q-btn @click="step = 4" color="primary" label="Siguiente" />
        </q-stepper-navigation>
      </q-step>

      <!-- Step 4: Scheduling -->
      <q-step :name="4" title="Programar" icon="schedule" :done="step > 4">
        <div class="q-mt-md">
          <q-radio v-model="scheduleMode" val="now" label="Publicar ahora" class="q-mr-md" />
          <q-radio v-model="scheduleMode" val="scheduled" label="Programar para fecha y hora" />

          <div v-if="scheduleMode === 'scheduled'" class="q-mt-md row q-gutter-md">
            <q-input
              v-model="scheduledDate"
              label="Fecha"
              type="date"
              outlined
              style="width:200px"
            />
            <q-input
              v-model="scheduledTime"
              label="Hora"
              type="time"
              outlined
              style="width:150px"
            />
          </div>

          <q-banner class="bg-blue-1 q-mt-md rounded-borders" dense>
            <template v-slot:avatar><q-icon name="tips_and_updates" color="blue" /></template>
            <div class="text-caption">
              <strong>Mejores horarios sugeridos:</strong>
              Sábado/Domingo 9-11 PM · Miércoles 7-9 PM · Evita lunes 8-10 AM
            </div>
          </q-banner>
        </div>
        <q-stepper-navigation>
          <q-btn @click="step = 3" label="Atrás" flat class="q-mr-sm" />
          <q-btn @click="step = 5" color="primary" label="Revisar" />
        </q-stepper-navigation>
      </q-step>

      <!-- Step 5: Confirmar -->
      <q-step :name="5" title="Confirmar" icon="check_circle">
        <q-card flat bordered class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Resumen de publicación</div>
            <div><strong>Campaña:</strong> {{ campaigns.find(c => c.id === selectedCampaign)?.name }}</div>
            <div><strong>Canales seleccionados:</strong> {{ selectedChannelIds.length }}</div>
            <div><strong>Total publicaciones:</strong> ~{{ routing.length * selectedChannelIds.length }}</div>
            <div v-if="scheduleMode === 'now'"><strong>Timing:</strong> Publicar ahora</div>
            <div v-else><strong>Programada para:</strong> {{ scheduledDate }} {{ scheduledTime }}</div>
          </q-card-section>
        </q-card>

        <q-stepper-navigation class="q-mt-md">
          <q-btn @click="step = 4" label="Atrás" flat class="q-mr-sm" />
          <q-btn
            @click="submit"
            color="primary"
            icon="send"
            label="Publicar Campaña"
            :loading="submitting"
            size="lg"
          />
        </q-stepper-navigation>
      </q-step>

    </q-stepper>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MarketingPublishCampaignView',
  data() {
    return {
      step: 1,
      campaigns: [],
      selectedCampaign: null,
      routing: [],
      availableChannels: [],
      selectedChannelIds: [],
      caption: '',
      hashtags: ['meganet', 'internet'],
      scheduleMode: 'now',
      scheduledDate: '',
      scheduledTime: '20:00',
      loadingRouting: false,
      submitting: false,
    };
  },
  mounted() {
    this.loadCampaigns();
    this.loadChannels();
  },
  methods: {
    async loadCampaigns() {
      const { data } = await axios.get('/api/marketing/multivariant-campaigns');
      this.campaigns = (data.campaigns ?? data).filter(c => c.status === 'completed');
    },
    async loadChannels() {
      const { data } = await axios.get('/api/marketing/publishing/channels');
      this.availableChannels = data.channels.filter(c => c.active);
    },
    filterCampaigns(val, update) {
      update(() => {});
    },
    async loadRouting() {
      if (!this.selectedCampaign) return;
      this.loadingRouting = true;
      try {
        const { data } = await axios.get(`/api/marketing/publishing/campaigns/${this.selectedCampaign}/route`);
        this.routing = data.routing;
      } finally {
        this.loadingRouting = false;
      }
    },
    async submit() {
      this.submitting = true;
      try {
        let scheduledFor = null;
        if (this.scheduleMode === 'scheduled' && this.scheduledDate) {
          scheduledFor = `${this.scheduledDate} ${this.scheduledTime}:00`;
        }

        const { data } = await axios.post(
          `/api/marketing/publishing/campaigns/${this.selectedCampaign}/publish`,
          {
            channel_ids: this.selectedChannelIds,
            scheduled_for: scheduledFor,
            caption: this.caption,
            hashtags: this.hashtags,
          }
        );

        this.$q.notify({
          type: 'positive',
          message: `${data.queued} publicaciones creadas correctamente`,
        });

        this.$router.push ? this.$router.push('/marketing/publishing/queue') : (window.location.href = '/marketing/publishing/queue');
      } catch (err) {
        this.$q.notify({ type: 'negative', message: 'Error al publicar: ' + (err.response?.data?.message ?? err.message) });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
