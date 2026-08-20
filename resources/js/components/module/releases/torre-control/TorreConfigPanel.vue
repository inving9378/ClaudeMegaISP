<template>
  <!-- Engrane: al final de la barra de pestañas, empujado con margin-left:auto -->
  <button class="tcfg-gear" :class="{ 'tcfg-dark': dark }" type="button"
          title="Configuración de la Torre" @click="abrir">
    <i class="bi bi-gear-fill"></i>
  </button>

  <!-- Backdrop DEBAJO del modal (z-index 1050 / 1060): si se invierte, el modal queda inerte -->
  <teleport to="body">
    <div v-if="open" class="tcfg-backdrop" @click.self="cerrar"></div>
    <div v-if="open" class="tcfg-modal" :class="{ 'tcfg-dark': dark }" role="dialog" aria-modal="true">

      <header class="tcfg-head">
        <div>
          <h2 class="tcfg-h2"><i class="bi bi-gear-fill me-2"></i>Configuración de la Torre</h2>
          <p class="tcfg-sub">Todo lo que decide cuánto puede avanzar el circuito sin ti.</p>
        </div>
        <button class="tcfg-x" @click="cerrar" title="Cerrar">✕</button>
      </header>

      <div v-if="cargando" class="tcfg-load">Cargando la política vigente…</div>

      <div v-else-if="error" class="tcfg-alert">{{ error }}</div>

      <div v-else class="tcfg-body">

        <!-- Aviso de solo lectura: el panel se ve COMPLETO, no se oculta -->
        <div v-if="!puedeEditar" class="tcfg-ro">
          <i class="bi bi-eye"></i> Solo lectura — necesitas <code>torre.config.edit</code> para guardar.
          Ves la política completa a propósito: saber bajo qué régimen corre el circuito no depende de
          poder cambiarlo.
        </div>

        <!-- ── POLÍTICA BASE ────────────────────────────────────────────── -->
        <section class="tcfg-sec">
          <h3 class="tcfg-h3">Política base <span class="tcfg-tag">no es un techo</span></h3>
          <p class="tcfg-note">
            Es el valor <b>por defecto</b>: un override sobre un item concreto puede excederla — salvo
            en <b>Manual</b>, que es absoluto y no admite excepciones.
          </p>

          <div class="tcfg-niveles">
            <label v-for="n in niveles" :key="n" class="tcfg-nivel"
                   :class="{ 'tcfg-on': form.nivel_automatizacion === n, 'tcfg-dis': !puedeEditar }">
              <input type="radio" :value="n" v-model="form.nivel_automatizacion" :disabled="!puedeEditar">
              <b>{{ etiquetaNivel(n) }}</b>
              <span>{{ descNivel(n) }}</span>
            </label>
          </div>

          <table class="tcfg-matriz">
            <thead><tr><th>Nivel del item</th><th v-for="n in niveles" :key="n">{{ etiquetaNivel(n) }}</th></tr></thead>
            <tbody>
              <tr v-for="lv in ['A','B','C']" :key="lv">
                <td><b>{{ lv }}</b></td>
                <td v-for="n in niveles" :key="n"
                    :class="celda(lv, n) === 'auto' ? 'tcfg-auto' : 'tcfg-irving'">
                  {{ celda(lv, n) === 'auto' ? 'auto' : 'Irving' }}
                </td>
              </tr>
              <tr class="tcfg-bloq">
                <td>Toca prod · borrar datos<br>dinero · credenciales</td>
                <td v-for="n in niveles" :key="n">BLOQUEADO</td>
              </tr>
            </tbody>
          </table>

          <h4 class="tcfg-h4">Nivel efectivo por actor</h4>
          <p class="tcfg-note">
            Cada actor tiene su propio sub-techo. El efectivo es <code>min(base, sub-techo)</code>.
            Que uno sea más conservador que la base <b>es información, no una inconsistencia</b>.
          </p>
          <table class="tcfg-actores">
            <tr v-for="(d, a) in politica.actores" :key="a">
              <td>{{ a }}</td>
              <td>{{ d.sub_techo || '—' }}</td>
              <td><b>{{ d.efectivo || 'ninguno' }}</b></td>
              <td class="tcfg-note">{{ d.sub_techo === null ? 'sólo la base lo gobierna' : (d.efectivo !== d.sub_techo ? 'topado por la base' : '') }}</td>
            </tr>
          </table>

          <div v-if="politica.overrides_excedentes.total > 0" class="tcfg-over">
            <b>{{ politica.overrides_excedentes.total }}</b> item(s) con override por encima de la política base:
            <span v-for="id in politica.overrides_excedentes.ids.slice(0, 12)" :key="id" class="tcfg-id">#{{ id }}</span>
          </div>
        </section>

        <!-- ── AUDITOR ──────────────────────────────────────────────────── -->
        <section class="tcfg-sec">
          <h3 class="tcfg-h3">Auditor <span class="tcfg-tag">el generador de trabajo</span></h3>
          <label class="tcfg-campo">
            <span>Activo</span>
            <input type="checkbox" v-model="form.auditor_activo" :disabled="!puedeEditar">
          </label>
          <label class="tcfg-campo">
            <span>Máximo de items por corrida <em>(1–20)</em></span>
            <input type="number" min="1" max="20" v-model.number="form.auditor_max_por_corrida" :disabled="!puedeEditar">
          </label>
          <label class="tcfg-campo">
            <span>Cooldown entre auditorías, minutos <em>(5–1440)</em></span>
            <input type="number" min="5" max="1440" v-model.number="form.auditor_cooldown_min" :disabled="!puedeEditar">
          </label>
        </section>

        <!-- ── MOTORES: por última ejecución, NUNCA por el flag ─────────── -->
        <section class="tcfg-sec">
          <h3 class="tcfg-h3">Motores programados</h3>
          <p class="tcfg-note">
            Un motor está vivo si <b>corrió bien hace poco</b>, no si alguien dejó un booleano en
            <code>true</code>. Un flag de habilitación es una intención; la última ejecución es un hecho.
          </p>
          <table class="tcfg-motores">
            <thead><tr><th>Motor</th><th>Última ejecución</th><th>Tope</th><th>Agendado</th></tr></thead>
            <tbody>
              <tr v-for="m in motores" :key="m.comando" :class="{ 'tcfg-rojo': m.vencido }">
                <td><code>{{ m.comando }}</code></td>
                <td>
                  <b v-if="m.at">hace {{ humano(m.horas) }}</b>
                  <b v-else class="tcfg-nunca">NUNCA</b>
                </td>
                <td>{{ m.max_horas }} h</td>
                <td>
                  <span v-if="m.agendado === false" class="tcfg-nunca">NO está en el crontab</span>
                  <span v-else-if="m.agendado">sí</span>
                  <span v-else>—</span>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-for="m in motoresRotos" :key="'w'+m.comando" class="tcfg-pierde">
            <b>{{ m.comando }}</b> — se pierde: {{ m.si_no_corre }}
          </p>
        </section>

        <!-- ── GUARDRAILS ───────────────────────────────────────────────── -->
        <section class="tcfg-sec">
          <h3 class="tcfg-h3">Guardrails <span class="tcfg-tag">no editables desde aquí</span></h3>
          <p class="tcfg-note">
            No tienen endpoint. Un panel web capaz de apagar la separación dev/prod
            <b>es un control remoto para apagarla</b>.
          </p>
          <div v-for="(g, i) in guardrails" :key="i" class="tcfg-guard">
            <span>{{ g.icono }}</span> {{ g.texto }} <em>· {{ g.donde }}</em>
          </div>
        </section>
      </div>

      <footer class="tcfg-foot">
        <span class="tcfg-note">Cambios auditados en el canal <code>torre_config</code>.</span>
        <div>
          <button class="tcfg-btn" @click="cerrar">{{ puedeEditar ? 'Cancelar' : 'Cerrar' }}</button>
          <button v-if="puedeEditar" class="tcfg-btn tcfg-primary" :disabled="guardando || !haCambiado" @click="intentarGuardar">
            {{ guardando ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </footer>
    </div>

    <!-- Confirmación al SUBIR el techo: es un evento de seguridad, no una preferencia -->
    <div v-if="confirmar" class="tcfg-backdrop tcfg-backdrop-alto" @click.self="confirmar = false"></div>
    <div v-if="confirmar" class="tcfg-confirm" :class="{ 'tcfg-dark': dark }">
      <h3>⚠️ Vas a SUBIR la automatización</h3>
      <p>
        De <b>{{ etiquetaNivel(politica.nivel_automatizacion) }}</b> a
        <b>{{ etiquetaNivel(form.nivel_automatizacion) }}</b>.
      </p>
      <p v-if="form.nivel_automatizacion === 'autonomo'">
        En <b>Autónomo</b> un item de <b>nivel C</b> —decisión de diseño o arquitectura— se ejecuta
        <b>sin que nadie lo revise</b>.
      </p>
      <p class="tcfg-note">
        Los cuatro topes duros siguen vigentes y esto no los levanta: producción, borrar datos,
        dinero y credenciales seguirán yendo a tu bandeja.
      </p>
      <div class="tcfg-confirm-btns">
        <button class="tcfg-btn" @click="confirmar = false">Cancelar</button>
        <button class="tcfg-btn tcfg-danger" @click="guardar">Sí, subir la automatización</button>
      </div>
    </div>
  </teleport>
</template>

<script>
import { darkMode } from '../../../../hook/appConfig.js';

const { ref, reactive, computed } = Vue;

export default {
  name: 'TorreConfigPanel',
  // El tema se lee del hook del proyecto (body[data-layout-mode]) en vez de recibirlo por prop:
  // así el engrane funciona desde cualquier padre y respeta el toggle claro/oscuro existente.
  setup() {
    const open      = ref(false);
    const cargando  = ref(false);
    const guardando = ref(false);
    const confirmar = ref(false);
    const error     = ref('');
    const puedeEditar = ref(false);
    const politica  = ref(null);
    const motores   = ref([]);
    const guardrails = ref([]);

    // Props numéricas TIPADAS: ref(0), nunca ref(null) — un null en un <input type=number>
    // se convierte en string vacío y vuelve como null al servidor.
    const form = reactive({
      nivel_automatizacion: 'estandar',
      auditor_activo: true,
      auditor_max_por_corrida: 0,
      auditor_cooldown_min: 0,
    });
    let original = {};

    const niveles = computed(() => politica.value?.niveles || ['manual', 'estandar', 'asistido', 'autonomo']);
    const ORDEN   = { manual: 0, estandar: 1, asistido: 2, autonomo: 3 };

    const ETIQUETAS = { manual: 'Manual', estandar: 'Estándar', asistido: 'Asistido', autonomo: 'Autónomo' };
    const DESCS = {
      manual:   'Nada avanza sin ti.',
      estandar: 'A corre solo. B y C esperan.',
      asistido: 'A y B corren solos. C no.',
      autonomo: 'A, B y C corren salvo los topes.',
    };
    const etiquetaNivel = (n) => ETIQUETAS[n] || n;
    const descNivel     = (n) => DESCS[n] || '';
    const celda = (lv, n) => politica.value?.matriz?.[lv]?.[n] || 'irving';

    const haCambiado = computed(() => Object.keys(form).some((k) => form[k] !== original[k]));
    const sube = computed(() => (ORDEN[form.nivel_automatizacion] ?? 0) > (ORDEN[original.nivel_automatizacion] ?? 0));
    const motoresRotos = computed(() => motores.value.filter((m) => m.vencido && m.si_no_corre));

    const humano = (h) => (h === null || h === undefined ? '—' : (h < 1 ? `${Math.round(h * 60)} min` : (h < 48 ? `${h} h` : `${Math.round(h / 24)} días`)));

    async function abrir() {
      open.value = true;
      cargando.value = true;
      error.value = '';
      try {
        const { data } = await axios.get('/api/roadmap/torre/config');
        politica.value    = data.politica;
        motores.value     = data.motores || [];
        guardrails.value  = data.guardrails || [];
        puedeEditar.value = !!data.puede_editar;
        form.nivel_automatizacion    = data.politica.nivel_automatizacion;
        form.auditor_activo          = !!data.politica.auditor.activo;
        form.auditor_max_por_corrida = data.politica.auditor.max_por_corrida;
        form.auditor_cooldown_min    = data.politica.auditor.cooldown_min;
        original = { ...form };
      } catch (e) {
        error.value = e?.response?.data?.message || 'No se pudo leer la configuración de la Torre.';
      } finally {
        cargando.value = false;
      }
    }

    function cerrar() { open.value = false; confirmar.value = false; }

    // Subir la automatización es un EVENTO DE SEGURIDAD, no una preferencia: segundo diálogo.
    // Bajarla no pide nada.
    function intentarGuardar() { sube.value ? (confirmar.value = true) : guardar(); }

    async function guardar() {
      guardando.value = true;
      confirmar.value = false;
      try {
        const { data } = await axios.post('/api/roadmap/torre/config', { ...form });
        politica.value = data.politica;
        original = { ...form };
      } catch (e) {
        error.value = e?.response?.data?.message || 'No se pudo guardar.';
      } finally {
        guardando.value = false;
      }
    }

    return { open, cargando, guardando, confirmar, error, puedeEditar, politica, motores, guardrails,
             form, niveles, etiquetaNivel, descNivel, celda, haCambiado, motoresRotos, humano,
             abrir, cerrar, intentarGuardar, guardar, dark: darkMode };
  },
};
</script>

<style scoped>
.tcfg-gear{margin-left:auto;width:34px;height:34px;border-radius:9px;border:1px solid var(--tc-border,#d7dee7);background:var(--tc-card,#fff);color:var(--tc-fg,#334155);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}
.tcfg-gear:hover{background:var(--tc-accent,#0d9488);color:#fff;border-color:transparent;}
/* backdrop DEBAJO del modal — invertirlo deja el modal inerte */
.tcfg-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1050;}
.tcfg-backdrop-alto{z-index:1070;}
.tcfg-modal{position:fixed;z-index:1060;top:50%;left:50%;transform:translate(-50%,-50%);width:min(880px,94vw);max-height:88vh;display:flex;flex-direction:column;background:var(--tc-card,#fff);color:var(--tc-fg,#0f172a);border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.3);}
.tcfg-confirm{position:fixed;z-index:1080;top:50%;left:50%;transform:translate(-50%,-50%);width:min(520px,92vw);padding:22px;background:var(--tc-card,#fff);color:var(--tc-fg,#0f172a);border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.35);border-top:4px solid #dc2626;}
.tcfg-head{display:flex;justify-content:space-between;align-items:flex-start;padding:18px 22px 10px;border-bottom:1px solid var(--tc-border,#e2e8f0);}
.tcfg-h2{font-size:17px;font-weight:800;margin:0;}
.tcfg-h3{font-size:14px;font-weight:800;margin:0 0 6px;}
.tcfg-h4{font-size:12.5px;font-weight:800;margin:16px 0 4px;}
.tcfg-sub,.tcfg-note{font-size:12px;opacity:.75;margin:2px 0 0;line-height:1.5;}
.tcfg-tag{font-size:10.5px;font-weight:600;opacity:.6;margin-left:6px;}
.tcfg-x{background:none;border:none;font-size:18px;cursor:pointer;color:inherit;opacity:.6;}
.tcfg-body{overflow-y:auto;padding:16px 22px;}
.tcfg-sec{padding:14px 0;border-bottom:1px solid var(--tc-border,#eef2f7);}
.tcfg-ro{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:9px;padding:9px 12px;font-size:12px;margin-bottom:12px;line-height:1.5;}
.tcfg-niveles{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin:10px 0;}
.tcfg-nivel{border:1px solid var(--tc-border,#d7dee7);border-radius:9px;padding:9px 11px;cursor:pointer;display:flex;flex-direction:column;gap:2px;font-size:12px;}
.tcfg-nivel b{font-size:13px;}
.tcfg-nivel span{opacity:.7;}
.tcfg-on{border-color:var(--tc-accent,#0d9488);box-shadow:inset 0 0 0 1px var(--tc-accent,#0d9488);}
.tcfg-dis{opacity:.6;cursor:not-allowed;}
.tcfg-matriz,.tcfg-actores,.tcfg-motores{width:100%;border-collapse:collapse;font-size:12px;margin-top:8px;}
.tcfg-matriz th,.tcfg-matriz td,.tcfg-actores td,.tcfg-motores th,.tcfg-motores td{border:1px solid var(--tc-border,#e2e8f0);padding:5px 8px;text-align:left;}
.tcfg-auto{color:#15803d;font-weight:700;}
.tcfg-irving{color:#b45309;}
.tcfg-bloq td{background:rgba(220,38,38,.07);color:#b91c1c;font-weight:700;font-size:11px;}
.tcfg-campo{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:6px 0;font-size:12.5px;}
.tcfg-campo em{opacity:.6;font-style:normal;font-size:11px;}
.tcfg-campo input[type=number]{width:96px;padding:4px 8px;border:1px solid var(--tc-border,#d7dee7);border-radius:7px;background:transparent;color:inherit;}
.tcfg-rojo td{background:rgba(220,38,38,.08);}
.tcfg-nunca{color:#b91c1c;font-weight:700;}
.tcfg-pierde{font-size:11.5px;color:#b91c1c;margin:6px 0 0;}
.tcfg-guard{font-size:12px;padding:4px 0;opacity:.85;}
.tcfg-guard em{opacity:.6;font-style:normal;}
.tcfg-over{margin-top:10px;background:rgba(217,119,6,.1);border:1px solid rgba(217,119,6,.35);border-radius:8px;padding:8px 11px;font-size:12px;}
.tcfg-id{display:inline-block;margin-left:5px;font-weight:700;}
.tcfg-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 22px;border-top:1px solid var(--tc-border,#e2e8f0);}
.tcfg-btn{padding:6px 14px;border-radius:8px;border:1px solid var(--tc-border,#d7dee7);background:transparent;color:inherit;cursor:pointer;font-size:12.5px;margin-left:8px;}
.tcfg-primary{background:var(--tc-accent,#0d9488);color:#fff;border-color:transparent;font-weight:700;}
.tcfg-primary:disabled{opacity:.45;cursor:not-allowed;}
.tcfg-danger{background:#dc2626;color:#fff;border-color:transparent;font-weight:700;}
.tcfg-confirm-btns{display:flex;justify-content:flex-end;margin-top:14px;}
.tcfg-load,.tcfg-alert{padding:24px 22px;font-size:13px;}
.tcfg-alert{color:#b91c1c;}
/* dark: sólo se redefinen tokens, nunca colores sueltos */
.tcfg-dark{--tc-card:#0f172a;--tc-fg:#e2e8f0;--tc-border:#1e293b;}
.tcfg-dark .tcfg-ro{background:rgba(59,130,246,.12);color:#93c5fd;border-color:#1e3a5f;}
</style>
