<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <div class="text-h5 text-weight-bold">Dashboard de Publicaciones</div>
        <div class="text-body2 text-grey-6">Métricas y estado general del publicador multicanal</div>
      </div>
      <div class="col-auto q-gutter-sm">
        <q-btn color="primary" icon="add" label="Nueva Publicación" to="/marketing/publishing/campaign" />
        <q-btn color="grey-7" icon="list" label="Ver Cola" to="/marketing/publishing/queue" outline />
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row q-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-2">
        <q-card class="text-center q-pa-md">
          <div class="text-h4 text-weight-bold text-primary">{{ stats.published }}</div>
          <div class="text-caption text-grey-6">Publicadas</div>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-2">
        <q-card class="text-center q-pa-md">
          <div class="text-h4 text-weight-bold text-orange">{{ stats.queued }}</div>
          <div class="text-caption text-grey-6">En Cola</div>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-2">
        <q-card class="text-center q-pa-md">
          <div class="text-h4 text-weight-bold text-negative">{{ stats.failed }}</div>
          <div class="text-caption text-grey-6">Fallidas</div>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-2">
        <q-card class="text-center q-pa-md">
          <div class="text-h4 text-weight-bold text-warning">{{ stats.waiting_credentials }}</div>
          <div class="text-caption text-grey-6">En Espera</div>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-2">
        <q-card class="text-center q-pa-md">
          <div class="text-h4 text-weight-bold">{{ stats.total }}</div>
          <div class="text-caption text-grey-6">Total</div>
        </q-card>
      </div>
    </div>

    <div class="row q-gutter-md">
      <!-- Estado de Canales -->
      <div class="col-12 col-md-5">
        <q-card>
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Estado de Canales</div>
            <q-list separator>
              <q-item v-for="ch in byChannel" :key="ch.channel_name" dense>
                <q-item-section avatar>
                  <q-icon :name="platformIcon(ch.platform)" :color="ch.credentials_ready ? 'positive' : 'warning'" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ ch.channel_name }}</q-item-label>
                  <q-item-label caption>{{ ch.published_count }} publicadas</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-badge :color="ch.credentials_ready ? 'positive' : 'warning'" :label="ch.credentials_ready ? '✓ Listo' : '⏸ Pendiente'" />
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
          <q-card-actions>
            <q-btn flat color="primary" label="Configurar canales" to="/marketing/publishing/setup" />
          </q-card-actions>
        </q-card>
      </div>

      <!-- Publicaciones Recientes -->
      <div class="col-12 col-md-6">
        <q-card>
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Publicaciones Recientes</div>
            <div v-if="!recent.length" class="text-grey-5 text-center q-pa-md">
              Sin publicaciones todavía
            </div>
            <q-list separator v-else>
              <q-item v-for="pub in recent" :key="pub.id" dense>
                <q-item-section avatar>
                  <q-icon :name="platformIcon(pub.platform)" :color="platformColor(pub.platform)" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ pub.channel_name }}</q-item-label>
                  <q-item-label caption>{{ pub.ab_variant_tag }} · {{ formatDate(pub.published_at) }}</q-item-label>
                </q-item-section>
                <q-item-section side v-if="pub.metrics">
                  <div class="text-caption">
                    <span v-if="pub.metrics.likes">❤️ {{ pub.metrics.likes }}</span>
                    <span v-if="pub.metrics.views" class="q-ml-xs">▶️ {{ pub.metrics.views }}</span>
                  </div>
                </q-item-section>
                <q-item-section side>
                  <q-btn
                    v-if="pub.external_post_url"
                    flat dense icon="open_in_new" size="sm"
                    :href="pub.external_post_url" target="_blank"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
          <q-card-actions>
            <q-btn flat color="primary" label="Ver toda la cola" to="/marketing/publishing/queue" />
          </q-card-actions>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MarketingPublishingDashboardView',
  data() {
    return {
      stats: { total: 0, published: 0, queued: 0, failed: 0, waiting_credentials: 0 },
      byChannel: [],
      recent: [],
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      const [statsRes, recentRes] = await Promise.all([
        axios.get('/api/marketing/publishing/dashboard/stats'),
        axios.get('/api/marketing/publishing/dashboard/recent'),
      ]);
      this.stats     = statsRes.data.stats;
      this.byChannel = statsRes.data.by_channel;
      this.recent    = recentRes.data.publications;
    },
    platformIcon(platform) {
      const map = { facebook: 'fab fa-facebook', instagram: 'fab fa-instagram', whatsapp: 'fab fa-whatsapp', email: 'email' };
      return map[platform] || 'share';
    },
    platformColor(platform) {
      const map = { facebook: 'blue-8', instagram: 'purple-6', whatsapp: 'green-7', email: 'orange-7' };
      return map[platform] || 'grey';
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
  },
};
</script>
