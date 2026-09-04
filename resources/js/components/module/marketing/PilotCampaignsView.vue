<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 text-weight-bold col">Piloto de campaña A/B (email)</div>
      <q-btn color="primary" icon="add" label="Nuevo piloto" @click="openCreate" />
    </div>

    <q-banner class="bg-orange-1 text-orange-10 q-mb-md rounded-borders" dense>
      <template v-slot:avatar><q-icon name="warning" color="orange" /></template>
      Solo para lista de prueba interna (empleados/cuentas dummy) — nunca clientes reales.
      El envío real requiere el interruptor <code>MARKETING_PILOT_CAMPAIGN_SEND_ENABLED</code> encendido
      a mano y siempre corre un dry-run primero.
    </q-banner>

    <q-table
      :rows="campaigns"
      :columns="columns"
      row-key="id"
      flat
      bordered
      :loading="loading"
      @row-click="(evt, row) => openDetail(row.id)"
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="statusColor(props.value)">{{ statusLabel(props.value) }}</q-badge>
        </q-td>
      </template>
    </q-table>

    <!-- Crear -->
    <q-dialog v-model="showCreate" persistent>
      <q-card style="min-width:600px;max-width:90vw;">
        <q-card-section class="text-h6">Nuevo piloto A/B</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.name" label="Nombre del piloto" outlined dense />

          <q-separator />
          <div class="text-subtitle2">Variante A</div>
          <q-input v-model="form.variant_a_subject" label="Asunto A" outlined dense />
          <q-input v-model="form.variant_a_body" label="Cuerpo A" type="textarea" outlined dense rows="3" />
          <div class="row q-gutter-sm">
            <q-input v-model="form.variant_a_cta_label" label="Texto del botón (opcional)" outlined dense class="col" />
            <q-input v-model="form.variant_a_cta_url" label="URL del botón (opcional)" outlined dense class="col" />
          </div>

          <q-separator />
          <div class="text-subtitle2">Variante B</div>
          <q-input v-model="form.variant_b_subject" label="Asunto B" outlined dense />
          <q-input v-model="form.variant_b_body" label="Cuerpo B" type="textarea" outlined dense rows="3" />
          <div class="row q-gutter-sm">
            <q-input v-model="form.variant_b_cta_label" label="Texto del botón (opcional)" outlined dense class="col" />
            <q-input v-model="form.variant_b_cta_url" label="URL del botón (opcional)" outlined dense class="col" />
          </div>

          <q-separator />
          <div class="text-subtitle2">Destinatarios de prueba (uno por línea: email o "email, nombre")</div>
          <q-input v-model="recipientsRaw" type="textarea" outlined dense rows="4"
                    placeholder="empleado1@meganet.mx, Empleado 1&#10;empleado2@meganet.mx" />

          <div class="row q-gutter-sm">
            <q-input v-model.number="form.batch_size" type="number" label="Tamaño de lote" outlined dense class="col" />
            <q-input v-model.number="form.batch_pause_seconds" type="number" label="Pausa entre lotes (seg)" outlined dense class="col" />
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Crear" :loading="saving" @click="submitCreate" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Detalle -->
    <q-dialog v-model="showDetail" @hide="detail = null">
      <q-card style="min-width:600px;max-width:90vw;" v-if="detail">
        <q-card-section class="row items-center">
          <div class="text-h6 col">{{ detail.campaign.name }}</div>
          <q-badge :color="statusColor(detail.campaign.status)">{{ statusLabel(detail.campaign.status) }}</q-badge>
        </q-card-section>

        <q-card-section>
          <div class="row q-col-gutter-md q-mb-md">
            <div class="col-6" v-for="v in ['a', 'b']" :key="v">
              <q-card flat bordered>
                <q-card-section>
                  <div class="text-subtitle2">Variante {{ v.toUpperCase() }}</div>
                  <div>Enviados: {{ detail.stats.by_variant[v].total }}</div>
                  <div>Abiertos: {{ detail.stats.by_variant[v].opened }}</div>
                  <div>Clics: {{ detail.stats.by_variant[v].clicked }}</div>
                  <div>Conversiones: {{ detail.stats.by_variant[v].converted }}</div>
                </q-card-section>
              </q-card>
            </div>
          </div>

          <q-banner v-if="detail.campaign.dry_run_report" class="bg-blue-1 rounded-borders q-mb-md" dense>
            <div class="text-caption">
              Dry-run: {{ detail.campaign.dry_run_report.total }} destinatarios
              ({{ detail.campaign.dry_run_report.variant_a }} A / {{ detail.campaign.dry_run_report.variant_b }} B),
              {{ detail.campaign.dry_run_report.batches }} lotes.
              SMTP: <strong :class="detail.campaign.dry_run_report.smtp.valid ? 'text-positive' : 'text-negative'">
                {{ detail.campaign.dry_run_report.smtp.message }}
              </strong>
            </div>
          </q-banner>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cerrar" v-close-popup />
          <q-btn color="secondary" label="Dry-run" :loading="dryRunning" @click="runDryRun(detail.campaign.id)" />
          <q-btn
            color="primary"
            label="Enviar de verdad"
            :disable="!['dry_run', 'ready_to_send'].includes(detail.campaign.status)"
            :loading="sending"
            @click="confirmSend(detail.campaign.id)"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MarketingPilotCampaignsView',
  data() {
    return {
      loading: false,
      saving: false,
      dryRunning: false,
      sending: false,
      campaigns: [],
      showCreate: false,
      showDetail: false,
      detail: null,
      recipientsRaw: '',
      columns: [
        { name: 'id', label: '#', field: 'id', align: 'left' },
        { name: 'name', label: 'Nombre', field: 'name', align: 'left' },
        { name: 'sends_count', label: 'Destinatarios', field: 'sends_count', align: 'left' },
        { name: 'status', label: 'Estado', field: 'status', align: 'left' },
      ],
      form: this.emptyForm(),
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    emptyForm() {
      return {
        name: '',
        variant_a_subject: '', variant_a_body: '', variant_a_cta_label: '', variant_a_cta_url: '',
        variant_b_subject: '', variant_b_body: '', variant_b_cta_label: '', variant_b_cta_url: '',
        batch_size: 10, batch_pause_seconds: 5,
      };
    },
    statusLabel(s) {
      return {
        draft: 'Borrador', dry_run: 'Dry-run listo', ready_to_send: 'Listo para enviar',
        sending: 'Enviando', sent: 'Enviado', canceled: 'Cancelado',
      }[s] || s;
    },
    statusColor(s) {
      return {
        draft: 'grey-6', dry_run: 'blue', ready_to_send: 'teal',
        sending: 'orange', sent: 'positive', canceled: 'negative',
      }[s] || 'grey';
    },
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/marketing/pilot-campaigns');
        this.campaigns = data.campaigns;
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = this.emptyForm();
      this.recipientsRaw = '';
      this.showCreate = true;
    },
    parseRecipients() {
      return this.recipientsRaw
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
          const [email, name] = line.split(',').map((s) => s && s.trim());
          return { email, name: name || null };
        });
    },
    async submitCreate() {
      const recipients = this.parseRecipients();
      if (!recipients.length) {
        this.$q.notify({ type: 'negative', message: 'Agrega al menos un destinatario de prueba.' });
        return;
      }
      this.saving = true;
      try {
        await axios.post('/api/marketing/pilot-campaigns', { ...this.form, recipients });
        this.$q.notify({ type: 'positive', message: 'Piloto creado.' });
        this.showCreate = false;
        this.load();
      } catch (err) {
        this.$q.notify({ type: 'negative', message: 'Error: ' + (err.response?.data?.message ?? err.message) });
      } finally {
        this.saving = false;
      }
    },
    async openDetail(id) {
      const { data } = await axios.get(`/api/marketing/pilot-campaigns/${id}`);
      this.detail = data;
      this.showDetail = true;
    },
    async runDryRun(id) {
      this.dryRunning = true;
      try {
        await axios.post(`/api/marketing/pilot-campaigns/${id}/dry-run`);
        this.$q.notify({ type: 'positive', message: 'Dry-run generado.' });
        await this.openDetail(id);
        this.load();
      } catch (err) {
        this.$q.notify({ type: 'negative', message: 'Error: ' + (err.response?.data?.message ?? err.message) });
      } finally {
        this.dryRunning = false;
      }
    },
    confirmSend(id) {
      this.$q.dialog({
        title: 'Confirmar envío real',
        message: 'Esto envía correos de verdad a la lista de prueba. ¿Confirmas?',
        cancel: true,
        persistent: true,
      }).onOk(() => this.doSend(id));
    },
    async doSend(id) {
      this.sending = true;
      try {
        const { data } = await axios.post(`/api/marketing/pilot-campaigns/${id}/send`);
        this.$q.notify({ type: 'positive', message: `Enviados: ${data.result.sent}, fallidos: ${data.result.failed}` });
        await this.openDetail(id);
        this.load();
      } catch (err) {
        this.$q.notify({ type: 'negative', message: 'Error: ' + (err.response?.data?.message ?? err.message) });
      } finally {
        this.sending = false;
      }
    },
  },
};
</script>
