<template>
  <!-- Fase 8 de la Épica #874 (#885): historial de acciones ejecutadas desde los botones de la
       Torre (quién, qué botón, cuándo, resultado). Solo lectura — agrega roadmap_items.log,
       el mismo patrón append-only que ya usan ~15 endpoints (decidir/deshacer/override/urgente/…). -->
  <div class="ha-wrap" :class="{ 'ha-dark': dark }">

    <div class="ha-bar">
      <span class="ha-bar-title"><i class="bi bi-list-check me-1"></i> Historial de acciones</span>
      <span class="ha-bar-meta">
        {{ acciones.length }} acción(es){{ totalEscaneados ? ` · ${totalEscaneados} items escaneados` : '' }}
      </span>
      <button class="ha-refresh" type="button" :disabled="cargando" @click="cargar">
        <i class="bi" :class="cargando ? 'bi-arrow-repeat ha-spin' : 'bi-arrow-repeat'"></i> Actualizar
      </button>
    </div>

    <div class="ha-filtros">
      <input
        v-model.number="filtroItemId"
        type="number"
        min="1"
        class="ha-input ha-input-sm"
        placeholder="# item"
        @keyup.enter="cargar"
      />
      <input
        v-model="filtroPor"
        type="text"
        class="ha-input"
        placeholder="Filtrar por quién (ej. irving:, wt-2, merge-runner…)"
        @keyup.enter="cargar"
      />
      <button class="ha-btn" type="button" @click="cargar">Filtrar</button>
      <button v-if="filtroItemId || filtroPor" class="ha-btn ha-btn-ghost" type="button" @click="limpiarFiltros">Limpiar</button>
    </div>

    <p v-if="error" class="ha-error">{{ error }}</p>

    <div v-if="!cargando && !acciones.length && !error" class="ha-empty">
      <i class="bi bi-inbox ha-empty-ico"></i>
      <p class="ha-empty-h">Sin acciones registradas todavía</p>
      <p class="ha-empty-p">
        Cada botón de la Torre (decidir, deshacer, marcar urgente, override, archivar…) deja su
        rastro aquí en cuanto se usa. Si acabas de filtrar, prueba a limpiar los filtros.
      </p>
    </div>

    <table v-if="acciones.length" class="ha-table">
      <thead>
        <tr>
          <th>Cuándo</th>
          <th>Quién</th>
          <th>Botón / acción</th>
          <th>Item</th>
          <th>Resultado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(a, idx) in acciones" :key="idx">
          <td class="ha-td-ts" :title="a.ts">{{ fmtTs(a.ts) }}</td>
          <td class="ha-td-por">{{ a.por || '—' }}</td>
          <td>
            <span class="ha-accion">{{ a.accion }}</span>
            <span v-if="a.detalle" class="ha-detalle" :title="a.detalle">{{ a.detalle }}</span>
          </td>
          <td class="ha-td-item">
            <a :href="`/roadmap/item/${a.item_id}`" target="_blank" rel="noopener" :title="a.item_title">
              #{{ a.item_id }}
            </a>
          </td>
          <td class="ha-td-estado">{{ a.estado || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { ref, onMounted } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

export default {
    name: "TorreHistorialAcciones",
    setup() {
        const acciones = ref([]);
        const totalEscaneados = ref(0);
        const cargando = ref(false);
        const error = ref(null);
        const filtroItemId = ref(null);
        const filtroPor = ref("");

        const fmtTs = (iso) => {
            if (!iso) return "—";
            try {
                return new Date(iso).toLocaleString("es-MX", { dateStyle: "short", timeStyle: "short" });
            } catch (e) {
                return iso;
            }
        };

        async function cargar() {
            cargando.value = true;
            error.value = null;
            try {
                const params = {};
                if (filtroItemId.value) params.item_id = filtroItemId.value;
                if (filtroPor.value) params.por = filtroPor.value;
                const { data } = await axios.get("/api/roadmap/torre/historial-acciones", { params });
                acciones.value = data.acciones || [];
                totalEscaneados.value = data.total_items_escaneados || 0;
            } catch (e) {
                error.value = (e.response && e.response.data && e.response.data.error) || "No se pudo cargar el historial.";
            } finally {
                cargando.value = false;
            }
        }

        const limpiarFiltros = () => {
            filtroItemId.value = null;
            filtroPor.value = "";
            cargar();
        };

        onMounted(cargar);

        return { acciones, totalEscaneados, cargando, error, filtroItemId, filtroPor, fmtTs, cargar, limpiarFiltros, dark: darkMode };
    },
};
</script>

<style scoped>
.ha-wrap{
  --ha-bg:#f8fafc; --ha-surface:#fff; --ha-ink:#0f172a; --ha-muted:#64748b; --ha-line:#e5e7eb;
  --ha-accent:#0d9488;
  color:var(--ha-ink);
}
.ha-wrap.ha-dark{
  --ha-bg:#0b1220; --ha-surface:#0f172a; --ha-ink:#e2e8f0; --ha-muted:#94a3b8; --ha-line:#1e293b;
  --ha-accent:#2dd4bf;
}

.ha-bar{ display:flex; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.ha-bar-title{ font-weight:700; font-size:15px; }
.ha-bar-meta{ font-size:12px; color:var(--ha-muted); flex:1 1 auto; }
.ha-refresh{
  border:1px solid var(--ha-line); background:var(--ha-surface); color:var(--ha-ink);
  border-radius:8px; padding:5px 10px; font-size:12px; cursor:pointer;
}
.ha-refresh:disabled{ opacity:.6; cursor:default; }
.ha-spin{ display:inline-block; animation:ha-spin 1s linear infinite; }
@keyframes ha-spin{ from{ transform:rotate(0); } to{ transform:rotate(360deg); } }

.ha-filtros{ display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.ha-input{
  border:1px solid var(--ha-line); background:var(--ha-surface); color:var(--ha-ink);
  border-radius:8px; padding:6px 10px; font-size:13px; flex:1 1 260px; min-width:160px;
}
.ha-input-sm{ flex:0 0 100px; min-width:90px; }
.ha-btn{
  border:1px solid var(--ha-accent); background:var(--ha-accent); color:#fff;
  border-radius:8px; padding:6px 14px; font-size:13px; font-weight:600; cursor:pointer;
}
.ha-btn-ghost{ background:transparent; color:var(--ha-muted); border-color:var(--ha-line); }

.ha-error{ color:#dc2626; font-size:13px; }

.ha-empty{ text-align:center; padding:40px 20px; border:1px dashed var(--ha-line); border-radius:14px; background:var(--ha-surface); }
.ha-empty-ico{ font-size:30px; color:var(--ha-muted); }
.ha-empty-h{ font-weight:700; margin:8px 0 4px; }
.ha-empty-p{ color:var(--ha-muted); font-size:13px; max-width:520px; margin:0 auto; line-height:1.5; }

.ha-table{ width:100%; border-collapse:collapse; font-size:13px; background:var(--ha-surface); border:1px solid var(--ha-line); border-radius:10px; overflow:hidden; }
.ha-table th{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--ha-line); color:var(--ha-muted); font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
.ha-table td{ padding:8px 10px; border-bottom:1px solid var(--ha-line); vertical-align:top; }
.ha-table tr:last-child td{ border-bottom:none; }
.ha-td-ts{ white-space:nowrap; color:var(--ha-muted); font-variant-numeric:tabular-nums; }
.ha-td-por{ white-space:nowrap; font-weight:600; }
.ha-accion{ font-weight:700; color:var(--ha-accent); display:block; }
.ha-detalle{ display:block; color:var(--ha-muted); font-size:11.5px; max-width:420px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ha-td-item a{ color:var(--ha-accent); font-weight:700; text-decoration:none; white-space:nowrap; }
.ha-td-item a:hover{ text-decoration:underline; }
.ha-td-estado{ color:var(--ha-muted); white-space:nowrap; }
</style>
