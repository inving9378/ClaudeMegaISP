<template>
    <div class="td" :class="{ 'td-dark': dark, 'td-full': fullscreen }">

        <!-- Barra: resumen + controles -->
        <div class="td-bar">
            <div class="td-sum">
                <span class="td-count">{{ archivos.length }}</span>
                {{ archivos.length === 1 ? 'archivo' : 'archivos' }}
                <span class="td-add">+{{ totales.add }}</span>
                <span class="td-del">−{{ totales.del }}</span>
            </div>
            <div class="td-ctrls">
                <div class="td-seg" role="group" aria-label="Modo de vista">
                    <button :class="{ on: modo === 'unificado' }" @click="modo = 'unificado'">Unificado</button>
                    <button :class="{ on: modo === 'dividido' }" @click="modo = 'dividido'">Dividido</button>
                </div>
                <button class="td-btn" @click="todos(false)" title="Colapsar todos los archivos">Colapsar</button>
                <button class="td-btn" @click="todos(true)" title="Expandir todos los archivos">Expandir</button>
                <button class="td-btn" @click="$emit('fullscreen')" :title="fullscreen ? 'Salir de pantalla completa' : 'Ver a pantalla completa'">
                    {{ fullscreen ? '⤡ Reducir' : '⤢ Ampliar' }}
                </button>
            </div>
        </div>

        <p v-if="truncado" class="td-trunc">
            ⚠ Diff recortado: se muestran los primeros {{ kb(diff.length) }} de {{ kb(bytes) }}.
            Para revisarlo completo: <code>git diff main...{{ branch }}</code>
        </p>

        <p v-if="!archivos.length" class="td-vacio">Sin cambios que mostrar.</p>

        <!-- Un bloque por archivo -->
        <div v-for="(f, i) in archivos" :key="i" class="td-file">
            <button class="td-fhead" @click="abierto[i] = !abierto[i]" :aria-expanded="abierto[i] ? 'true' : 'false'">
                <span class="td-caret">{{ abierto[i] ? '▾' : '▸' }}</span>
                <span class="td-estado" :class="'td-e-' + f.estado">{{ etiqueta(f.estado) }}</span>
                <span class="td-path">{{ f.path }}</span>
                <span class="td-fnums">
                    <span v-if="f.add" class="td-add">+{{ f.add }}</span>
                    <span v-if="f.del" class="td-del">−{{ f.del }}</span>
                </span>
            </button>

            <div v-show="abierto[i]" class="td-body">
                <p v-if="f.binario" class="td-bin">Archivo binario — sin diff de texto.</p>

                <!-- UNIFICADO -->
                <table v-else-if="modo === 'unificado'" class="td-tbl">
                    <tbody>
                        <template v-for="(h, hi) in f.hunks" :key="hi">
                            <tr class="td-hunk">
                                <td class="td-ln" colspan="2">…</td>
                                <td class="td-code">{{ h.cabecera }}</td>
                            </tr>
                            <tr v-for="(l, li) in h.lineas" :key="li" :class="'td-' + l.tipo">
                                <td class="td-ln">{{ l.viejo || '' }}</td>
                                <td class="td-ln">{{ l.nuevo || '' }}</td>
                                <td class="td-code"><span class="td-sig">{{ signo(l.tipo) }}</span>{{ l.texto }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <!-- DIVIDIDO -->
                <table v-else class="td-tbl td-split">
                    <tbody>
                        <template v-for="(h, hi) in f.hunks" :key="hi">
                            <tr class="td-hunk">
                                <td class="td-ln">…</td><td class="td-code">{{ h.cabecera }}</td>
                                <td class="td-ln">…</td><td class="td-code"></td>
                            </tr>
                            <tr v-for="(p, pi) in pares(h)" :key="pi">
                                <td class="td-ln" :class="p.izq ? 'td-' + p.izq.tipo : 'td-nulo'">{{ p.izq ? p.izq.viejo : '' }}</td>
                                <td class="td-code" :class="p.izq ? 'td-' + p.izq.tipo : 'td-nulo'">{{ p.izq ? p.izq.texto : '' }}</td>
                                <td class="td-ln" :class="p.der ? 'td-' + p.der.tipo : 'td-nulo'">{{ p.der ? p.der.nuevo : '' }}</td>
                                <td class="td-code" :class="p.der ? 'td-' + p.der.tipo : 'td-nulo'">{{ p.der ? p.der.texto : '' }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * VISOR DE DIFF estilo GitHub para la pestaña Integración de la Torre.
 *
 * POR QUÉ ESTE COMPONENTE Y NO UN IFRAME DE GITHUB (que fue lo que se pidió):
 * las ramas del circuito **sólo existen en este box**. El circuito jamás hace push
 * (`circuito:integrar` → "Nunca push ni prod"), así que no hay URL de GitHub que
 * embeber: el diff de `circuito/item-N-…` no existe fuera de aquí. Lo que sí se
 * puede —y es lo que hace esto— es hablar el MISMO idioma visual: un bloque por
 * archivo, números de línea de los dos lados, verde/rojo por línea y vista
 * unificada o dividida.
 *
 * Parsea el formato `git diff` unificado en el cliente. Sin librerías: el formato
 * es estable y la alternativa era meter un paquete de npm para leer texto plano.
 *
 * NO hay resaltado de sintaxis a propósito. Lo que hace legible un diff es el
 * canal (qué archivo), el ancla (qué línea) y el signo (qué cambió) — no los
 * colores del lenguaje; y colorear mal 40 lenguajes distintos estorba más de lo
 * que ayuda.
 */
import { ref, reactive, computed, watch } from 'vue';

export default {
    name: 'TorreDiff',
    props: {
        diff:       { type: String, default: '' },
        branch:     { type: String, default: '' },
        bytes:      { type: Number, default: 0 },
        truncado:   { type: Boolean, default: false },
        dark:       { type: Boolean, default: false },
        fullscreen: { type: Boolean, default: false },
    },
    emits: ['fullscreen'],
    setup(props) {
        const modo = ref('unificado');
        const abierto = reactive({});

        /** `git diff` unificado → [{path, estado, binario, add, del, hunks:[{cabecera, lineas}]}] */
        const archivos = computed(() => {
            const out = [];
            if (!props.diff) return out;

            let f = null;
            let h = null;
            let viejo = 0;
            let nuevo = 0;

            for (const raw of props.diff.split('\n')) {
                if (raw.startsWith('diff --git ')) {
                    f = { path: rutaDe(raw), estado: 'mod', binario: false, add: 0, del: 0, hunks: [] };
                    out.push(f);
                    h = null;
                    continue;
                }
                if (!f) continue;

                if (raw.startsWith('new file')) { f.estado = 'add'; continue; }
                if (raw.startsWith('deleted file')) { f.estado = 'del'; continue; }
                if (raw.startsWith('rename ')) { f.estado = 'ren'; continue; }
                if (raw.startsWith('Binary files')) { f.binario = true; continue; }
                // Cabeceras de git que no son contenido: no deben pintarse como líneas.
                if (raw.startsWith('index ') || raw.startsWith('--- ') || raw.startsWith('+++ ')
                    || raw.startsWith('old mode') || raw.startsWith('new mode')
                    || raw.startsWith('similarity ')) continue;

                if (raw.startsWith('@@')) {
                    const m = raw.match(/@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
                    viejo = m ? parseInt(m[1], 10) : 0;
                    nuevo = m ? parseInt(m[2], 10) : 0;
                    h = { cabecera: raw, lineas: [] };
                    f.hunks.push(h);
                    continue;
                }
                if (!h) continue;

                if (raw.startsWith('+')) {
                    h.lineas.push({ tipo: 'add', viejo: '', nuevo: nuevo++, texto: raw.slice(1) });
                    f.add++;
                } else if (raw.startsWith('-')) {
                    h.lineas.push({ tipo: 'del', viejo: viejo++, nuevo: '', texto: raw.slice(1) });
                    f.del++;
                } else if (raw.startsWith('\\')) {
                    // "\ No newline at end of file": es una nota de git, no una línea del archivo.
                    continue;
                } else {
                    h.lineas.push({ tipo: 'ctx', viejo: viejo++, nuevo: nuevo++, texto: raw.slice(1) });
                }
            }
            return out;
        });

        const totales = computed(() => archivos.value.reduce(
            (a, f) => ({ add: a.add + f.add, del: a.del + f.del }), { add: 0, del: 0 }
        ));

        // Los archivos arrancan abiertos: se abrió el visor para LEER el diff, no para
        // volver a hacer clic. Con más de 12 archivos se colapsan, que ahí sí estorba.
        watch(archivos, (lista) => {
            const abrirTodo = lista.length <= 12;
            lista.forEach((_, i) => { abierto[i] = abrirTodo; });
        }, { immediate: true });

        function rutaDe(linea) {
            // `diff --git a/ruta b/ruta` — se toma el lado `b/` (el destino, que es el que importa
            // en un rename) y se cae al `a/` si no hubiera.
            const m = linea.match(/ b\/(.+)$/);
            if (m) return m[1];
            const a = linea.match(/ a\/(.+?) b\//);
            return a ? a[1] : linea.replace('diff --git ', '');
        }

        /** Empareja borradas con añadidas para la vista dividida (izquierda = antes, derecha = después). */
        function pares(hunk) {
            const out = [];
            let buf = [];
            const volcar = () => {
                const dels = buf.filter((l) => l.tipo === 'del');
                const adds = buf.filter((l) => l.tipo === 'add');
                for (let i = 0; i < Math.max(dels.length, adds.length); i++) {
                    out.push({ izq: dels[i] || null, der: adds[i] || null });
                }
                buf = [];
            };
            for (const l of hunk.lineas) {
                if (l.tipo === 'ctx') { volcar(); out.push({ izq: l, der: l }); }
                else buf.push(l);
            }
            volcar();
            return out;
        }

        const signo = (t) => (t === 'add' ? '+' : t === 'del' ? '−' : ' ');
        const etiqueta = (e) => ({ add: 'nuevo', del: 'borrado', ren: 'movido', mod: 'editado' }[e] || 'editado');
        const kb = (n) => (n > 1024 ? `${Math.round(n / 1024)} KB` : `${n} B`);
        const todos = (v) => archivos.value.forEach((_, i) => { abierto[i] = v; });

        return { modo, abierto, archivos, totales, pares, signo, etiqueta, kb, todos };
    },
};
</script>

<style scoped>
/* Paleta propia del visor: sigue a GitHub en el lenguaje (verde añade, rojo quita,
   canaleta de números apagada) sin copiar su marca. */
.td{
    --td-bg:#ffffff; --td-line:#d8dee4; --td-ink:#1f2328; --td-mut:#636c76;
    --td-gut:#f6f8fa; --td-add-bg:#e6ffec; --td-add-ink:#0a5122;
    --td-del-bg:#ffebe9; --td-del-ink:#82071e; --td-hunk:#ddf4ff; --td-hunk-ink:#0550ae;
    --td-nulo:#f6f8fa;
    border:1px solid var(--td-line); border-radius:8px; background:var(--td-bg);
    color:var(--td-ink); overflow:hidden; margin-top:8px;
}
.td-dark{
    --td-bg:#0d1117; --td-line:#30363d; --td-ink:#e6edf3; --td-mut:#8b949e;
    --td-gut:#161b22; --td-add-bg:#12261e; --td-add-ink:#3fb950;
    --td-del-bg:#25171c; --td-del-ink:#f85149; --td-hunk:#121d2f; --td-hunk-ink:#58a6ff;
    --td-nulo:#0b0f14;
}
.td-full{
    position:fixed; inset:24px; z-index:10050; margin:0;
    display:flex; flex-direction:column; box-shadow:0 24px 64px rgba(0,0,0,.45);
}
.td-full .td-file:last-child{margin-bottom:0;}
.td-full > .td-file, .td-full > .td-vacio{overflow:auto;}

.td-bar{
    display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between;
    padding:8px 10px; background:var(--td-gut); border-bottom:1px solid var(--td-line);
}
.td-sum{font-size:12px; color:var(--td-mut); display:flex; gap:6px; align-items:baseline;}
.td-count{font-weight:700; color:var(--td-ink);}
.td-add{color:#1a7f37; font-weight:600;}
.td-del{color:#cf222e; font-weight:600;}
.td-dark .td-add{color:#3fb950;} .td-dark .td-del{color:#f85149;}

.td-ctrls{display:flex; gap:6px; align-items:center;}
.td-seg{display:inline-flex; border:1px solid var(--td-line); border-radius:6px; overflow:hidden;}
.td-seg button{
    font-size:11.5px; padding:3px 9px; border:none; background:var(--td-bg);
    color:var(--td-mut); cursor:pointer;
}
.td-seg button.on{background:#0969da; color:#fff; font-weight:600;}
.td-dark .td-seg button.on{background:#1f6feb;}
.td-btn{
    font-size:11.5px; padding:3px 9px; border:1px solid var(--td-line); border-radius:6px;
    background:var(--td-bg); color:var(--td-ink); cursor:pointer;
}
.td-btn:hover{background:var(--td-gut);}
.td-seg button:focus-visible, .td-btn:focus-visible, .td-fhead:focus-visible{outline:2px solid #0969da; outline-offset:1px;}

.td-trunc{margin:0; padding:7px 10px; font-size:11.5px; background:#fff8c5; color:#7d4e00; border-bottom:1px solid var(--td-line);}
.td-dark .td-trunc{background:#2b2413; color:#d29922;}
.td-trunc code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
.td-vacio{margin:0; padding:14px; font-size:12px; color:var(--td-mut);}

.td-file + .td-file{border-top:1px solid var(--td-line);}
.td-fhead{
    width:100%; display:flex; gap:8px; align-items:center; text-align:left;
    padding:7px 10px; background:var(--td-gut); border:none; cursor:pointer;
    font-size:12px; color:var(--td-ink);
}
.td-fhead:hover{filter:brightness(.98);}
.td-caret{color:var(--td-mut); width:10px;}
.td-path{
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11.5px;
    font-weight:600; overflow-wrap:anywhere; flex:1;
}
.td-fnums{display:flex; gap:6px; font-size:11px; white-space:nowrap;}
.td-estado{
    font-size:9.5px; text-transform:uppercase; letter-spacing:.05em; padding:1px 5px;
    border-radius:4px; border:1px solid currentColor; white-space:nowrap;
}
.td-e-add{color:#1a7f37;} .td-e-del{color:#cf222e;} .td-e-ren{color:#9a6700;} .td-e-mod{color:var(--td-mut);}

.td-body{overflow-x:auto;}
.td-bin{margin:0; padding:12px; font-size:12px; color:var(--td-mut);}

.td-tbl{
    width:100%; border-collapse:collapse;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    font-size:11.5px; line-height:1.55;
}
.td-ln{
    width:1%; min-width:40px; padding:0 8px; text-align:right; vertical-align:top;
    color:var(--td-mut); background:var(--td-gut); border-right:1px solid var(--td-line);
    user-select:none; font-variant-numeric:tabular-nums; white-space:nowrap;
}
.td-code{padding:0 10px; white-space:pre-wrap; overflow-wrap:anywhere; vertical-align:top;}
.td-sig{display:inline-block; width:1ch; color:var(--td-mut);}

tr.td-add .td-code{background:var(--td-add-bg);} tr.td-add .td-sig{color:var(--td-add-ink);}
tr.td-del .td-code{background:var(--td-del-bg);} tr.td-del .td-sig{color:var(--td-del-ink);}
tr.td-hunk .td-code, tr.td-hunk .td-ln{background:var(--td-hunk); color:var(--td-hunk-ink); font-size:11px;}

/* Dividido: las clases van por celda, no por fila. */
.td-split .td-code.td-add{background:var(--td-add-bg);}
.td-split .td-code.td-del{background:var(--td-del-bg);}
.td-split .td-nulo{background:var(--td-nulo);}
.td-split .td-code{width:49%;}
.td-split .td-ln + .td-code{border-right:1px solid var(--td-line);}
</style>
