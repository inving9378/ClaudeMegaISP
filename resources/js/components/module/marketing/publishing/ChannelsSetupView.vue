<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <div class="text-h5 text-weight-bold">Configuración de Canales</div>
        <div class="text-body2 text-grey-6">Conecta tus cuentas para publicar automáticamente</div>
      </div>
      <div class="col-auto">
        <q-btn color="primary" icon="refresh" label="Validar todos" :loading="validatingAll" @click="validateAll" outline />
      </div>
    </div>

    <!-- Alerta de sesión -->
    <q-banner v-if="flashMessage" :class="flashType === 'success' ? 'bg-positive text-white' : 'bg-negative text-white'" class="q-mb-md rounded-borders">
      <template v-slot:avatar><q-icon :name="flashType === 'success' ? 'check_circle' : 'error'" /></template>
      {{ flashMessage }}
    </q-banner>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner size="40px" color="primary" />
    </div>

    <div v-else class="row q-gutter-md">
      <div v-for="channel in channels" :key="channel.id" class="col-12 col-md-6 col-lg-4">
        <q-card class="full-height">
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <q-icon :name="platformIcon(channel.platform)" size="28px" :color="platformColor(channel.platform)" class="q-mr-sm" />
              <div>
                <div class="text-subtitle1 text-weight-bold">{{ channel.name }}</div>
                <div class="text-caption text-grey-6">{{ channel.platform }} / {{ channel.channel_type }}</div>
              </div>
              <q-space />
              <q-badge :color="channel.credentials_ready ? 'positive' : 'warning'" :label="channel.credentials_ready ? 'Listo' : 'Pendiente'" />
            </div>

            <q-separator class="q-my-sm" />

            <div class="text-caption q-mb-sm">
              <q-icon name="info" size="14px" class="q-mr-xs" />
              {{ channel.credentials_status_message || 'Sin validar' }}
            </div>

            <div v-if="channel.credentials_validated_at" class="text-caption text-grey-5">
              Validado: {{ formatDate(channel.credentials_validated_at) }}
            </div>

            <div class="text-caption q-mt-xs">
              <span class="text-grey-6">Ratios: </span>
              <q-chip v-for="r in channel.supported_aspect_ratios" :key="r" dense size="sm" color="grey-3">{{ r }}</q-chip>
            </div>
            <div class="text-caption text-grey-6">Duración máx: {{ channel.max_duration_seconds }}s</div>
          </q-card-section>

          <q-card-actions>
            <q-btn
              v-if="channel.platform === 'facebook' || channel.platform === 'instagram'"
              color="primary" icon="link" label="Conectar Meta"
              @click="connectMeta" flat dense
            />
            <q-btn
              v-else-if="channel.platform === 'whatsapp'"
              color="green" icon="chat" label="Ver Evolution"
              :href="evolutionUrl" target="_blank" flat dense
            />
            <q-btn
              v-else-if="channel.platform === 'email'"
              color="orange" icon="email" label="Config SMTP"
              @click="showSmtpInfo = true" flat dense
            />
            <q-space />
            <q-btn
              color="grey" icon="check_circle" label="Validar"
              :loading="validating[channel.id]"
              @click="validateChannel(channel)" flat dense
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Dialog info SMTP -->
    <q-dialog v-model="showSmtpInfo">
      <q-card style="min-width:400px">
        <q-card-section class="bg-orange text-white">
          <div class="text-h6"><q-icon name="email" class="q-mr-sm" />Configuración Email SMTP</div>
        </q-card-section>
        <q-card-section>
          <p>El Email Blast usa el SMTP configurado en tu servidor. Agrega estas líneas a tu <code>.env</code>:</p>
          <pre class="bg-grey-2 q-pa-sm rounded-borders text-caption">MAIL_MAILER=smtp
MAIL_HOST=smtp.tuservidor.com
MAIL_PORT=587
MAIL_USERNAME=marketing@meganet.com
MAIL_PASSWORD=tupassword
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=marketing@meganet.com
MAIL_FROM_NAME="Meganet"</pre>
          <p class="text-caption text-grey-6 q-mt-sm">Después reinicia el servidor y valida el canal.</p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MarketingChannelsSetupView',
  data() {
    return {
      channels: [],
      loading: true,
      validatingAll: false,
      validating: {},
      showSmtpInfo: false,
      flashMessage: '',
      flashType: 'success',
    };
  },
  computed: {
    evolutionUrl() {
      return window.location.origin.replace('80', '8080');
    },
  },
  mounted() {
    this.loadChannels();
    this.checkFlashMessages();
  },
  methods: {
    async loadChannels() {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/marketing/publishing/channels');
        this.channels = data.channels;
      } finally {
        this.loading = false;
      }
    },
    async validateChannel(channel) {
      this.$set(this.validating, channel.id, true);
      try {
        const { data } = await axios.post(`/api/marketing/publishing/channels/${channel.id}/validate`);
        const ch = this.channels.find(c => c.id === channel.id);
        if (ch) {
          ch.credentials_ready          = data.valid;
          ch.credentials_status_message = data.message;
          ch.credentials_validated_at   = new Date().toISOString();
        }
        this.$q.notify({ type: data.valid ? 'positive' : 'warning', message: data.message });
      } catch {
        this.$q.notify({ type: 'negative', message: 'Error validando canal' });
      } finally {
        this.$set(this.validating, channel.id, false);
      }
    },
    async validateAll() {
      this.validatingAll = true;
      for (const ch of this.channels) {
        await this.validateChannel(ch);
      }
      this.validatingAll = false;
    },
    connectMeta() {
      window.location.href = '/marketing/meta/oauth/start';
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
      return new Date(d).toLocaleString('es-MX');
    },
    checkFlashMessages() {
      const params = new URLSearchParams(window.location.search);
      if (params.get('success')) {
        this.flashMessage = params.get('success');
        this.flashType    = 'success';
      } else if (params.get('error')) {
        this.flashMessage = params.get('error');
        this.flashType    = 'error';
      }
    },
  },
};
</script>
