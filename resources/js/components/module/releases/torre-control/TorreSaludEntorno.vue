<template>
  <!-- Item #891 (+ #884, mismo panel) — Fase 7 de la Épica #874: los indicadores que hoy solo se ven
       entrando por SSH (certificado TLS, disco, migraciones pendientes, jobs fallidos, último
       respaldo, errores 24h agrupados por firma, cron schedule:run, queue workers de supervisor).
       Solo lectura, salvo los 2 botones declarados por #891. -->
  <div class="se-wrap" :class="{ 'se-dark': dark }">

    <div class="se-bar">
      <span class="se-bar-title"><i class="bi bi-heart-pulse me-1"></i> Salud del entorno</span>
      <span class="se-bar-meta">{{ generadoHace }}</span>
      <button class="se-refresh" type="button" :disabled="cargando" @click="cargar">
        <i class="bi" :class="cargando ? 'bi-arrow-repeat se-spin' : 'bi-arrow-repeat'"></i> Actualizar
      </button>
    </div>

    <p v-if="error" class="se-error">{{ error }}</p>

    <div v-if="cargando && !cargadoUnaVez" class="se-meta">Leyendo indicadores del servidor…</div>

    <template v-else-if="!error">
      <div class="se-grid">
        <!-- Certificado TLS -->
        <div class="se-card" :class="claseEstado(d.certificado?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Certificado TLS</div>
          <template v-if="d.certificado?.error">
            <div class="se-desconocido">{{ d.certificado.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ d.certificado?.dias_restantes }} días</div>
            <div class="se-card-s">{{ d.certificado?.host }} · expira {{ fmt(d.certificado?.expira_at) }}</div>
          </template>
        </div>

        <!-- Disco -->
        <div class="se-card" :class="claseEstado(d.disco?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Disco</div>
          <template v-if="d.disco?.error">
            <div class="se-desconocido">{{ d.disco.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ d.disco?.porcentaje }} % usado</div>
            <div class="se-card-s">quedan {{ d.disco?.libre_gb }} GB de {{ d.disco?.total_gb }} GB</div>
          </template>
        </div>

        <!-- Migraciones pendientes -->
        <div class="se-card" :class="claseEstado(d.migraciones?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Migraciones</div>
          <div class="se-card-n">{{ d.migraciones?.cantidad === 0 ? 'ninguna pendiente' : (d.migraciones?.cantidad + ' pendiente(s)') }}</div>
          <div v-if="d.migraciones?.nombres?.length" class="se-card-s se-pre">{{ d.migraciones.nombres.join('\n') }}</div>
        </div>

        <!-- Jobs fallidos -->
        <div class="se-card" :class="claseEstado(d.jobs_fallidos?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Trabajos fallidos</div>
          <div class="se-card-n">{{ d.jobs_fallidos?.cantidad }}</div>
          <div v-if="d.jobs_fallidos?.mas_viejo_at" class="se-card-s">el más viejo, {{ haceHoras(d.jobs_fallidos.mas_viejo_hace_horas) }}</div>
        </div>

        <!-- Último respaldo -->
        <div class="se-card" :class="claseEstado(d.ultimo_respaldo?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Último respaldo</div>
          <template v-if="d.ultimo_respaldo?.error">
            <div class="se-desconocido">{{ d.ultimo_respaldo.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ haceHoras(d.ultimo_respaldo?.hace_horas) }}</div>
            <div class="se-card-s">{{ fmt(d.ultimo_respaldo?.ultimo_at) }} · /var/backups/mysql/</div>
          </template>
        </div>

        <!-- #957 — pulso de circuito:reactivar-agendados (Fase 2 de #921) -->
        <div class="se-card" :class="claseEstado(d.reactivacion_agendados?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Reactivación de agendados</div>
          <template v-if="d.reactivacion_agendados?.error">
            <div class="se-desconocido">{{ d.reactivacion_agendados.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ d.reactivacion_agendados?.nunca ? 'nunca ha corrido' : haceHoras(d.reactivacion_agendados?.hace_horas) }}</div>
            <div class="se-card-s">circuito:reactivar-agendados · diario 00:05</div>
            <div v-if="d.reactivacion_agendados?.ultimo_fallo" class="se-card-s">último fallo: {{ d.reactivacion_agendados.ultimo_fallo.error }}</div>
          </template>
        </div>

        <!-- #884 — cron schedule:run -->
        <div class="se-card" :class="claseEstado(d.cron_schedule_run?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Cron schedule:run</div>
          <template v-if="d.cron_schedule_run?.error">
            <div class="se-desconocido">{{ d.cron_schedule_run.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ d.cron_schedule_run?.activo ? 'activo' : 'sin configurar' }}</div>
            <div v-if="d.cron_schedule_run?.nota" class="se-card-s">{{ d.cron_schedule_run.nota }}</div>
          </template>
        </div>

        <!-- #884 — queue workers de supervisor -->
        <div class="se-card" :class="claseEstado(d.queue_workers?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Queue workers</div>
          <template v-if="d.queue_workers?.error">
            <div class="se-desconocido">{{ d.queue_workers.error }}</div>
          </template>
          <template v-else>
            <div class="se-card-n">{{ d.queue_workers?.cantidad }} corriendo</div>
            <div class="se-card-s">mínimo esperado: {{ d.queue_workers?.esperado }}</div>
          </template>
        </div>

        <!-- Errores 24h, agrupados por firma -->
        <div class="se-card" :class="claseEstado(d.errores_24h?.estado)">
          <div class="se-card-h"><span class="se-dot"></span> Errores 24 h</div>
          <div class="se-card-n">
            {{ d.errores_24h?.total || 0 }} repetición(es) de {{ d.errores_24h?.grupos?.length || 0 }} error(es)
          </div>
          <button v-if="d.errores_24h?.grupos?.length" class="se-ver" type="button" @click="erroresAbiertos = !erroresAbiertos">
            {{ erroresAbiertos ? 'Ocultar ▲' : 'Ver detalle ▼' }}
          </button>
        </div>
      </div>

      <!-- Detalle de errores agrupados (nunca en crudo: una fila por firma, no por línea) -->
      <div v-if="erroresAbiertos && d.errores_24h?.grupos?.length" class="se-detalle">
        <table class="se-table">
          <thead>
            <tr><th>Repeticiones</th><th>Mensaje</th><th>Primera vez</th><th>Última vez</th></tr>
          </thead>
          <tbody>
            <tr v-for="(g, i) in d.errores_24h.grupos" :key="i">
              <td class="se-td-n">{{ g.cantidad }}</td>
              <td :title="g.mensaje">{{ g.mensaje }}</td>
              <td class="se-td-ts">{{ fmt(g.primera_at) }}</td>
              <td class="se-td-ts">{{ fmt(g.ultima_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Botones (#891): SOLO los 2 declarados por el item, gate torre.salud.manage server-side -->
      <div v-if="d.puede_gestionar" class="se-acciones">
        <div class="se-accion">
          <button class="se-btn" type="button" :disabled="reintentando" @click="reintentarFallidos">
            {{ reintentando ? 'Reencolando…' : '↻ Reintentar trabajos fallidos' }}
          </button>
          <span v-if="msgReintentar" class="se-msg">{{ msgReintentar }}</span>
        </div>

        <div class="se-accion se-accion-caches">
          <button class="se-btn" type="button" :disabled="recalentando" @click="recalentarCaches">
            {{ recalentando ? 'Recalentando…' : '🔥 Limpiar y recalentar cachés' }}
          </button>
          <label class="se-chk" :title="cacheSeguro ? 'Incluir config:cache' : d.caches?.motivo">
            <input type="checkbox" v-model="incluirConfig" :disabled="!cacheSeguro" />
            incluir <code>config:cache</code>
          </label>
          <span v-if="!cacheSeguro" class="se-motivo">{{ d.caches?.motivo || 'config:auditar-env no reporta limpio todavía.' }}</span>
          <span v-if="msgCaches" class="se-msg">{{ msgCaches }}</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

export default {
    name: "TorreSaludEntorno",
    setup() {
        const d = ref({});
        const cargando = ref(false);
        const cargadoUnaVez = ref(false);
        const error = ref(null);
        const erroresAbiertos = ref(false);
        const incluirConfig = ref(false);
        const reintentando = ref(false);
        const recalentando = ref(false);
        const msgReintentar = ref("");
        const msgCaches = ref("");
        let timer = null;

        const cacheSeguro = computed(() => !!(d.value.caches && d.value.caches.config_cache_seguro));

        function fmt(iso) {
            if (!iso) return "—";
            try {
                return new Date(iso).toLocaleString("es-MX", { dateStyle: "short", timeStyle: "short" });
            } catch (e) {
                return iso;
            }
        }

        function haceHoras(horas) {
            if (horas === null || horas === undefined) return "—";
            if (horas < 1) return "hace menos de 1 h";
            if (horas < 48) return `hace ${Math.round(horas)} h`;
            return `hace ${Math.round(horas / 24)} día(s)`;
        }

        function claseEstado(estado) {
            return {
                "se-verde": estado === "verde",
                "se-amarillo": estado === "amarillo",
                "se-rojo": estado === "rojo",
                "se-gris": estado === "desconocido" || !estado,
            };
        }

        const generadoHace = computed(() => (d.value.generado_at ? `actualizado ${fmt(d.value.generado_at)}` : ""));

        async function cargar() {
            cargando.value = true;
            error.value = null;
            try {
                const { data } = await axios.get("/api/roadmap/torre/salud-entorno");
                d.value = data;
                cargadoUnaVez.value = true;
            } catch (e) {
                error.value = (e.response && e.response.data && e.response.data.error) || "No se pudo leer la salud del entorno.";
            } finally {
                cargando.value = false;
            }
        }

        async function reintentarFallidos() {
            if (reintentando.value) return;
            if (!window.confirm("¿Reintentar todos los trabajos fallidos ahora?")) return;
            reintentando.value = true;
            msgReintentar.value = "";
            try {
                const { data } = await axios.post("/api/roadmap/torre/salud/reintentar-fallidos");
                msgReintentar.value = `Se reencolaron ${data.reencolados} trabajo(s); quedan ${data.quedan}.`;
                await cargar();
            } catch (e) {
                msgReintentar.value = (e.response && e.response.data && e.response.data.message) || "No se pudo reintentar.";
            } finally {
                reintentando.value = false;
            }
        }

        async function recalentarCaches() {
            if (recalentando.value) return;
            recalentando.value = true;
            msgCaches.value = "";
            try {
                const { data } = await axios.post("/api/roadmap/torre/salud/recalentar-caches", {
                    incluir_config: incluirConfig.value,
                });
                msgCaches.value = data.config_cache_ejecutado
                    ? "Cachés recalentadas, incluido config:cache."
                    : (data.config_cache_motivo ? `Cachés recalentadas. config:cache omitido: ${data.config_cache_motivo}` : "Cachés recalentadas.");
                await cargar();
            } catch (e) {
                msgCaches.value = (e.response && e.response.data && e.response.data.message) || "No se pudo recalentar.";
            } finally {
                recalentando.value = false;
            }
        }

        onMounted(() => {
            cargar();
            timer = setInterval(cargar, 30000);
        });
        onUnmounted(() => {
            if (timer) clearInterval(timer);
        });

        return {
            d, cargando, cargadoUnaVez, error, erroresAbiertos, incluirConfig, cacheSeguro,
            reintentando, recalentando, msgReintentar, msgCaches, generadoHace,
            fmt, haceHoras, claseEstado, cargar, reintentarFallidos, recalentarCaches,
            dark: darkMode,
        };
    },
};
</script>

<style scoped>
.se-wrap{
  --se-bg:#f8fafc; --se-surface:#fff; --se-ink:#0f172a; --se-muted:#64748b; --se-line:#e5e7eb;
  --se-accent:#0d9488; --se-verde:#16a34a; --se-amarillo:#d97706; --se-rojo:#dc2626; --se-gris:#94a3b8;
  color:var(--se-ink);
}
.se-wrap.se-dark{
  --se-bg:#0b1220; --se-surface:#0f172a; --se-ink:#e2e8f0; --se-muted:#94a3b8; --se-line:#1e293b;
  --se-accent:#2dd4bf; --se-verde:#4ade80; --se-amarillo:#fbbf24; --se-rojo:#f87171; --se-gris:#64748b;
}

.se-bar{ display:flex; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
.se-bar-title{ font-weight:700; font-size:15px; }
.se-bar-meta{ font-size:12px; color:var(--se-muted); flex:1 1 auto; }
.se-refresh{
  border:1px solid var(--se-line); background:var(--se-surface); color:var(--se-ink);
  border-radius:8px; padding:5px 10px; font-size:12px; cursor:pointer;
}
.se-refresh:disabled{ opacity:.6; cursor:default; }
.se-spin{ display:inline-block; animation:se-spin 1s linear infinite; }
@keyframes se-spin{ from{ transform:rotate(0); } to{ transform:rotate(360deg); } }

.se-error{ color:var(--se-rojo); font-size:13px; }
.se-meta{ font-size:13px; color:var(--se-muted); }

.se-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:14px; }
.se-card{
  background:var(--se-surface); border:1px solid var(--se-line); border-left:4px solid var(--se-gris);
  border-radius:10px; padding:12px 14px;
}
.se-card.se-verde{ border-left-color:var(--se-verde); }
.se-card.se-amarillo{ border-left-color:var(--se-amarillo); }
.se-card.se-rojo{ border-left-color:var(--se-rojo); }
.se-card.se-gris{ border-left-color:var(--se-gris); }
.se-card-h{ font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--se-muted); display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.se-dot{ width:8px; height:8px; border-radius:50%; background:currentColor; }
.se-verde .se-dot{ color:var(--se-verde); }
.se-amarillo .se-dot{ color:var(--se-amarillo); }
.se-rojo .se-dot{ color:var(--se-rojo); }
.se-gris .se-dot{ color:var(--se-gris); }
.se-card-n{ font-size:18px; font-weight:700; }
.se-card-s{ font-size:12px; color:var(--se-muted); margin-top:2px; }
.se-pre{ white-space:pre-line; }
.se-desconocido{ font-size:12.5px; color:var(--se-muted); }
.se-ver{ margin-top:8px; border:none; background:none; color:var(--se-accent); font-size:12px; font-weight:600; cursor:pointer; padding:0; }

.se-detalle{ margin-bottom:14px; }
.se-table{ width:100%; border-collapse:collapse; font-size:13px; background:var(--se-surface); border:1px solid var(--se-line); border-radius:10px; overflow:hidden; }
.se-table th{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--se-line); color:var(--se-muted); font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
.se-table td{ padding:8px 10px; border-bottom:1px solid var(--se-line); vertical-align:top; }
.se-table tr:last-child td{ border-bottom:none; }
.se-td-n{ font-weight:700; white-space:nowrap; }
.se-td-ts{ white-space:nowrap; color:var(--se-muted); font-variant-numeric:tabular-nums; }

.se-acciones{ display:flex; flex-direction:column; gap:10px; padding-top:10px; border-top:1px dashed var(--se-line); }
.se-accion{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.se-btn{
  border:1px solid var(--se-accent); background:var(--se-accent); color:#fff;
  border-radius:8px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer;
}
.se-btn:disabled{ opacity:.6; cursor:default; }
.se-chk{ font-size:12.5px; color:var(--se-muted); display:flex; align-items:center; gap:5px; }
.se-motivo{ font-size:12px; color:var(--se-amarillo); max-width:480px; }
.se-msg{ font-size:12.5px; color:var(--se-muted); }
</style>
