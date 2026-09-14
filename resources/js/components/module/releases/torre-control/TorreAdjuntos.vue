<template>
  <section class="ad-wrap" :class="{ 'ad-abierto': abierto }">
    <!-- Cabecera compacta: colapsado por defecto para no quitarle espacio al Panorama (punto 6). -->
    <button type="button" class="ad-head" :aria-expanded="abierto ? 'true' : 'false'" @click="toggle">
      <span class="ad-caret" aria-hidden="true">{{ abierto ? '▾' : '▸' }}</span>
      <span class="ad-title">📎 Adjuntos</span>
      <span class="ad-count" :title="total + ' archivo(s) subido(s)'">{{ total }}</span>
      <span class="ad-hint">maquetas · capturas · PDFs · evidencia — amarrables a items</span>
    </button>

    <div v-if="abierto" class="ad-body">
      <p v-if="!puede.subir" class="ad-nota">Puedes ver y descargar; subir requiere el permiso <code>roadmap.adjuntos.upload</code>.</p>

      <!-- Zona de subida: arrastrar-y-soltar + botón (punto 6). -->
      <div
        v-if="puede.subir"
        class="ad-drop"
        :class="{ 'ad-drop-over': arrastrando, 'ad-drop-busy': subiendo }"
        @dragover.prevent="arrastrando = true"
        @dragleave.prevent="arrastrando = false"
        @drop.prevent="onDrop"
      >
        <input ref="inputArchivo" type="file" class="ad-input" :accept="acceptAttr" multiple @change="onSeleccion" />
        <div class="ad-drop-txt">
          <b>Arrastra archivos aquí</b> o
          <button type="button" class="ad-btn ad-btn-primario" :disabled="subiendo" @click="$refs.inputArchivo.click()">Seleccionar archivo</button>
        </div>
        <div class="ad-drop-meta">
          Hasta {{ fmtBytes(limites.max_bytes) }} · {{ limites.extensiones.join(', ') }} · HTML y SVG se entregan siempre como descarga
        </div>
        <div class="ad-campos">
          <input v-model.trim="descripcion" type="text" class="ad-text" maxlength="255"
                 placeholder="Descripción corta (opcional): «maqueta de Panorama en árbol, contrato visual…»" />
          <div class="ad-amarre-inline">
            <input v-model.trim="qItemSubida" type="text" class="ad-text ad-text-sm" placeholder="Amarrar al item… (id o título)" @input="buscarItems(qItemSubida, 'subida')" />
            <ul v-if="sugerencias.subida.length" class="ad-sug">
              <li v-for="it in sugerencias.subida" :key="it.id"><button type="button" @click="elegirItemSubida(it)">#{{ it.id }} · {{ it.title }}</button></li>
            </ul>
            <span v-for="it in itemsSubida" :key="it.id" class="ad-chip">#{{ it.id }} <button type="button" class="ad-chip-x" title="Quitar" @click="itemsSubida = itemsSubida.filter(x => x.id !== it.id)">✕</button></span>
          </div>
        </div>
        <!-- Progreso (punto 10). -->
        <div v-if="subiendo" class="ad-progress" role="progressbar" :aria-valuenow="progreso" aria-valuemin="0" aria-valuemax="100">
          <div class="ad-progress-track"><div class="ad-progress-bar" :style="{ width: progreso + '%' }"></div></div>
          <span class="ad-progress-txt">{{ progreso }}% · {{ progresoTxt }}</span>
        </div>
      </div>

      <!-- Error claro: dice POR QUÉ se rechazó (extensión, tamaño, tipo real) (punto 10). -->
      <div v-if="error" class="ad-error" role="alert">
        <b>No se subió:</b> {{ error }}
        <button type="button" class="ad-link" @click="error = ''">cerrar</button>
      </div>
      <div v-if="aviso" class="ad-ok" role="status">{{ aviso }}</div>

      <div class="ad-toolbar">
        <input v-model.trim="filtro" type="search" class="ad-text ad-text-sm" placeholder="Filtrar por nombre o descripción…" />
        <label class="ad-check"><input v-model="verBorrados" type="checkbox" @change="cargar()" /> ver borrados</label>
        <button type="button" class="ad-btn" :disabled="cargando" @click="cargar()">{{ cargando ? '…' : '↻ Actualizar' }}</button>
      </div>

      <div v-if="cargando && !adjuntos.length" class="ad-vacio">Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="ad-vacio">
        {{ verBorrados ? 'No hay adjuntos borrados.' : 'Todavía no hay adjuntos. Sube la maqueta, la captura o el PDF que la terminal deba tener a la mano.' }}
      </div>

      <!-- Lista (punto 7): nombre, tipo, tamaño, quién, fecha, items; acciones descargar/amarrar/desamarrar/borrar. -->
      <ul v-else class="ad-lista">
        <li v-for="a in listaFiltrada" :key="a.id" class="ad-item" :class="{ 'ad-item-borrado': a.deleted_at }">
          <!-- Miniatura para imágenes, ícono por tipo para el resto (punto 9). -->
          <a class="ad-thumb" :href="urlDescarga(a)" :title="a.previsualizable ? 'Abrir vista previa' : 'Descargar'"
             :target="a.previsualizable ? '_blank' : null" rel="noopener">
            <img v-if="a.es_imagen && a.existe_en_disco" :src="urlDescarga(a, true)" :alt="a.nombre" loading="lazy" />
            <span v-else class="ad-ico" :class="'ad-ico-' + tipoDe(a)">{{ iconoDe(a) }}</span>
          </a>
          <div class="ad-info">
            <div class="ad-nombre-row">
              <a class="ad-nombre" :href="urlDescarga(a)" :title="a.nombre">{{ a.nombre }}</a>
              <span class="ad-tipo">{{ a.extension.toUpperCase() }}</span>
              <span v-if="!a.existe_en_disco" class="ad-tag ad-tag-bad" title="El archivo ya no está en disco">falta en disco</span>
              <span v-if="a.veces_subido > 1" class="ad-tag" :title="'Subido ' + a.veces_subido + ' veces (mismo contenido)'">×{{ a.veces_subido }}</span>
            </div>
            <div v-if="a.descripcion" class="ad-desc">{{ a.descripcion }}</div>
            <div class="ad-meta">
              {{ fmtBytes(a.tamano) }} · {{ a.mime }} · {{ a.subido_por_nombre || ('usuario #' + a.subido_por) }} · <time :datetime="a.created_at" :title="a.created_at">{{ hace(a.created_at) }}</time>
              <template v-if="a.deleted_at"> · <b>borrado</b> {{ hace(a.deleted_at) }}</template>
            </div>
            <div class="ad-items">
              <span v-if="!a.items.length" class="ad-suelto">suelto (sin item)</span>
              <span v-for="it in a.items" :key="it.id" class="ad-chip" :title="it.title">
                <a :href="'/roadmap/item/' + it.id" target="_blank" rel="noopener">#{{ it.id }}</a> {{ recorta(it.title, 34) }}
                <button v-if="puede.subir && !a.deleted_at" type="button" class="ad-chip-x" :title="'Desamarrar del #' + it.id" @click="desamarrar(a, it)">✕</button>
              </span>
              <span v-if="amarrando === a.id" class="ad-amarre-inline">
                <input :ref="'amarre-' + a.id" v-model.trim="qAmarre" type="text" class="ad-text ad-text-sm" placeholder="id o título del item…" @input="buscarItems(qAmarre, 'amarre')" @keydown.esc="amarrando = null" />
                <ul v-if="sugerencias.amarre.length" class="ad-sug">
                  <li v-for="it in sugerencias.amarre" :key="it.id"><button type="button" @click="amarrar(a, it)">#{{ it.id }} · {{ it.title }}</button></li>
                </ul>
              </span>
            </div>
          </div>
          <!-- Acciones (punto 7). -->
          <div class="ad-acciones">
            <a class="ad-btn" :href="urlDescarga(a)" title="Descargar">⬇ Descargar</a>
            <button v-if="puede.subir && !a.deleted_at" type="button" class="ad-btn" @click="abrirAmarre(a)">🔗 Amarrar</button>
            <button v-if="puede.borrar && !a.deleted_at" type="button" class="ad-btn ad-btn-peligro" :disabled="borrando === a.id" @click="borrar(a)">🗑 Borrar</button>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

/**
 * #9991164 (Fase 2 de #9991162) — Panel de adjuntos del Panorama. Solo pinta y llama la API de la
 * Fase 1 (`/api/roadmap/adjuntos`); toda la validación (extensión, tamaño, MIME real, dedupe) la
 * hace el servidor y aquí se muestra tal cual el motivo del rechazo (punto 10). Hereda los tokens
 * `--tc-*` de TorreControl (claro/oscuro) — no define paleta propia (punto 11).
 */
const ICONOS = { imagen: '🖼', pdf: '📕', html: '🧩', texto: '📄', hoja: '📊', doc: '📝', zip: '🗜', otro: '📎' };

export default {
    name: 'TorreAdjuntos',
    emits: ['cambio'],
    setup(props, { emit }) {
        const abierto = ref(false);
        const cargando = ref(false);
        const adjuntos = ref([]);
        const limites = reactive({ max_bytes: 25 * 1024 * 1024, extensiones: [], nunca_inline: [] });
        const puede = reactive({ subir: false, borrar: false });
        const error = ref('');
        const aviso = ref('');
        const filtro = ref('');
        const verBorrados = ref(false);
        const arrastrando = ref(false);
        const subiendo = ref(false);
        const progreso = ref(0);
        const progresoTxt = ref('');
        const descripcion = ref('');
        const qItemSubida = ref('');
        const itemsSubida = ref([]);
        const amarrando = ref(null);
        const qAmarre = ref('');
        const borrando = ref(null);
        const sugerencias = reactive({ subida: [], amarre: [] });
        let avisoTimer = null;
        let buscarTimer = null;

        const total = computed(() => adjuntos.value.filter(a => !a.deleted_at).length);
        const acceptAttr = computed(() => limites.extensiones.map(e => '.' + e).join(','));
        const listaFiltrada = computed(() => {
            const q = filtro.value.toLowerCase();
            return adjuntos.value.filter(a => !q || (a.nombre || '').toLowerCase().includes(q) || (a.descripcion || '').toLowerCase().includes(q));
        });

        function toggle() {
            abierto.value = !abierto.value;
            if (abierto.value && !adjuntos.value.length) cargar();
        }

        async function cargar() {
            cargando.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/adjuntos', { params: verBorrados.value ? { borrados: 1 } : {} });
                adjuntos.value = data.adjuntos || [];
                Object.assign(limites, data.limites || {});
                Object.assign(puede, data.puede || {});
            } catch (e) {
                error.value = msgDe(e, 'No se pudo cargar la lista de adjuntos.');
            } finally {
                cargando.value = false;
            }
        }

        function onDrop(ev) {
            arrastrando.value = false;
            subirVarios(Array.from(ev.dataTransfer?.files || []));
        }
        function onSeleccion(ev) {
            subirVarios(Array.from(ev.target.files || []));
            ev.target.value = '';
        }

        async function subirVarios(archivos) {
            if (!archivos.length || subiendo.value) return;
            error.value = '';
            for (const f of archivos) {
                // eslint-disable-next-line no-await-in-loop
                const ok = await subirUno(f);
                if (!ok) break; // el primer rechazo detiene la tanda y deja el motivo a la vista
            }
            emit('cambio');
        }

        async function subirUno(archivo) {
            // Rechazos baratos ANTES de mandar 25 MB: el servidor sigue siendo la autoridad.
            const ext = (archivo.name.split('.').pop() || '').toLowerCase();
            if (limites.extensiones.length && !limites.extensiones.includes(ext)) {
                error.value = `«${archivo.name}»: extensión .${ext || '—'} no permitida (permitidas: ${limites.extensiones.join(', ')}).`;
                return false;
            }
            if (archivo.size > limites.max_bytes) {
                error.value = `«${archivo.name}»: ${fmtBytes(archivo.size)} supera el límite de ${fmtBytes(limites.max_bytes)}.`;
                return false;
            }
            const fd = new FormData();
            fd.append('archivo', archivo);
            if (descripcion.value) fd.append('descripcion', descripcion.value);
            itemsSubida.value.forEach(it => fd.append('item_ids[]', it.id));
            subiendo.value = true;
            progreso.value = 0;
            progresoTxt.value = `Subiendo ${archivo.name}…`;
            try {
                const { data } = await axios.post('/api/roadmap/adjuntos', fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (p) => { if (p.total) progreso.value = Math.round((p.loaded / p.total) * 100); },
                });
                mostrarAviso(data.mensaje || 'Archivo subido.');
                await cargar();
                return true;
            } catch (e) {
                error.value = `«${archivo.name}»: ${msgDe(e, 'el servidor rechazó el archivo.')}`;
                return false;
            } finally {
                subiendo.value = false;
                progreso.value = 0;
            }
        }

        function buscarItems(q, destino) {
            clearTimeout(buscarTimer);
            if (!q) { sugerencias[destino] = []; return; }
            buscarTimer = setTimeout(async () => {
                try {
                    const { data } = await axios.get('/api/roadmap/items/buscar', { params: { q } });
                    sugerencias[destino] = data.items || [];
                } catch { sugerencias[destino] = []; }
            }, 220);
        }
        function elegirItemSubida(it) {
            if (!itemsSubida.value.some(x => x.id === it.id)) itemsSubida.value.push(it);
            qItemSubida.value = '';
            sugerencias.subida = [];
        }
        function abrirAmarre(a) {
            amarrando.value = amarrando.value === a.id ? null : a.id;
            qAmarre.value = '';
            sugerencias.amarre = [];
        }
        async function amarrar(a, it) {
            try {
                const { data } = await axios.post(`/api/roadmap/adjuntos/${a.id}/amarrar`, { item_id: it.id });
                reemplazar(data.adjunto);
                mostrarAviso(data.mensaje);
                amarrando.value = null;
                emit('cambio');
            } catch (e) { error.value = msgDe(e, 'No se pudo amarrar.'); }
        }
        async function desamarrar(a, it) {
            try {
                const { data } = await axios.post(`/api/roadmap/adjuntos/${a.id}/desamarrar`, { item_id: it.id });
                reemplazar(data.adjunto);
                mostrarAviso(data.mensaje);
                emit('cambio');
            } catch (e) { error.value = msgDe(e, 'No se pudo desamarrar.'); }
        }
        async function borrar(a) {
            const abiertos = a.items.filter(i => !['completado', 'cancelado', 'rechazado'].includes(i.estado_aprobacion));
            const txt = abiertos.length
                ? `«${a.nombre}» está amarrado a ${abiertos.length} item(s) abierto(s); el servidor lo va a rechazar hasta que lo desamarres. ¿Intentar de todos modos?`
                : `¿Borrar «${a.nombre}»? El archivo se conserva 30 días por si hay que recuperarlo.`;
            if (!window.confirm(txt)) return;
            borrando.value = a.id;
            try {
                const { data } = await axios.delete(`/api/roadmap/adjuntos/${a.id}`);
                mostrarAviso(data.mensaje);
                await cargar();
                emit('cambio');
            } catch (e) { error.value = msgDe(e, 'No se pudo borrar.'); }
            finally { borrando.value = null; }
        }

        function reemplazar(nuevo) {
            const i = adjuntos.value.findIndex(x => x.id === nuevo.id);
            if (i >= 0) adjuntos.value.splice(i, 1, nuevo); else adjuntos.value.unshift(nuevo);
        }
        function mostrarAviso(t) {
            aviso.value = t;
            clearTimeout(avisoTimer);
            avisoTimer = setTimeout(() => { aviso.value = ''; }, 4000);
        }
        function msgDe(e, fallback) {
            const d = e?.response?.data || {};
            if (d.mensaje) return d.mensaje;
            if (d.errors) return Object.values(d.errors).flat()[0] || fallback;
            if (e?.response?.status === 403) return 'no tienes permiso para esta acción.';
            if (e?.response?.status === 413) return 'el servidor rechazó la petición por tamaño (límite del servidor web).';
            return d.message || fallback;
        }
        function urlDescarga(a, inline = false) {
            return `/api/roadmap/adjuntos/${a.id}/descargar${inline && a.previsualizable ? '?inline=1' : ''}`;
        }
        function tipoDe(a) {
            if (a.es_imagen) return 'imagen';
            if (a.mime === 'application/pdf') return 'pdf';
            if (['html', 'htm'].includes(a.extension)) return 'html';
            if (['md', 'txt', 'csv', 'json'].includes(a.extension)) return 'texto';
            if (a.extension === 'xlsx') return 'hoja';
            if (a.extension === 'docx') return 'doc';
            if (a.extension === 'zip') return 'zip';
            return 'otro';
        }
        function iconoDe(a) { return ICONOS[tipoDe(a)] || ICONOS.otro; }
        function fmtBytes(n) {
            if (n == null) return '—';
            if (n >= 1048576) return (n / 1048576).toFixed(n >= 10485760 ? 0 : 1) + ' MB';
            if (n >= 1024) return Math.round(n / 1024) + ' KB';
            return n + ' B';
        }
        function hace(iso) {
            if (!iso) return '';
            const s = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
            if (s < 60) return 'hace un momento';
            if (s < 3600) return `hace ${Math.floor(s / 60)} min`;
            if (s < 86400) return `hace ${Math.floor(s / 3600)} h`;
            return `hace ${Math.floor(s / 86400)} d`;
        }
        function recorta(t, n) { return t && t.length > n ? t.slice(0, n - 1) + '…' : (t || ''); }

        onMounted(() => {
            // Colapsado por defecto: solo se pide el conteo ligero para la cabecera.
            axios.get('/api/roadmap/adjuntos').then(({ data }) => {
                adjuntos.value = data.adjuntos || [];
                Object.assign(limites, data.limites || {});
                Object.assign(puede, data.puede || {});
            }).catch(() => {});
        });

        return {
            abierto, cargando, adjuntos, limites, puede, error, aviso, filtro, verBorrados, arrastrando, subiendo, progreso, progresoTxt,
            descripcion, qItemSubida, itemsSubida, amarrando, qAmarre, borrando, sugerencias, total, acceptAttr, listaFiltrada,
            toggle, cargar, onDrop, onSeleccion, buscarItems, elegirItemSubida, abrirAmarre, amarrar, desamarrar, borrar,
            urlDescarga, tipoDe, iconoDe, fmtBytes, hace, recorta,
        };
    },
};
</script>

<style scoped>
/* Paleta: SOLO los tokens --tc-* que define TorreControl (claro y oscuro) — sin tintas propias. */
.ad-wrap{margin:12px 0;border:1px solid var(--tc-line);border-radius:12px;background:var(--tc-surface);color:var(--tc-ink);}
.ad-head{display:flex;align-items:center;gap:10px;width:100%;padding:9px 14px;background:transparent;border:0;color:inherit;font:inherit;text-align:left;cursor:pointer;border-radius:12px;}
.ad-head:hover{background:var(--tc-bg2);}
.ad-caret{width:12px;color:var(--tc-muted);}
.ad-title{font-size:12.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--tc-muted);}
.ad-count{font-size:12px;font-weight:700;min-width:22px;text-align:center;padding:1px 7px;border-radius:999px;background:var(--tc-line);color:var(--tc-ink);}
.ad-hint{font-size:12px;color:var(--tc-muted);margin-left:auto;}
.ad-body{padding:0 14px 14px;border-top:1px solid var(--tc-line);}
.ad-nota{font-size:12.5px;color:var(--tc-muted);margin:10px 0 0;}
.ad-drop{margin-top:12px;padding:14px;border:2px dashed var(--tc-line);border-radius:12px;background:var(--tc-bg2);transition:border-color .15s,background .15s;}
.ad-drop-over{border-color:var(--tc-accent);background:var(--tc-surface);}
.ad-drop-busy{opacity:.85;}
.ad-input{display:none;}
.ad-drop-txt{font-size:13.5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ad-drop-meta{font-size:11.5px;color:var(--tc-muted);margin-top:4px;}
.ad-campos{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:flex-start;}
.ad-text{flex:1 1 280px;font-size:12.5px;padding:6px 9px;border:1px solid var(--tc-line);border-radius:8px;background:var(--tc-surface);color:var(--tc-ink);}
.ad-text-sm{flex:1 1 220px;font-size:12px;padding:5px 8px;}
.ad-text::placeholder{color:var(--tc-muted);}
.ad-amarre-inline{position:relative;display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1 1 260px;}
.ad-sug{position:absolute;top:100%;left:0;z-index:20;margin:2px 0 0;padding:4px 0;list-style:none;min-width:280px;max-width:520px;max-height:220px;overflow:auto;background:var(--tc-surface);border:1px solid var(--tc-line);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.15);}
.ad-sug li button{display:block;width:100%;text-align:left;background:transparent;border:0;color:var(--tc-ink);font-size:12.5px;padding:6px 10px;cursor:pointer;}
.ad-sug li button:hover{background:var(--tc-bg2);}
.ad-progress{display:flex;align-items:center;gap:10px;margin-top:10px;}
.ad-progress-track{flex:1 1 auto;height:10px;border-radius:5px;background:var(--tc-line);overflow:hidden;}
.ad-progress-bar{height:100%;background:var(--tc-accent);transition:width .15s;}
.ad-progress-txt{flex:0 0 auto;font-size:11.5px;color:var(--tc-ink);font-weight:600;max-width:55%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
/* Error y aviso: tinta de token sobre superficie (≥ 4.5:1 en ambos temas) + borde del mismo token. */
.ad-error{margin-top:10px;padding:9px 12px;border-radius:9px;font-size:12.8px;line-height:1.45;border:1px solid var(--tc-bad);color:var(--tc-bad);background:var(--tc-surface);}
.ad-ok{margin-top:10px;padding:8px 12px;border-radius:9px;font-size:12.5px;border:1px solid var(--tc-accent);color:var(--tc-accent-ink,#0f766e);background:var(--tc-surface);}
.tc-dark .ad-ok{color:var(--tc-accent);}
.ad-link{background:none;border:0;color:inherit;text-decoration:underline;cursor:pointer;font-size:12px;margin-left:8px;}
.ad-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;}
.ad-check{font-size:12px;color:var(--tc-muted);display:flex;align-items:center;gap:5px;}
.ad-vacio{padding:16px 4px;font-size:12.8px;color:var(--tc-muted);}
.ad-lista{list-style:none;margin:10px 0 0;padding:0;display:flex;flex-direction:column;gap:8px;}
.ad-item{display:grid;grid-template-columns:56px 1fr auto;gap:12px;align-items:start;padding:10px;border:1px solid var(--tc-line);border-radius:10px;background:var(--tc-surface);}
.ad-item-borrado{opacity:.6;}
.ad-thumb{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:8px;background:var(--tc-bg2);border:1px solid var(--tc-line);overflow:hidden;text-decoration:none;}
.ad-thumb img{width:100%;height:100%;object-fit:cover;}
.ad-ico{font-size:24px;line-height:1;}
.ad-info{min-width:0;}
.ad-nombre-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ad-nombre{font-size:13.5px;font-weight:600;color:var(--tc-ink);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;}
.ad-nombre:hover{text-decoration:underline;}
.ad-tipo{font-size:10.5px;font-weight:700;letter-spacing:.04em;color:var(--tc-muted);border:1px solid var(--tc-line);border-radius:5px;padding:0 5px;}
.ad-tag{font-size:10.5px;font-weight:700;color:var(--tc-muted);background:var(--tc-line);border-radius:5px;padding:1px 6px;}
.ad-tag-bad{color:var(--tc-bad);background:transparent;border:1px solid var(--tc-bad);}
.ad-desc{font-size:12.5px;margin-top:2px;}
.ad-meta{font-size:11.5px;color:var(--tc-muted);margin-top:2px;}
.ad-items{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px;}
.ad-suelto{font-size:11.5px;color:var(--tc-muted);font-style:italic;}
.ad-chip{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;color:var(--tc-ink);background:var(--tc-line);border-radius:6px;padding:1px 7px;max-width:100%;}
.ad-chip a{color:var(--tc-info);text-decoration:none;}
.ad-chip-x{border:0;background:transparent;color:var(--tc-muted);cursor:pointer;font-size:11px;padding:0 2px;}
.ad-chip-x:hover{color:var(--tc-bad);}
.ad-acciones{display:flex;flex-direction:column;gap:6px;}
.ad-btn{font-size:12px;font-weight:600;padding:6px 10px;border-radius:8px;border:1px solid var(--tc-line);background:var(--tc-bg2);color:var(--tc-ink);cursor:pointer;text-decoration:none;text-align:center;white-space:nowrap;}
.ad-btn:hover{border-color:var(--tc-accent);}
.ad-btn:disabled{opacity:.6;cursor:default;}
.ad-btn-primario{border-color:var(--tc-accent);color:var(--tc-accent-ink,#0f766e);}
.tc-dark .ad-btn-primario{color:var(--tc-accent);}
.ad-btn-peligro{color:var(--tc-bad);}
.ad-btn-peligro:hover{border-color:var(--tc-bad);}
/* Teléfono (punto 11 / V10): una columna, acciones en fila y a ancho completo. */
@media (max-width:720px){
  .ad-hint{display:none;}
  .ad-item{grid-template-columns:44px 1fr;}
  .ad-thumb{width:44px;height:44px;}
  .ad-acciones{grid-column:1 / -1;flex-direction:row;flex-wrap:wrap;}
  .ad-acciones .ad-btn{flex:1 1 auto;}
  .ad-text,.ad-text-sm{flex-basis:100%;}
}
@media (prefers-reduced-motion:reduce){
  .ad-drop,.ad-progress-bar{transition:none;}
}
</style>
