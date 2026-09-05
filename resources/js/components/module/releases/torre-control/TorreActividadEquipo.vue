<template>
  <!-- Item #9990375 — "quién trabaja en el sistema, cómo, y cuánto tiempo", por rango de fechas.
       SOLO LECTURA sobre datos que ya existen (roadmap_items, vueltas del circuito, activity_log,
       commits de git). Dos bloques separados a propósito: MEDIDO (dato duro: sesión/terminal/
       tiempo/conteos) vs. INFERIDO (qué persona hay detrás — con login compartido es una pista,
       nunca una certeza). -->
  <div class="ae-wrap" :class="{ 'ae-dark': dark }">

    <div class="ae-bar">
      <span class="ae-bar-title"><i class="bi bi-people me-1"></i> Actividad del equipo</span>
      <span class="ae-bar-meta" v-if="rango.inicio">{{ rango.inicio }} → {{ rango.fin }}</span>
      <button class="ae-refresh" type="button" :disabled="cargando" @click="cargar">
        <i class="bi" :class="cargando ? 'bi-arrow-repeat ae-spin' : 'bi-arrow-repeat'"></i> Actualizar
      </button>
    </div>

    <div class="ae-filtros">
      <label class="ae-label">Desde <input v-model="fechaInicio" type="date" class="ae-input" @change="cargar" /></label>
      <label class="ae-label">Hasta <input v-model="fechaFin" type="date" class="ae-input" @change="cargar" /></label>
      <button class="ae-btn" type="button" @click="cargar">Filtrar</button>
      <button class="ae-btn ae-btn-ghost" type="button" @click="ultimos(1)">Hoy</button>
      <button class="ae-btn ae-btn-ghost" type="button" @click="ultimos(7)">7 días</button>
      <button class="ae-btn ae-btn-ghost" type="button" @click="ultimos(30)">30 días</button>
    </div>

    <p v-if="error" class="ae-error">{{ error }}</p>

    <div v-if="!cargando && cargado && !error" class="ae-resumen">
      <div class="ae-chip"><b>{{ porTerminal.length }}</b> terminal(es) activa(s)</div>
      <div class="ae-chip"><b>{{ totalCompletados }}</b> item(s) completados</div>
      <div class="ae-chip"><b>{{ ejecuciones.total }}</b> vuelta(s) del circuito</div>
      <div class="ae-chip"><b>{{ commits.total }}</b> commit(s)</div>
    </div>

    <!-- ═══ MEDIDO — dato duro ═══ -->
    <section class="ae-section ae-section-medido">
      <h3 class="ae-h3"><i class="bi bi-check-circle-fill ae-ico-medido"></i> Medido <span class="ae-tag ae-tag-medido">dato duro</span></h3>
      <p class="ae-aviso">{{ medidoAviso || 'Sesión/terminal, tiempos y conteos leídos directo de la base de datos y de git.' }}</p>

      <h4 class="ae-h4">Por terminal / sesión</h4>
      <div v-if="!porTerminal.length" class="ae-empty-mini">Sin reclamos/cierres de items en este rango.</div>
      <table v-else class="ae-table">
        <thead>
          <tr>
            <th>Terminal</th>
            <th>Items reclamados</th>
            <th>Items completados</th>
            <th>Tiempo en tarea</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in porTerminal" :key="t.worker_sid">
            <td class="ae-td-sid">{{ t.worker_sid }}</td>
            <td>{{ t.items_reclamados }}</td>
            <td>{{ t.items_completados }}</td>
            <td>{{ fmtDuracion(t.segundos_en_tarea) }}</td>
          </tr>
        </tbody>
      </table>

      <h4 class="ae-h4">Vueltas del circuito</h4>
      <p class="ae-mini-stats">
        {{ ejecuciones.total }} vuelta(s) · {{ ejecuciones.con_cambio }} con cambio ·
        {{ fmtDuracion(ejecuciones.segundos_totales) }} en total
      </p>
      <div v-if="!ejecuciones.vueltas.length" class="ae-empty-mini">Sin vueltas registradas en este rango.</div>
      <table v-else class="ae-table">
        <thead>
          <tr>
            <th>Inicio</th>
            <th>Duración</th>
            <th>Modo</th>
            <th>¿Cambió algo?</th>
            <th>Resumen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in ejecuciones.vueltas.slice(0, 50)" :key="v.id">
            <td class="ae-td-ts">{{ fmtTs(v.started_at) }}</td>
            <td>{{ v.duracion_seg != null ? fmtDuracion(v.duracion_seg) : '—' }}</td>
            <td>{{ v.modo || '—' }}</td>
            <td>{{ v.ejecuto ? 'Sí' : 'No' }}</td>
            <td class="ae-td-resumen" :title="v.resumen">{{ v.resumen || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="ejecuciones.vueltas.length > 50" class="ae-mini-stats">Mostrando 50 de {{ ejecuciones.vueltas.length }}.</p>

      <h4 class="ae-h4">Commits de git</h4>
      <div v-if="!commits.por_autor.length" class="ae-empty-mini">Sin commits en este rango.</div>
      <template v-else>
        <table class="ae-table ae-table-compact">
          <thead><tr><th>Autor</th><th>Commits</th></tr></thead>
          <tbody>
            <tr v-for="a in commits.por_autor" :key="a.autor"><td>{{ a.autor }}</td><td>{{ a.commits }}</td></tr>
          </tbody>
        </table>
        <details class="ae-details">
          <summary>Ver commits recientes ({{ commits.recientes.length }})</summary>
          <table class="ae-table">
            <thead><tr><th>Fecha</th><th>Autor</th><th>Hash</th><th>Mensaje</th></tr></thead>
            <tbody>
              <tr v-for="c in commits.recientes" :key="c.hash">
                <td class="ae-td-ts">{{ fmtTs(c.fecha) }}</td>
                <td>{{ c.autor }}</td>
                <td><code>{{ c.hash }}</code></td>
                <td class="ae-td-resumen" :title="c.mensaje">{{ c.mensaje }}</td>
              </tr>
            </tbody>
          </table>
        </details>
      </template>
    </section>

    <!-- ═══ INFERIDO — pista, no certeza ═══ -->
    <section class="ae-section ae-section-inferido">
      <h3 class="ae-h3"><i class="bi bi-question-diamond-fill ae-ico-inferido"></i> Inferido <span class="ae-tag ae-tag-inferido">no es certeza</span></h3>
      <p class="ae-aviso ae-aviso-inferido">
        {{ inferidoAviso || 'La cuenta de login es compartida — esto es una pista de qué persona pudo estar detrás de cada terminal/commit/acción, no un hecho confirmado.' }}
      </p>

      <h4 class="ae-h4">Por "quién" en el registro de items</h4>
      <div v-if="!porQuienLog.length" class="ae-empty-mini">Sin eventos con autor en el registro de items en este rango.</div>
      <table v-else class="ae-table">
        <thead><tr><th>Quién (según el registro)</th><th>Eventos</th><th>Terminales vistas</th></tr></thead>
        <tbody>
          <tr v-for="q in porQuienLog" :key="q.por">
            <td>{{ q.por }}</td>
            <td>{{ q.eventos }}</td>
            <td>{{ (q.terminales || []).join(', ') || '—' }}</td>
          </tr>
        </tbody>
      </table>

      <h4 class="ae-h4">Acciones de UI por usuario</h4>
      <div v-if="!porUsuarioUi.length" class="ae-empty-mini">Sin acciones de UI registradas en este rango.</div>
      <table v-else class="ae-table">
        <thead><tr><th>Usuario</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-for="u in porUsuarioUi" :key="u.causer_id ?? 'sin-usuario'">
            <td>{{ u.nombre || (u.causer_id ? `Usuario #${u.causer_id}` : 'Sin usuario') }}</td>
            <td>{{ u.acciones }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

export default {
    name: "TorreActividadEquipo",
    setup() {
        const cargando = ref(false);
        const cargado = ref(false);
        const error = ref(null);
        const rango = ref({ inicio: null, fin: null });
        const medidoAviso = ref("");
        const inferidoAviso = ref("");
        const porTerminal = ref([]);
        const ejecuciones = ref({ total: 0, con_cambio: 0, segundos_totales: 0, vueltas: [] });
        const commits = ref({ total: 0, por_autor: [], recientes: [] });
        const porQuienLog = ref([]);
        const porUsuarioUi = ref([]);

        const hoy = new Date();
        const hace7 = new Date(hoy.getTime() - 6 * 86400000);
        const toISODate = (d) => d.toISOString().slice(0, 10);
        const fechaInicio = ref(toISODate(hace7));
        const fechaFin = ref(toISODate(hoy));

        const totalCompletados = computed(() => porTerminal.value.reduce((acc, t) => acc + (t.items_completados || 0), 0));

        const fmtTs = (iso) => {
            if (!iso) return "—";
            try {
                return new Date(iso).toLocaleString("es-MX", { dateStyle: "short", timeStyle: "short" });
            } catch (e) {
                return iso;
            }
        };

        const fmtDuracion = (seg) => {
            seg = Number(seg) || 0;
            if (seg <= 0) return "0m";
            const h = Math.floor(seg / 3600);
            const m = Math.floor((seg % 3600) / 60);
            if (h > 0) return `${h}h ${m}m`;
            const s = Math.floor(seg % 60);
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        };

        async function cargar() {
            cargando.value = true;
            error.value = null;
            try {
                const params = {};
                if (fechaInicio.value) params.fecha_inicio = fechaInicio.value;
                if (fechaFin.value) params.fecha_fin = fechaFin.value;
                const { data } = await axios.get("/api/roadmap/torre/actividad-equipo", { params });
                rango.value = data.rango || { inicio: null, fin: null };
                const medido = data.medido || {};
                const inferido = data.inferido || {};
                medidoAviso.value = medido.aviso || "";
                inferidoAviso.value = inferido.aviso || "";
                porTerminal.value = medido.por_terminal || [];
                ejecuciones.value = medido.ejecuciones || { total: 0, con_cambio: 0, segundos_totales: 0, vueltas: [] };
                commits.value = medido.commits || { total: 0, por_autor: [], recientes: [] };
                porQuienLog.value = inferido.por_quien_en_log || [];
                porUsuarioUi.value = inferido.por_usuario_ui || [];
                cargado.value = true;
            } catch (e) {
                error.value = (e.response && e.response.data && (e.response.data.error || e.response.data.message)) || "No se pudo cargar la actividad del equipo.";
            } finally {
                cargando.value = false;
            }
        }

        const ultimos = (dias) => {
            const fin = new Date();
            const inicio = new Date(fin.getTime() - (dias - 1) * 86400000);
            fechaFin.value = toISODate(fin);
            fechaInicio.value = toISODate(inicio);
            cargar();
        };

        onMounted(cargar);

        return {
            cargando, cargado, error, rango, medidoAviso, inferidoAviso,
            porTerminal, ejecuciones, commits, porQuienLog, porUsuarioUi,
            fechaInicio, fechaFin, totalCompletados,
            fmtTs, fmtDuracion, cargar, ultimos, dark: darkMode,
        };
    },
};
</script>

<style scoped>
.ae-wrap{
  --ae-bg:#f8fafc; --ae-surface:#fff; --ae-ink:#0f172a; --ae-muted:#64748b; --ae-line:#e5e7eb;
  --ae-accent:#0d9488; --ae-medido:#0d9488; --ae-inferido:#b45309;
  color:var(--ae-ink);
}
.ae-wrap.ae-dark{
  --ae-bg:#0b1220; --ae-surface:#0f172a; --ae-ink:#e2e8f0; --ae-muted:#94a3b8; --ae-line:#1e293b;
  --ae-accent:#2dd4bf; --ae-medido:#2dd4bf; --ae-inferido:#fbbf24;
}

.ae-bar{ display:flex; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.ae-bar-title{ font-weight:700; font-size:15px; }
.ae-bar-meta{ font-size:12px; color:var(--ae-muted); flex:1 1 auto; }
.ae-refresh{
  border:1px solid var(--ae-line); background:var(--ae-surface); color:var(--ae-ink);
  border-radius:8px; padding:5px 10px; font-size:12px; cursor:pointer;
}
.ae-refresh:disabled{ opacity:.6; cursor:default; }
.ae-spin{ display:inline-block; animation:ae-spin 1s linear infinite; }
@keyframes ae-spin{ from{ transform:rotate(0); } to{ transform:rotate(360deg); } }

.ae-filtros{ display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.ae-label{ font-size:12px; color:var(--ae-muted); display:flex; align-items:center; gap:6px; }
.ae-input{
  border:1px solid var(--ae-line); background:var(--ae-surface); color:var(--ae-ink);
  border-radius:8px; padding:5px 8px; font-size:13px;
}
.ae-btn{
  border:1px solid var(--ae-accent); background:var(--ae-accent); color:#fff;
  border-radius:8px; padding:6px 14px; font-size:13px; font-weight:600; cursor:pointer;
}
.ae-btn-ghost{ background:transparent; color:var(--ae-muted); border-color:var(--ae-line); }

.ae-error{ color:#dc2626; font-size:13px; }

.ae-resumen{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
.ae-chip{
  border:1px solid var(--ae-line); background:var(--ae-surface); border-radius:10px;
  padding:8px 14px; font-size:13px;
}

.ae-section{ border:1px solid var(--ae-line); border-radius:12px; padding:14px 16px; margin-bottom:16px; background:var(--ae-surface); }
.ae-h3{ font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px; margin:0 0 4px; }
.ae-h4{ font-size:12.5px; font-weight:700; color:var(--ae-muted); text-transform:uppercase; letter-spacing:.03em; margin:16px 0 6px; }
.ae-h4:first-of-type{ margin-top:10px; }
.ae-ico-medido{ color:var(--ae-medido); }
.ae-ico-inferido{ color:var(--ae-inferido); }
.ae-tag{ font-size:10px; font-weight:700; padding:2px 8px; border-radius:999px; text-transform:uppercase; letter-spacing:.03em; }
.ae-tag-medido{ background:color-mix(in srgb, var(--ae-medido) 18%, transparent); color:var(--ae-medido); }
.ae-tag-inferido{ background:color-mix(in srgb, var(--ae-inferido) 18%, transparent); color:var(--ae-inferido); }
.ae-aviso{ font-size:12px; color:var(--ae-muted); margin:0 0 8px; }
.ae-aviso-inferido{ font-style:italic; }
.ae-mini-stats{ font-size:12px; color:var(--ae-muted); margin:0 0 8px; }

.ae-empty-mini{ font-size:12.5px; color:var(--ae-muted); padding:10px 0; }

.ae-table{ width:100%; border-collapse:collapse; font-size:13px; margin-bottom:10px; }
.ae-table th{ text-align:left; padding:6px 8px; border-bottom:1px solid var(--ae-line); color:var(--ae-muted); font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
.ae-table td{ padding:6px 8px; border-bottom:1px solid var(--ae-line); vertical-align:top; }
.ae-table tr:last-child td{ border-bottom:none; }
.ae-table-compact td, .ae-table-compact th{ padding:4px 8px; }
.ae-td-sid{ font-weight:700; }
.ae-td-ts{ white-space:nowrap; color:var(--ae-muted); font-variant-numeric:tabular-nums; }
.ae-td-resumen{ max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.ae-details summary{ cursor:pointer; font-size:12.5px; color:var(--ae-accent); font-weight:600; margin-bottom:8px; }
</style>
