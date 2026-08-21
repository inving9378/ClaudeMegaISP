<template>
  <!-- #940 (Torre fase 6, sub-item frontend de #890) — vista de SOLO LECTURA de la cola
       ejecutable real: consume GET /api/roadmap/torre/cola tal cual la devuelve el backend
       (mismo orden, misma frase, misma causa de exclusión) sin reordenar ni reescribir nada
       en el frontend. Sin botones de acción: leer nada más. -->
  <div class="tce-wrap" :class="{ 'tce-dark': dark }">

    <!-- Sin permiso (`torre.cola.ver`, solo super-administrator + DESARROLLADOR): el endpoint
         responde 403 y el bloque completo se oculta — no hay nada más que mostrar aquí. -->
    <div v-if="sinPermiso" class="tce-empty">
      <i class="bi bi-lock-fill tce-empty-ico"></i>
      <p class="tce-empty-h">Sin acceso a la cola ejecutable</p>
      <p class="tce-empty-p">Esta vista requiere el permiso <code>torre.cola.ver</code>.</p>
    </div>

    <template v-else>
      <!-- Cabecera grande: estado del reparto (solo lectura, sin botón — el pausar/reanudar
           es del kill switch #342, fuera de alcance de este item). -->
      <div v-if="cargado" class="tce-banner" :class="repartoActivo ? 'tce-banner-on' : 'tce-banner-off'">
        <i class="bi" :class="repartoActivo ? 'bi-play-circle-fill' : 'bi-pause-circle-fill'"></i>
        <span class="tce-banner-txt">{{ repartoActivo ? 'Reparto ACTIVO' : 'Reparto PAUSADO' }}</span>
        <span class="tce-banner-sub">{{ repartoActivo ? 'las terminales están tomando trabajo de esta cola' : 'ninguna terminal está tomando trabajo nuevo' }}</span>
      </div>

      <div v-if="error" class="tce-empty">
        <i class="bi bi-exclamation-triangle-fill tce-empty-ico"></i>
        <p class="tce-empty-h">No se pudo cargar la cola</p>
        <p class="tce-empty-p">{{ error }}</p>
      </div>

      <template v-else-if="cargado">
        <!-- Frase del orden real, generada por el backend — se pinta TAL CUAL, sin reescribirla. -->
        <p v-if="ordenFrase" class="tce-frase"><i class="bi bi-info-circle me-1"></i>{{ ordenFrase }}</p>

        <!-- Cola en su orden real (no reordenar en el frontend). -->
        <div class="tce-section">
          <div class="tce-section-h">
            <i class="bi bi-list-ol"></i> Cola ejecutable
            <span class="tce-count">{{ cola.length }}</span>
          </div>
          <div v-if="!cola.length" class="tce-empty-inline">Cola vacía — nada listo para despachar ahora mismo.</div>
          <table v-else class="tce-table">
            <thead>
              <tr>
                <th class="tce-col-pos">#</th>
                <th>Item</th>
                <th>Módulo</th>
                <th>Riesgo</th>
                <th>Prioridad</th>
                <th>Urgente</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in cola" :key="it.id">
                <td class="tce-col-pos">{{ idx + 1 }}</td>
                <td class="tce-col-item">
                  <a :href="'/roadmap/item/' + it.id" target="_blank" rel="noopener">
                    <b class="tce-idnum">#{{ it.id }}</b> {{ it.title || '(sin título)' }}
                  </a>
                </td>
                <td>{{ it.modulo || '—' }}</td>
                <td><span class="tce-tag" :class="lvClass(it.nivel_riesgo)">{{ it.nivel_riesgo || '—' }}</span></td>
                <td><span class="tce-prio" :class="'tce-prio-' + (it.priority || 'none')">{{ prioLabel(it.priority) }}</span></td>
                <td><span v-if="it.urgente" class="tce-urgente"><i class="bi bi-lightning-fill"></i> urgente</span><span v-else class="tce-muted">—</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Excluidos: aprobados que NO se despachan, con su causa concreta. -->
        <div class="tce-section">
          <div class="tce-section-h">
            <i class="bi bi-slash-circle"></i> Excluidos del despacho
            <span class="tce-count">{{ excluidos.length }}</span>
          </div>
          <div v-if="!excluidos.length" class="tce-empty-inline">Ningún item aprobado está excluido ahora mismo.</div>
          <table v-else class="tce-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Módulo</th>
                <th>Riesgo</th>
                <th>Estado</th>
                <th>Causa</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ex in excluidos" :key="ex.id">
                <td class="tce-col-item">
                  <a :href="'/roadmap/item/' + ex.id" target="_blank" rel="noopener">
                    <b class="tce-idnum">#{{ ex.id }}</b> {{ ex.title || '(sin título)' }}
                  </a>
                </td>
                <td>{{ ex.modulo || '—' }}</td>
                <td><span class="tce-tag" :class="lvClass(ex.nivel_riesgo)">{{ ex.nivel_riesgo || '—' }}</span></td>
                <td>{{ estadoAprobLabel(ex.estado_aprobacion) }}</td>
                <td class="tce-causa">
                  {{ ex.causa }}
                  <span v-if="ex.accion" class="tce-accion">→ {{ ex.accion }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="tce-empty-inline">Cargando cola…</div>
    </template>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

const POLL_MS = 10000;

export default {
    name: "TorreColaEjecutable",
    setup() {
        const cargado = ref(false);
        const sinPermiso = ref(false);
        const error = ref("");
        const repartoActivo = ref(false);
        const cola = ref([]);
        const ordenFrase = ref("");
        const excluidos = ref([]);
        let pollTimer = null;

        async function cargar() {
            try {
                const { data } = await axios.get("/api/roadmap/torre/cola");
                repartoActivo.value = !!data.reparto_activo;
                cola.value = data.cola || [];
                ordenFrase.value = data.orden_frase || "";
                excluidos.value = data.excluidos || [];
                cargado.value = true;
                error.value = "";
            } catch (e) {
                if (e.response && e.response.status === 403) {
                    sinPermiso.value = true;
                    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                    return;
                }
                error.value = (e.response && e.response.data && (e.response.data.message || e.response.data.error)) || "Error de red.";
            }
        }

        const lvClass = (n) => (n === "A" ? "tce-lvA" : n === "B" ? "tce-lvB" : n === "C" ? "tce-lvC" : "tce-lvNone");
        const prioLabel = (p) => ({ alta: "Alta", media: "Media", baja: "Baja" }[p] || "Sin prioridad");
        const estadoAprobLabel = (e) => ({
            pendiente_revision: "pendiente revisión",
            requiere_irving: "requiere Irving",
            en_progreso: "en progreso",
            aprobado_irving: "aprobado",
            aprobado_claude: "aprobado (auto)",
            aprobado_revisor: "aprobado (revisor)",
            completado: "completado",
            cancelado: "cancelado",
            rechazado: "rechazado",
        }[e] || e || "—");

        onMounted(() => {
            cargar();
            pollTimer = setInterval(cargar, POLL_MS);
        });
        onUnmounted(() => {
            if (pollTimer) clearInterval(pollTimer);
        });

        return {
            dark: darkMode,
            cargado, sinPermiso, error, repartoActivo, cola, ordenFrase, excluidos,
            lvClass, prioLabel, estadoAprobLabel,
        };
    },
};
</script>

<style scoped>
.tce-wrap{
  --tce-bg:#f8fafc; --tce-surface:#fff; --tce-ink:#0f172a; --tce-muted:#64748b; --tce-line:#e5e7eb;
  --tce-accent:#0d9488; --tce-live:#10b981; --tce-warn:#d97706; --tce-danger:#dc2626;
  color:var(--tce-ink);
}
.tce-wrap.tce-dark{
  --tce-bg:#0b1220; --tce-surface:#0f172a; --tce-ink:#e2e8f0; --tce-muted:#94a3b8; --tce-line:#1e293b;
  --tce-accent:#2dd4bf; --tce-live:#34d399; --tce-warn:#fbbf24; --tce-danger:#f87171;
}

.tce-empty{ text-align:center; padding:46px 20px; border:1px dashed var(--tce-line); border-radius:14px; background:var(--tce-surface); }
.tce-empty-ico{ font-size:34px; color:var(--tce-muted); }
.tce-empty-h{ font-weight:700; margin:10px 0 4px; }
.tce-empty-p{ color:var(--tce-muted); font-size:13px; max-width:560px; margin:0 auto; line-height:1.55; }
.tce-empty-inline{ color:var(--tce-muted); font-size:13px; font-style:italic; padding:14px 4px; }

.tce-banner{
  display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;
  padding:14px 18px; border-radius:14px; margin-bottom:14px; font-weight:800; font-size:18px;
}
.tce-banner-on{ background:rgba(16,185,129,.14); color:var(--tce-live); }
.tce-banner-off{ background:rgba(217,119,6,.14); color:var(--tce-warn); }
.tce-banner-sub{ font-size:12px; font-weight:600; color:var(--tce-muted); }

.tce-frase{ color:var(--tce-muted); font-size:13px; margin:0 0 16px; }

.tce-section{ background:var(--tce-surface); border:1px solid var(--tce-line); border-radius:14px; padding:14px 16px; margin-bottom:16px; }
.tce-section-h{ display:flex; align-items:center; gap:8px; font-weight:700; font-size:14px; margin-bottom:10px; }
.tce-count{ background:var(--tce-bg); color:var(--tce-muted); border-radius:999px; padding:1px 9px; font-size:11.5px; font-weight:700; }

.tce-table{ width:100%; border-collapse:collapse; font-size:13px; }
.tce-table th{ text-align:left; font-size:11px; font-weight:700; color:var(--tce-muted); text-transform:uppercase; letter-spacing:.02em; padding:6px 8px; border-bottom:1px solid var(--tce-line); }
.tce-table td{ padding:8px; border-bottom:1px solid var(--tce-line); vertical-align:top; }
.tce-table tbody tr:last-child td{ border-bottom:none; }
.tce-col-pos{ width:34px; text-align:center; font-weight:800; color:var(--tce-accent); }
.tce-col-item a{ color:var(--tce-ink); text-decoration:none; }
.tce-col-item a:hover{ text-decoration:underline; color:var(--tce-accent); }
.tce-idnum{ color:var(--tce-accent); }
.tce-muted{ color:var(--tce-muted); }
.tce-causa{ color:var(--tce-ink); }
.tce-accion{ display:block; color:var(--tce-muted); font-size:12px; margin-top:2px; }

.tce-urgente{ color:var(--tce-danger); font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:3px; }

.tce-tag{ display:inline-block; font-size:11px; font-weight:700; padding:2px 7px; border-radius:6px; }
.tce-lvA{ background:#ecfdf5; color:#047857; } .tce-lvB{ background:#fffbeb; color:#b45309; }
.tce-lvC{ background:#fef2f2; color:#b91c1c; } .tce-lvNone{ background:#f1f5f9; color:#475569; }
.tce-dark .tce-lvA{ background:rgba(52,211,153,.15); color:#34d399; } .tce-dark .tce-lvB{ background:rgba(251,191,36,.15); color:#fbbf24; }
.tce-dark .tce-lvC{ background:rgba(248,113,113,.15); color:#f87171; } .tce-dark .tce-lvNone{ background:rgba(148,163,184,.15); color:#94a3b8; }

.tce-prio{ display:inline-block; font-size:11px; font-weight:700; padding:2px 7px; border-radius:6px; }
.tce-prio-alta{ background:#fef2f2; color:#b91c1c; } .tce-prio-media{ background:#fffbeb; color:#b45309; }
.tce-prio-baja{ background:#eff6ff; color:#1d4ed8; } .tce-prio-none{ background:#f1f5f9; color:#475569; }
.tce-dark .tce-prio-alta{ background:rgba(248,113,113,.15); color:#f87171; } .tce-dark .tce-prio-media{ background:rgba(251,191,36,.15); color:#fbbf24; }
.tce-dark .tce-prio-baja{ background:rgba(96,165,250,.15); color:#60a5fa; } .tce-dark .tce-prio-none{ background:rgba(148,163,184,.15); color:#94a3b8; }
</style>
