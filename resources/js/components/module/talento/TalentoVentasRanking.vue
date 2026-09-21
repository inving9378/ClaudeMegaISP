<template>
  <div class="talento-ventas-ranking tc-wrap" :class="{ 'tc-dark': darkMode }">
    <div class="tc-card mb-3">
      <div class="tc-cardhead d-flex align-items-center justify-content-between">
        <span>Ranking de ventas</span>
        <div class="d-flex gap-2 align-items-center">
          <input v-model="range.from" @change="loadRanking" type="date" class="form-control form-control-sm" style="width:150px" title="Desde">
          <input v-model="range.to" @change="loadRanking" type="date" class="form-control form-control-sm" style="width:150px" title="Hasta">
          <button v-if="range.from || range.to" @click="clearRange" class="tc-btn tc-btn-seg btn-sm">
            <i class="fa fa-times me-1"></i>Limpiar
          </button>
        </div>
      </div>
      <div class="p-3">
        <div v-if="loadingRanking" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        <div v-else-if="!ranking.length" class="alert alert-light text-center mb-0">Sin ventas en el rango.</div>
        <table v-else class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th style="width:60px">#</th><th>Vendedor</th><th class="text-end">Ventas</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in ranking" :key="row.user_id">
              <td class="text-muted small">{{ idx + 1 }}</td>
              <td>{{ row.name }}</td>
              <td class="text-end">
                <span class="tc-status" :class="idx === 0 ? 'is-ok' : 'is-slate'">{{ row.sales }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tc-card">
      <div class="tc-cardhead d-flex align-items-center justify-content-between">
        <span>Prospectos ({{ prospectosMeta.total ?? 0 }} en total)</span>
        <select v-model="prospectFilter" @change="loadProspectos(1)" class="form-select form-select-sm tc-select" style="width:220px">
          <option value="">Todos los vendedores</option>
          <option v-for="o in ranking" :key="o.user_id" :value="o.user_id">{{ o.name }}</option>
        </select>
      </div>
      <div class="p-3">
        <div v-if="loadingProspectos" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        <div v-else-if="!prospectos.length" class="alert alert-light text-center mb-0">Sin prospectos.</div>
        <template v-else>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Nombre</th><th>Vendedor</th><th>Estado</th><th>Teléfono</th><th>Último contacto</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in prospectos" :key="p.crm_id">
                  <td>{{ p.name }} {{ p.father_last_name }} {{ p.mother_last_name }}</td>
                  <td class="small">{{ p.owner_name }}</td>
                  <td><span class="tc-status" :class="statusVariant(p.crm_status)">{{ p.crm_status }}</span></td>
                  <td class="small">{{ p.phone }}</td>
                  <td class="small">{{ p.last_contacted ? p.last_contacted.substring(0, 10) : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="small text-muted">Página {{ prospectosMeta.current_page }} de {{ prospectosMeta.last_page }}</span>
            <div class="d-flex gap-2">
              <button class="tc-btn tc-btn-seg btn-sm" :disabled="prospectosMeta.current_page <= 1" @click="loadProspectos(prospectosMeta.current_page - 1)">
                <i class="fa fa-chevron-left"></i>
              </button>
              <button class="tc-btn tc-btn-seg btn-sm" :disabled="prospectosMeta.current_page >= prospectosMeta.last_page" @click="loadProspectos(prospectosMeta.current_page + 1)">
                <i class="fa fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";

export default {
  name: "TalentoVentasRanking",
  setup() {
    return { darkMode };
  },
  data() {
    return {
      loadingRanking: true,
      loadingProspectos: true,
      ranking: [],
      prospectos: [],
      prospectosMeta: {},
      range: { from: "", to: "" },
      prospectFilter: "",
    };
  },
  mounted() {
    this.loadRanking();
    this.loadProspectos(1);
  },
  methods: {
    clearRange() {
      this.range.from = "";
      this.range.to = "";
      this.loadRanking();
    },
    async loadRanking() {
      this.loadingRanking = true;
      const params = {};
      if (this.range.from && this.range.to) params.range = [this.range.from, this.range.to];
      const r = await axios.get("/talento/api/ventas/ranking", { params }).catch(() => null);
      this.ranking = r?.data?.ranking ?? [];
      this.loadingRanking = false;
    },
    async loadProspectos(page) {
      this.loadingProspectos = true;
      const params = { page };
      if (this.prospectFilter) params.user_id = this.prospectFilter;
      const r = await axios.get("/talento/api/prospectos", { params }).catch(() => null);
      this.prospectos = r?.data?.data ?? [];
      this.prospectosMeta = {
        total: r?.data?.total ?? 0,
        current_page: r?.data?.current_page ?? 1,
        last_page: r?.data?.last_page ?? 1,
      };
      this.loadingProspectos = false;
    },
    statusVariant(status) {
      const map = {
        Nuevo: "is-info",
        Contactado: "is-info",
        Interesado: "is-warn",
        Instalacion: "is-ok",
        Ganado: "is-ok",
        Perdido: "is-bad",
      };
      return map[status] ?? "is-slate";
    },
  },
};
</script>
