<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <div class="text-h5 text-weight-bold">Suscripciones SaaS — Flotas</div>
        <div class="text-caption text-grey">Gestión de planes y periodos de prueba de clientes</div>
      </div>
    </div>

    <!-- KPIs -->
    <div v-if="kpis" class="row q-col-gutter-md q-mb-lg">
      <div class="col-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <q-icon name="check_circle" color="positive" size="32px" />
            <div class="text-h5 text-weight-bold text-positive">{{ kpis.activas }}</div>
            <div class="text-caption text-grey">Activas</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <q-icon name="access_time" color="amber" size="32px" />
            <div class="text-h5 text-weight-bold text-amber-8">{{ kpis.en_trial }}</div>
            <div class="text-caption text-grey">En prueba</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <q-icon name="cancel" color="grey" size="32px" />
            <div class="text-h5 text-weight-bold text-grey-7">{{ kpis.bajas }}</div>
            <div class="text-caption text-grey">Canceladas/Vencidas</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <q-icon name="attach_money" color="primary" size="32px" />
            <div class="text-h5 text-weight-bold text-primary">${{ Number(kpis.mrr).toLocaleString('es-MX') }}</div>
            <div class="text-caption text-grey">MRR (activas)</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filtros -->
    <div class="row q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-4">
        <q-select
          v-model="filters.status" :options="statusOptions" option-value="value" option-label="label"
          emit-value map-options label="Estado" clearable dense outlined
          @update:model-value="load"
        />
      </div>
      <div class="col-12 col-sm-4">
        <q-select
          v-model="filters.plan" :options="planOptions" option-value="key" option-label="name"
          emit-value map-options label="Plan" clearable dense outlined
          @update:model-value="load"
        />
      </div>
    </div>

    <!-- Tabla -->
    <q-card flat bordered>
      <q-table
        :rows="rows"
        :columns="columns"
        row-key="id"
        :loading="loading"
        flat
        :pagination="{ rowsPerPage: 25 }"
        no-data-label="Sin suscripciones registradas"
      >
        <template #body-cell-status="{ row }">
          <q-td>
            <q-chip
              :color="statusColor(row.status)"
              text-color="white"
              dense
              :label="statusLabel(row.status)"
            />
          </q-td>
        </template>
        <template #body-cell-plan="{ row }">
          <q-td>{{ planLabel(row.plan) }}</q-td>
        </template>
        <template #body-cell-monthly_price="{ row }">
          <q-td>${{ Number(row.monthly_price).toLocaleString('es-MX') }}</q-td>
        </template>
        <template #body-cell-actions="{ row }">
          <q-td>
            <q-btn
              flat dense size="sm" icon="open_in_new" color="primary"
              :href="`/cliente/editar/${row.client_id}`"
              target="_blank"
              title="Ver ficha del cliente"
            />
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- Paginación -->
    <div v-if="meta.last_page > 1" class="flex flex-center q-mt-md">
      <q-pagination
        v-model="page"
        :max="meta.last_page"
        boundary-numbers
        @update:model-value="load"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'FleetSubscriptionDashboard',

  props: {
    baseUrl: { type: String, default: '/flotas' },
  },

  data() {
    return {
      loading: false,
      kpis: null,
      rows: [],
      plans: [],
      page: 1,
      meta: { last_page: 1 },
      filters: { status: null, plan: null },
      columns: [
        { name: 'client_id',        label: 'ID Cliente',     field: 'client_id',        align: 'left',  sortable: true },
        { name: 'plan',             label: 'Plan',           field: 'plan',             align: 'left' },
        { name: 'status',           label: 'Estado',         field: 'status',           align: 'center' },
        { name: 'vehicles_count',   label: 'Vehículos',      field: 'vehicles_count',   align: 'center', sortable: true },
        { name: 'monthly_price',    label: 'Monto/mes',      field: 'monthly_price',    align: 'right',  sortable: true },
        { name: 'next_billing_date',label: 'Próx. factura',  field: 'next_billing_date',align: 'center' },
        { name: 'trial_ends_at',    label: 'Fin de prueba',  field: r => r.trial_ends_at?.slice(0,10) ?? '—', align: 'center' },
        { name: 'actions',          label: '',               field: 'id',               align: 'center' },
      ],
      statusOptions: [
        { value: 'trial',     label: 'En prueba' },
        { value: 'active',    label: 'Activa' },
        { value: 'past_due',  label: 'Pago vencido' },
        { value: 'cancelled', label: 'Cancelada' },
        { value: 'expired',   label: 'Vencida' },
      ],
    };
  },

  computed: {
    planOptions() {
      return this.plans;
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const params = { page: this.page };
        if (this.filters.status) params.status = this.filters.status;
        if (this.filters.plan)   params.plan   = this.filters.plan;
        const { data } = await axios.get('/flotas/api/suscripciones/dashboard', { params });
        this.kpis  = data.kpis;
        this.rows  = data.subscriptions?.data ?? [];
        this.meta  = data.subscriptions?.meta ?? { last_page: 1 };
        this.plans = data.plans ?? [];
      } catch (e) {
        this.$q?.notify({ type: 'negative', message: 'Error al cargar suscripciones' });
      } finally {
        this.loading = false;
      }
    },

    statusColor(s) {
      return { trial: 'amber', active: 'positive', past_due: 'negative', cancelled: 'grey', expired: 'grey-7' }[s] ?? 'grey';
    },
    statusLabel(s) {
      return { trial: 'Prueba', active: 'Activa', past_due: 'Pago vencido', cancelled: 'Cancelada', expired: 'Vencida' }[s] ?? s;
    },
    planLabel(key) {
      return this.plans.find(p => p.key === key)?.name ?? key;
    },
  },
};
</script>
