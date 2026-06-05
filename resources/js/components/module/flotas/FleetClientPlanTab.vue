<template>
  <div class="q-pa-md">
    <!-- Loading -->
    <div v-if="loading" class="text-center q-py-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <!-- Error -->
    <q-banner v-else-if="error" class="bg-negative text-white rounded-borders q-mb-md">
      {{ error }}
      <template #action>
        <q-btn flat label="Reintentar" @click="load" />
      </template>
    </q-banner>

    <template v-else>
      <!-- ── Sin suscripción ──────────────────────────────────────── -->
      <template v-if="!subscription">
        <p class="text-subtitle1 text-weight-medium q-mb-md">Selecciona un plan para iniciar el periodo de prueba:</p>
        <div class="row q-col-gutter-md">
          <div v-for="plan in plans" :key="plan.key" class="col-12 col-md-4">
            <q-card bordered flat class="full-height">
              <q-card-section>
                <div class="text-h6">{{ plan.name }}</div>
                <div class="text-caption text-grey q-mb-sm">{{ plan.tagline }}</div>
                <div class="text-h5 text-primary text-weight-bold">
                  ${{ plan.price_per_vehicle }}<span class="text-caption text-grey">/veh/mes</span>
                </div>
                <div class="text-caption text-grey q-mb-md">Prueba gratis {{ plan.trial_days }} días</div>
                <q-list dense>
                  <q-item v-for="f in plan.features" :key="f" class="q-pa-none">
                    <q-item-section avatar style="min-width:24px">
                      <q-icon name="check_circle" color="positive" size="16px" />
                    </q-item-section>
                    <q-item-section class="text-caption">{{ f }}</q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
              <q-card-actions>
                <q-btn
                  color="primary" unelevated class="full-width"
                  :label="`Iniciar prueba ${plan.trial_days}d`"
                  :loading="saving === plan.key"
                  @click="startTrial(plan.key)"
                />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </template>

      <!-- ── Con suscripción ─────────────────────────────────────── -->
      <template v-else>
        <!-- Banner de estado -->
        <q-banner
          :class="statusBannerClass"
          rounded class="q-mb-lg"
        >
          <template #avatar><q-icon :name="statusIcon" size="28px" /></template>
          <div class="text-weight-bold text-body1">{{ statusLabel }}</div>
          <div v-if="subscription.status === 'trial'" class="text-caption">
            Vence: {{ subscription.trial_ends_at?.slice(0,10) }}
            ({{ daysLeft }} días restantes)
          </div>
          <div v-if="subscription.status === 'active'" class="text-caption">
            Próx. factura: {{ subscription.next_billing_date }}
            · Último cobro: {{ subscription.last_billed_at ?? 'N/A' }}
          </div>
          <div v-if="subscription.status === 'cancelled'" class="text-caption">
            Cancelada: {{ subscription.cancelled_at?.slice(0,10) }}
            · Datos retenidos hasta: {{ subscription.data_retention_until }}
          </div>
          <div v-if="subscription.status === 'expired'" class="text-caption">
            Expirada. Datos retenidos hasta: {{ subscription.data_retention_until }}
          </div>
        </q-banner>

        <!-- KPIs de la suscripción -->
        <div class="row q-col-gutter-md q-mb-lg">
          <div class="col-6 col-md-3">
            <q-card flat bordered>
              <q-card-section class="text-center">
                <div class="text-caption text-grey">Plan actual</div>
                <div class="text-weight-bold">{{ planName }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-6 col-md-3">
            <q-card flat bordered>
              <q-card-section class="text-center">
                <div class="text-caption text-grey">Vehículos</div>
                <div class="text-h6 text-weight-bold">{{ subscription.vehicles_count }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-6 col-md-3">
            <q-card flat bordered>
              <q-card-section class="text-center">
                <div class="text-caption text-grey">Precio/veh</div>
                <div class="text-h6 text-weight-bold">${{ subscription.price_per_vehicle }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-6 col-md-3">
            <q-card flat bordered>
              <q-card-section class="text-center">
                <div class="text-caption text-grey">Monto mensual est.</div>
                <div class="text-h6 text-weight-bold text-primary">${{ subscription.monthly_price }}</div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Acciones -->
        <template v-if="['trial','active'].includes(subscription.status)">
          <div class="row q-col-gutter-sm q-mb-lg">
            <div class="col-auto">
              <q-btn-dropdown outline color="primary" label="Cambiar plan" :loading="saving === 'plan'">
                <q-list>
                  <q-item
                    v-for="p in plans" :key="p.key" clickable v-close-popup
                    :active="p.key === subscription.plan"
                    @click="changePlan(p.key)"
                  >
                    <q-item-section>
                      <q-item-label>{{ p.name }}</q-item-label>
                      <q-item-label caption>${{ p.price_per_vehicle }}/veh/mes</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-btn-dropdown>
            </div>
            <div class="col-auto">
              <q-btn
                flat color="negative" label="Cancelar prueba"
                :loading="saving === 'cancel'"
                @click="cancelConfirm"
              />
            </div>
          </div>
        </template>

        <!-- Reactivar si cancelada/expirada -->
        <template v-if="['cancelled','expired'].includes(subscription.status)">
          <q-btn-dropdown unelevated color="positive" label="Reactivar" :loading="saving === 'reactivate'">
            <q-list>
              <q-item
                v-for="p in plans" :key="p.key" clickable v-close-popup
                @click="startTrial(p.key)"
              >
                <q-item-section>
                  <q-item-label>{{ p.name }}</q-item-label>
                  <q-item-label caption>Prueba {{ p.trial_days }} días gratis</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </template>

        <!-- Historial de eventos -->
        <q-expansion-item
          v-if="subscription.events?.length"
          label="Historial de cambios"
          icon="history"
          dense header-class="text-grey-7"
          class="q-mt-md"
        >
          <q-timeline color="grey" dense>
            <q-timeline-entry
              v-for="ev in subscription.events.slice().reverse()" :key="ev.id"
              :subtitle="ev.occurred_at?.slice(0,16)"
              :title="eventLabel(ev.event_type)"
              icon="fiber_manual_record"
            />
          </q-timeline>
        </q-expansion-item>
      </template>
    </template>
  </div>
</template>

<script>
export default {
  name: 'FleetClientPlanTab',

  props: {
    clientId: { type: [Number, String], required: true },
  },

  data() {
    return {
      loading: false,
      saving: null,
      error: null,
      subscription: null,
      plans: [],
    };
  },

  computed: {
    daysLeft() {
      if (!this.subscription?.trial_ends_at) return 0;
      const diff = new Date(this.subscription.trial_ends_at) - new Date();
      return Math.max(0, Math.ceil(diff / 86400000));
    },
    planName() {
      return this.plans.find(p => p.key === this.subscription?.plan)?.name ?? this.subscription?.plan ?? '—';
    },
    statusBannerClass() {
      const map = {
        trial: 'bg-amber-2 text-amber-10',
        active: 'bg-positive text-white',
        cancelled: 'bg-grey-3 text-grey-8',
        expired: 'bg-grey-3 text-grey-8',
        past_due: 'bg-negative text-white',
      };
      return map[this.subscription?.status] ?? 'bg-grey-3';
    },
    statusLabel() {
      const map = {
        trial: `En periodo de prueba — ${this.daysLeft} días restantes`,
        active: 'Suscripción activa',
        cancelled: 'Suscripción cancelada',
        expired: 'Prueba vencida sin pago',
        past_due: 'Pago vencido',
      };
      return map[this.subscription?.status] ?? this.subscription?.status;
    },
    statusIcon() {
      const map = { trial: 'access_time', active: 'check_circle', cancelled: 'cancel', expired: 'warning', past_due: 'error' };
      return map[this.subscription?.status] ?? 'info';
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/flotas/api/suscripciones/client/${this.clientId}`);
        this.subscription = data?.subscription ?? null;
        this.plans = data?.plans ?? [];
      } catch (e) {
        this.error = e.response?.data?.message ?? 'Error al cargar plan de flotas';
      } finally {
        this.loading = false;
      }
    },

    async startTrial(plan) {
      this.saving = plan;
      try {
        const { data } = await axios.post(
          `/flotas/api/suscripciones/client/${this.clientId}/start-trial`,
          { plan }
        );
        this.subscription = data.subscription;
        this.$q.notify({ type: 'positive', message: 'Periodo de prueba iniciado.' });
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al iniciar prueba' });
      } finally {
        this.saving = null;
      }
    },

    async changePlan(plan) {
      if (plan === this.subscription?.plan) return;
      this.saving = 'plan';
      try {
        await axios.post(`/flotas/api/suscripciones/client/${this.clientId}/change-plan`, { plan });
        this.$q.notify({ type: 'positive', message: 'Plan actualizado.' });
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al cambiar plan' });
      } finally {
        this.saving = null;
      }
    },

    cancelConfirm() {
      this.$q.dialog({
        title: 'Cancelar prueba',
        message: '¿Cancelar la prueba de Flotas para este cliente? Los datos se retienen 90 días.',
        cancel: true,
        persistent: true,
        prompt: { model: '', label: 'Razón de cancelación (opcional)', type: 'text' },
      }).onOk(async reason => {
        this.saving = 'cancel';
        try {
          await axios.post(`/flotas/api/suscripciones/client/${this.clientId}/cancel`, { reason });
          this.$q.notify({ type: 'warning', message: 'Suscripción cancelada.' });
          await this.load();
        } catch (e) {
          this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al cancelar' });
        } finally {
          this.saving = null;
        }
      });
    },

    eventLabel(type) {
      const map = {
        created: 'Suscripción creada', trial_started: 'Prueba iniciada',
        trial_reminder: 'Recordatorio enviado', trial_expiring: 'Prueba por vencer',
        activated: 'Activada (pagada)', billed: 'Ciclo facturado',
        payment_failed: 'Pago fallido', vehicles_changed: 'Vehículos actualizados',
        plan_changed: 'Plan cambiado', cancelled: 'Cancelada',
        expired: 'Expirada', reactivated: 'Reactivada',
      };
      return map[type] ?? type;
    },
  },
};
</script>
