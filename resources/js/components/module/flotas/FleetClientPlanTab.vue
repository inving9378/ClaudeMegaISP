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
        <p class="text-subtitle1 text-weight-medium q-mb-sm">El bloque se calcula solo con las unidades registradas:</p>
        <q-card bordered flat class="q-mb-md">
          <q-card-section>
            <div class="text-caption text-grey">{{ units.length }} unidad(es) registrada(s)</div>
            <div class="text-h6">Bloque {{ bloqueActual.name }}</div>
            <div class="text-h5 text-primary text-weight-bold">
              ${{ bloqueActual.price }}<span class="text-caption text-grey">/mes</span>
            </div>
            <div class="text-caption text-grey q-mt-xs">
              <span v-for="(p, idx) in plansOrdenados" :key="p.key">
                {{ rangoTexto(idx) }} = {{ p.name }} ${{ p.base_price }}<span v-if="idx < plansOrdenados.length - 1"> · </span>
              </span>
              · arriba de {{ topeMaximo }}: ${{ plansOrdenados[0]?.overage_unit_price }} por unidad extra
            </div>
          </q-card-section>
          <q-card-actions>
            <q-btn
              color="primary" unelevated
              :label="`Iniciar prueba ${bloqueActual.trial_days} días`"
              :loading="saving === 'trial'"
              @click="startTrial(false)"
            />
            <q-btn
              color="positive" unelevated
              label="Promoción 3 meses gratis"
              :loading="saving === 'promo'"
              @click="startTrial(true)"
            />
          </q-card-actions>
        </q-card>
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
                <div class="text-caption text-grey">Tope incluido</div>
                <div class="text-h6 text-weight-bold">{{ subscription.included_units }} veh</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-6 col-md-3">
            <q-card flat bordered>
              <q-card-section class="text-center">
                <div class="text-caption text-grey">Monto mensual est.</div>
                <div class="text-h6 text-weight-bold text-primary">${{ subscription.monthly_price }}</div>
                <div v-if="subscription.overage_units > 0" class="text-caption text-grey">
                  +{{ subscription.overage_units }} excedente (${{ subscription.overage_unit_price }}/veh)
                </div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Acciones -->
        <template v-if="['trial','active'].includes(subscription.status)">
          <div class="row q-col-gutter-sm q-mb-lg">
            <div class="col-auto self-center text-caption text-grey">
              El bloque se ajusta solo según las unidades registradas.
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
          <div class="row q-col-gutter-sm">
            <div class="col-auto">
              <q-btn unelevated color="positive" :label="`Reactivar con prueba de ${bloqueActual.trial_days} días`" :loading="saving === 'trial'" @click="startTrial(false)" />
            </div>
            <div class="col-auto">
              <q-btn outline color="positive" label="Reactivar con promoción 3 meses" :loading="saving === 'promo'" @click="startTrial(true)" />
            </div>
          </div>
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

      <!-- ── Unidades del cliente ────────────────────────────────── -->
      <q-separator class="q-my-lg" />
      <div class="row items-center q-mb-sm">
        <div class="text-subtitle1 text-weight-medium col">Unidades del cliente</div>
        <q-btn color="primary" unelevated dense icon="add" label="Agregar unidad" @click="openUnit()" />
      </div>
      <q-banner v-if="avisoBloque" class="bg-amber-2 text-amber-10 rounded-borders q-mb-sm" dense>
        {{ avisoBloque }}
      </q-banner>
      <q-markup-table flat bordered dense v-if="units.length">
        <thead>
          <tr><th class="text-left">Unidad</th><th class="text-left">Placas</th><th class="text-left">Estado</th><th class="text-left">GPS</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="u in units" :key="u.id">
            <td>{{ u.brand }} {{ u.model }} {{ u.year || '' }}</td>
            <td>{{ u.plates || '—' }}</td>
            <td>{{ u.status }}</td>
            <td>
              <template v-if="u.gps">
                {{ u.gps.imei }} · {{ u.gps.esperando_conexion ? 'esperando primera conexión' : u.gps.status }}
                <q-btn flat dense round size="sm" icon="link_off" color="negative" @click="removeGps(u)"><q-tooltip>Quitar GPS</q-tooltip></q-btn>
              </template>
              <q-btn v-else flat dense size="sm" icon="gps_fixed" label="Agregar GPS" @click="openGps(u)" />
            </td>
            <td class="text-right">
              <q-btn flat dense round icon="edit" @click="openUnit(u)" />
              <q-btn flat dense round icon="delete" color="negative" @click="removeUnit(u)" />
            </td>
          </tr>
        </tbody>
      </q-markup-table>
      <div v-else class="text-grey text-caption">Este cliente aún no tiene unidades registradas.</div>

      <q-dialog v-model="unitDialog" persistent>
        <q-card style="min-width:360px">
          <q-card-section class="text-h6">{{ unitForm.id ? 'Editar unidad' : 'Agregar unidad' }}</q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-input v-model="unitForm.brand" label="Marca *" dense outlined />
            <q-input v-model="unitForm.model" label="Modelo *" dense outlined />
            <q-input v-model="unitForm.year" label="Año" type="number" dense outlined />
            <q-input v-model="unitForm.plates" label="Placas" dense outlined />
            <q-input v-model="unitForm.color" label="Color" dense outlined />
            <q-select v-model="unitForm.status" :options="['active','in_workshop','inactive']" label="Estado" dense outlined />
            <div v-if="unitError" class="text-negative text-caption">{{ unitError }}</div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn color="primary" unelevated label="Guardar" :loading="saving === 'unit'" @click="saveUnit" />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <q-dialog v-model="gpsDialog" persistent>
        <q-card style="min-width:360px">
          <q-card-section class="text-h6">Agregar GPS</q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-select v-model="gpsForm.brand" :options="['ruptela']" label="Marca *" dense outlined />
            <q-input v-model="gpsForm.imei" label="IMEI *" dense outlined maxlength="20" />
            <q-input v-model="gpsForm.sim_number" label="Número del chip" dense outlined maxlength="20" />
            <q-input v-model="gpsForm.sim_carrier" label="Compañía del chip" dense outlined maxlength="50" />
            <div v-if="gpsError" class="text-negative text-caption">{{ gpsError }}</div>
            <div v-if="gpsListener" class="text-caption bg-blue-1 q-pa-sm rounded-borders">
              GPS agregado. Configura el equipo para enviar a la IP <b>{{ gpsListener.ip || '(pendiente de asignar)' }}</b>,
              puerto <b>{{ gpsListener.port }}</b>, protocolo {{ gpsListener.protocol }}. Estado: esperando primera conexión.
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat :label="gpsListener ? 'Cerrar' : 'Cancelar'" v-close-popup />
            <q-btn v-if="!gpsListener" color="primary" unelevated label="Guardar" :loading="saving === 'gps'" @click="saveGps" />
          </q-card-actions>
        </q-card>
      </q-dialog>
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
      units: [],
      unitDialog: false,
      unitForm: {},
      unitError: null,
      gpsDialog: false,
      gpsForm: {},
      gpsError: null,
      gpsListener: null,
      gpsUnit: null,
      avisoBloque: null,
    };
  },

  computed: {
    // Mismo criterio que FleetPlans::keyForUnits()/priceForUnits(): el menor bloque que incluye las
    // unidades; arriba del tope, el mayor + excedente. Solo para mostrar: el servidor recalcula.
    plansOrdenados() {
      return [...this.plans].sort((a, b) => a.included_units - b.included_units);
    },
    topeMaximo() {
      return this.plansOrdenados[this.plansOrdenados.length - 1]?.included_units ?? 0;
    },
    bloqueActual() {
      const n = this.units.length;
      const lista = this.plansOrdenados;
      const p = lista.find(x => n <= x.included_units) ?? lista[lista.length - 1];
      if (!p) return { key: null, name: '—', price: 0, trial_days: 0 };
      const extra = Math.max(0, n - p.included_units);
      return { key: p.key, name: p.name, price: (Number(p.base_price) + extra * Number(p.overage_unit_price)).toFixed(2), trial_days: p.trial_days };
    },
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
        trial: `${this.subscription?.is_promo ? 'En promoción (3 meses gratis)' : 'En periodo de prueba'} — ${this.daysLeft} días restantes`,
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
    rangoTexto(idx) {
      const lista = this.plansOrdenados;
      const desde = idx === 0 ? 1 : lista[idx - 1].included_units + 1;
      return `${desde}–${lista[idx].included_units}`;
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/flotas/api/suscripciones/client/${this.clientId}`);
        this.subscription = data?.subscription ?? null;
        this.plans = data?.plans ?? [];
        await this.loadUnits();
      } catch (e) {
        this.error = e.response?.data?.message ?? 'Error al cargar plan de flotas';
      } finally {
        this.loading = false;
      }
    },

    async loadUnits() {
      const { data } = await axios.get(`/flotas/api/suscripciones/client/${this.clientId}/vehiculos`);
      this.units = data?.vehicles ?? [];
    },

    openUnit(u = null) {
      this.unitError = null;
      this.unitForm = u ? { ...u } : { status: 'active' };
      this.unitDialog = true;
    },

    async saveUnit() {
      this.saving = 'unit';
      this.unitError = null;
      const base = `/flotas/api/suscripciones/client/${this.clientId}/vehiculos`;
      const f = this.unitForm;
      const body = { brand: f.brand, model: f.model, year: f.year || null, plates: f.plates || null, color: f.color || null, status: f.status };
      try {
        const { data } = f.id ? await axios.put(`${base}/${f.id}`, body) : await axios.post(base, body);
        this.avisoBloque = data?.aviso_bloque?.mensaje ?? null;
        this.unitDialog = false;
        await this.load();
      } catch (e) {
        this.unitError = e.response?.data?.message ?? 'Error al guardar la unidad';
      } finally {
        this.saving = null;
      }
    },

    openGps(u) {
      this.gpsUnit = u;
      this.gpsError = null;
      this.gpsListener = null;
      this.gpsForm = { brand: 'ruptela' };
      this.gpsDialog = true;
    },

    async saveGps() {
      this.saving = 'gps';
      this.gpsError = null;
      try {
        const { data } = await axios.post(`/flotas/api/suscripciones/client/${this.clientId}/vehiculos/${this.gpsUnit.id}/gps`, this.gpsForm);
        this.gpsListener = data.listener;
        await this.loadUnits();
      } catch (e) {
        this.gpsError = e.response?.data?.errors?.imei?.[0] ?? e.response?.data?.message ?? 'Error al agregar el GPS';
      } finally {
        this.saving = null;
      }
    },

    removeGps(u) {
      this.$q.dialog({ title: 'Quitar GPS', message: `¿Quitar el GPS de ${u.brand} ${u.model}?`, cancel: true, persistent: true }).onOk(async () => {
        try {
          await axios.delete(`/flotas/api/suscripciones/client/${this.clientId}/vehiculos/${u.id}/gps`);
          await this.loadUnits();
        } catch (e) {
          this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al quitar el GPS' });
        }
      });
    },

    removeUnit(u) {
      this.$q.dialog({ title: 'Dar de baja', message: `¿Dar de baja ${u.brand} ${u.model}?`, cancel: true, persistent: true }).onOk(async () => {
        try {
          const { data } = await axios.delete(`/flotas/api/suscripciones/client/${this.clientId}/vehiculos/${u.id}`);
          this.avisoBloque = data?.aviso_bloque?.mensaje ?? null;
          await this.load();
        } catch (e) {
          this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al dar de baja' });
        }
      });
    },

    async startTrial(promo = false) {
      const plan = this.bloqueActual.key;
      if (!plan) return;
      this.saving = promo ? 'promo' : 'trial';
      try {
        const { data } = await axios.post(
          `/flotas/api/suscripciones/client/${this.clientId}/start-trial`,
          { plan, promo }
        );
        this.subscription = data.subscription;
        this.$q.notify({ type: 'positive', message: promo ? 'Promoción de 3 meses iniciada.' : 'Periodo de prueba iniciado.' });
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.message ?? 'Error al iniciar prueba' });
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
        created: 'Suscripción creada', trial_started: 'Prueba iniciada', promo_started: 'Promoción 3 meses iniciada', promo_converted: 'Promoción terminada: pasó a cobro', block_change_notice: 'Cambio de bloque',
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
