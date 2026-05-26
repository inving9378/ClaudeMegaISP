<template>
  <div class="q-pa-md" v-if="lead">
    <!-- Header -->
    <div class="row items-center q-mb-lg q-gutter-md">
      <q-btn flat round icon="arrow_back" @click="() => window.history.back()" />
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ lead.full_name }}</div>
        <div class="row q-gutter-xs q-mt-xs">
          <lead-status-badge :status="lead.status" />
          <lead-source-chip :source="lead.source" />
        </div>
      </div>
      <div class="col-auto row items-center q-gutter-sm">
        <lead-score-badge :score="lead.score" />
        <span class="text-caption text-grey-6">Score</span>
      </div>
      <div class="col-auto q-gutter-xs">
        <q-btn unelevated color="primary" icon="auto_awesome" label="Re-calificar" :loading="scoring" @click="reScore" />
        <q-btn flat icon="edit" @click="editDialog = true" />
      </div>
    </div>

    <div class="row q-gutter-md">
      <!-- Info panel -->
      <div class="col-12 col-md-4">
        <q-card flat bordered>
          <q-card-section class="text-subtitle2 text-grey-6">Información</q-card-section>
          <q-list separator>
            <q-item v-if="lead.email"><q-item-section avatar><q-icon name="email" /></q-item-section><q-item-section>{{ lead.email }}</q-item-section></q-item>
            <q-item v-if="lead.phone"><q-item-section avatar><q-icon name="phone" /></q-item-section><q-item-section>{{ lead.phone }}</q-item-section></q-item>
            <q-item v-if="lead.whatsapp"><q-item-section avatar><q-icon name="chat" /></q-item-section><q-item-section>{{ lead.whatsapp }}</q-item-section></q-item>
            <q-item v-if="lead.address"><q-item-section avatar><q-icon name="location_on" /></q-item-section>
              <q-item-section>{{ [lead.address, lead.neighborhood, lead.city, lead.state].filter(Boolean).join(', ') }}</q-item-section>
            </q-item>
            <q-item v-if="lead.assigned_user"><q-item-section avatar><q-icon name="person" /></q-item-section>
              <q-item-section>{{ lead.assigned_user.name }}</q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-if="lead.score_reason" class="text-caption text-grey-7 bg-grey-1">
            <strong>Razón del score:</strong> {{ lead.score_reason }}
          </q-card-section>
          <q-card-section v-if="lead.notes">
            <div class="text-caption text-grey-6 q-mb-xs">Notas</div>
            <div class="text-body2">{{ lead.notes }}</div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Timeline + tabs -->
      <div class="col">
        <q-tabs v-model="tab" align="left" class="q-mb-md">
          <q-tab name="timeline" label="Timeline" icon="history" />
          <q-tab name="pipeline" label="Pipeline" icon="view_kanban" />
          <q-tab name="conversations" label="Conversaciones" icon="chat_bubble" />
        </q-tabs>

        <q-tab-panels v-model="tab" animated>
          <q-tab-panel name="timeline">
            <lead-activity-timeline :activities="activities" />
            <div v-if="activitiesLoading" class="text-center q-pa-md"><q-spinner color="primary" /></div>
          </q-tab-panel>
          <q-tab-panel name="pipeline">
            <div class="text-grey-6">Pipeline disponible en versión completa.</div>
          </q-tab-panel>
          <q-tab-panel name="conversations">
            <div class="text-grey-6">Conversaciones disponibles en Fase 3.</div>
          </q-tab-panel>
        </q-tab-panels>
      </div>
    </div>
  </div>
  <div v-else class="text-center q-pa-xl"><q-spinner size="60px" color="primary" /></div>
</template>

<script>
import axios from 'axios';
export default {
  name: 'MarketingLeadDetail',
  props: { leadId: { type: [String, Number], default: null } },
  data() {
    return { lead: null, activities: [], tab: 'timeline', scoring: false, activitiesLoading: false, editDialog: false };
  },
  mounted() {
    const id = this.leadId || window.location.pathname.split('/').pop();
    if (id) { this.loadLead(id); this.loadActivities(id); }
  },
  methods: {
    async loadLead(id) {
      const { data } = await axios.get('/api/marketing/leads/' + id);
      this.lead = data.data ?? data;
    },
    async loadActivities(id) {
      this.activitiesLoading = true;
      try {
        const { data } = await axios.get('/api/marketing/leads/' + id + '/activities');
        this.activities = data.data ?? data;
      } finally { this.activitiesLoading = false; }
    },
    async reScore() {
      this.scoring = true;
      try {
        await axios.post('/api/marketing/leads/' + this.lead.id + '/score');
        this.$q?.notify({ type: 'positive', message: 'Scoring encolado. Actualiza en unos segundos.' });
      } finally { this.scoring = false; }
    },
  }
}
</script>
