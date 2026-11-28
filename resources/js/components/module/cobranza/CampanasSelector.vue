<template>
    <!-- Selector con búsqueda (28-nov, pedido de Irving: la zona y la caja eran un <select> de
         cientos de opciones sin buscar). Patrón combobox de ARIA 1.2: el botón abre una lista
         y el foco pasa al buscador; ↑/↓ recorren, Enter elige, Esc cierra solo este selector. -->
    <div ref="raiz" class="cs" :class="{ 'cs--abierto': abierto, 'cs--arriba': haciaArriba, 'cs--derecha': alinear === 'derecha' }"
         @keydown="tecla" @focusout="alSalir">
        <button :id="id" ref="boton" type="button" class="cp-campo cs-boton" :disabled="disabled"
                aria-haspopup="listbox" :aria-expanded="abierto ? 'true' : 'false'" :aria-controls="abierto ? idLista : null"
                :aria-labelledby="idEtiqueta ? `${idEtiqueta} ${id}-valor` : null" @mousedown.prevent @click="alternar">
            <span :id="`${id}-valor`" class="cs-valor" :class="{ 'cs-valor--todas': !seleccion }">{{ textoBoton }}</span>
            <span v-if="seleccion && seleccion.n != null" class="cs-n cp-num" aria-hidden="true">{{ n(seleccion.n) }}</span>
            <i class="fa fa-chevron-down cs-flecha" aria-hidden="true"></i>
        </button>

        <transition name="cs-panel">
            <div v-if="abierto" class="cs-panel">
                <div class="cs-buscar">
                    <i class="fa fa-search cs-lupa" aria-hidden="true"></i>
                    <input ref="buscar" v-model="q" type="text" class="cs-buscar-campo" role="combobox" aria-autocomplete="list"
                           aria-expanded="true" :aria-controls="idLista" :aria-activedescendant="idActivo"
                           :aria-label="buscarEtiqueta" :placeholder="buscarEtiqueta" autocomplete="off" spellcheck="false">
                    <button v-if="q" type="button" class="cs-limpiar" aria-label="Borrar la búsqueda" @mousedown.prevent @click="limpiar">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div :id="idLista" ref="lista" class="cs-lista" role="listbox" :aria-label="buscarEtiqueta">
                    <template v-for="g in visibles" :key="g.clave">
                        <div v-if="g.titulo" :id="`${id}-g-${g.clave}`" class="cs-grupo" role="presentation">{{ g.titulo }}</div>
                        <div role="group" :aria-labelledby="g.titulo ? `${id}-g-${g.clave}` : null">
                            <div v-for="o in g.opciones" :id="idOpcion(o)" :key="o.indice" role="option" class="cs-op"
                                 :aria-selected="o.valor === (modelValue || null) ? 'true' : 'false'"
                                 :class="{ 'cs-op--activa': o.indice === activo, 'cs-op--elegida': o.valor === (modelValue || null), 'cs-op--todas': o.valor === null }"
                                 @mousedown.prevent @mousemove="activo = o.indice" @click="elegir(o.valor)">
                                <span class="cs-op-texto">
                                    <span class="cs-op-nombre"><template v-for="(p, i) in partes(o.etiqueta)" :key="i"><mark v-if="p.m" class="cs-marca">{{ p.t }}</mark><template v-else>{{ p.t }}</template></template></span>
                                    <span v-if="o.detalle" class="cs-op-detalle">{{ o.detalle }}</span>
                                </span>
                                <span v-if="o.n != null" class="cs-op-n cp-num">{{ n(o.n) }}</span>
                                <i class="fa fa-check cs-op-check" aria-hidden="true"></i>
                            </div>
                        </div>
                    </template>
                    <p v-if="!opcionesVisibles" class="cs-vacio" role="presentation">
                        {{ q ? `Nada coincide con «${q.trim()}».` : vacio }}
                    </p>
                    <!-- Una acción al final (p. ej. «Crear grupo de timbrado»); con búsqueda, la lleva como nombre. -->
                    <div v-if="accionTexto" :id="`${id}-op-accion`" role="option" aria-selected="false" class="cs-op cs-op--accion"
                         :class="{ 'cs-op--activa': activo === indiceAccion }"
                         @mousedown.prevent @mousemove="activo = indiceAccion" @click="hacerAccion">
                        <i class="fa fa-plus-circle cs-accion-icono" aria-hidden="true"></i>
                        <span class="cs-op-texto"><span class="cs-op-nombre">{{ q.trim() ? `${accionTexto} «${q.trim()}»` : accionTexto }}</span></span>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
// Para buscar sin acentos ni mayúsculas: «distrito 1» encuentra «Distrito 1», «wifi» encuentra «WiFi».
const plano = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default {
    name: 'CampanasSelector',
    props: {
        id:             { type: String, required: true },
        modelValue:     { type: String, default: null },
        // [{ titulo, opciones: [{ valor, etiqueta, n, buscar }] }]: `buscar` son palabras extra (la clave, el distrito).
        grupos:         { type: Array, default: () => [] },
        textoTodas:     { type: String, default: 'Todas' },
        buscarEtiqueta: { type: String, default: 'Buscar' },
        vacio:          { type: String, default: 'No hay opciones.' },
        disabled:       { type: Boolean, default: false },
        textoDeshabilitado: { type: String, default: '' },
        // El <label> de afuera: el botón se anuncia «Zona, Zona 54».
        idEtiqueta:     { type: String, default: null },
        alinear:        { type: String, default: 'izquierda' },
        // Texto de una acción al final de la lista (vacío = sin acción). Emite «accion» con lo buscado.
        accionTexto:    { type: String, default: '' },
    },
    emits: ['update:modelValue', 'accion'],
    data() {
        return { abierto: false, q: '', activo: 0, haciaArriba: false };
    },
    computed: {
        idLista() { return `${this.id}-lista`; },
        fichas() { return plano(this.q).split(/\s+/).filter(Boolean); },
        // Lo que se ve con la búsqueda, con un índice corrido para el teclado. «Todas» solo sin buscar.
        visibles() {
            let i = 0;
            const salida = [];
            if (!this.fichas.length) salida.push({ clave: 'todas', titulo: null, opciones: [{ valor: null, etiqueta: this.textoTodas, n: null, indice: i++ }] });
            this.grupos.forEach((g, gi) => {
                const pajar = o => plano(`${o.etiqueta} ${o.buscar || ''} ${g.titulo || ''}`);
                const opciones = g.opciones
                    .filter(o => this.fichas.every(f => pajar(o).includes(f)))
                    .map(o => ({ ...o, indice: i++ }));
                if (opciones.length) salida.push({ clave: `g${gi}`, titulo: g.titulo, opciones });
            });
            return salida;
        },
        opcionesVisibles() { return this.visibles.reduce((t, g) => t + g.opciones.length, 0); },
        indiceAccion() { return this.opcionesVisibles; },
        // Lo que se recorre con el teclado: las opciones y, al final, la acción.
        planas() {
            const lista = this.visibles.flatMap(g => g.opciones);
            if (this.accionTexto) lista.push({ accion: true, indice: this.indiceAccion });
            return lista;
        },
        seleccion() {
            if (!this.modelValue) return null;
            for (const g of this.grupos) {
                const o = g.opciones.find(x => x.valor === this.modelValue);
                if (o) return o;
            }
            return null;
        },
        textoBoton() {
            if (this.disabled && this.textoDeshabilitado) return this.textoDeshabilitado;
            return this.seleccion ? this.seleccion.etiqueta : this.textoTodas;
        },
        idActivo() {
            const o = this.planas[this.activo];
            return o ? this.idOpcion(o) : null;
        },
    },
    watch: {
        q() {
            this.activo = 0;
            this.$nextTick(() => { if (this.$refs.lista) this.$refs.lista.scrollTop = 0; });
        },
        disabled(v) { if (v) this.cerrar(false); },
    },
    methods: {
        n(x) { return Number(x || 0).toLocaleString('es-MX'); },
        idOpcion(o) { return o.accion ? `${this.id}-op-accion` : `${this.id}-op-${o.indice}`; },
        // «Zona 54 · D1» con lo buscado resaltado (cada palabra, sin acentos).
        partes(etiqueta) {
            const texto = String(etiqueta);
            if (!this.fichas.length) return [{ t: texto, m: false }];
            const base = plano(texto);
            // Solo si quitar acentos no movió las letras de lugar (así se resalta lo correcto).
            if (base.length !== texto.length) return [{ t: texto, m: false }];
            const marca = new Array(texto.length).fill(false);
            for (const f of this.fichas) {
                for (let i = base.indexOf(f); i >= 0; i = base.indexOf(f, i + f.length)) marca.fill(true, i, i + f.length);
            }
            const salida = [];
            for (let i = 0; i < texto.length; i++) {
                const ultimo = salida[salida.length - 1];
                if (ultimo && ultimo.m === marca[i]) ultimo.t += texto[i];
                else salida.push({ t: texto[i], m: marca[i] });
            }
            return salida;
        },
        alternar() {
            if (this.abierto) this.cerrar(true);
            else this.abrir();
        },
        abrir(inicial = '') {
            if (this.disabled || this.abierto) return;
            this.decidirLado();
            this.q = inicial;
            this.abierto = true;
            this.$nextTick(() => {
                const i = this.planas.findIndex(o => o.valor === (this.modelValue || null));
                this.activo = inicial ? 0 : Math.max(0, i);
                if (this.$refs.buscar) this.$refs.buscar.focus();
                this.verActivo('center');
            });
        },
        cerrar(devolverFoco) {
            if (!this.abierto) return;
            this.abierto = false;
            if (devolverFoco) this.$nextTick(() => this.$refs.boton && this.$refs.boton.focus());
        },
        hacerAccion() {
            const texto = this.q.trim();
            this.cerrar(false);
            this.$emit('accion', texto);
        },
        elegir(valor) {
            if (valor !== (this.modelValue || null)) this.$emit('update:modelValue', valor);
            this.cerrar(true);
        },
        limpiar() {
            this.q = '';
            if (this.$refs.buscar) this.$refs.buscar.focus();
        },
        // Abre hacia arriba si abajo no cabe dentro del cuerpo del diálogo (que es el que hace scroll).
        decidirLado() {
            const caja = this.$refs.boton.getBoundingClientRect();
            const marco = this.$refs.raiz.closest('.cpd-cuerpo');
            const limite = marco ? marco.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
            const abajo = limite.bottom - caja.bottom;
            this.haciaArriba = abajo < 330 && caja.top - limite.top > abajo;
        },
        mover(paso) {
            const total = this.planas.length;
            if (!total) return;
            this.activo = (this.activo + paso + total) % total;
            this.verActivo('nearest');
        },
        verActivo(bloque) {
            this.$nextTick(() => {
                const el = this.idActivo && document.getElementById(this.idActivo);
                if (el) el.scrollIntoView({ block: bloque });
            });
        },
        tecla(e) {
            if (!this.abierto) {
                if (e.target !== this.$refs.boton) return;
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.abrir();
                } else if (e.key.length === 1 && e.key !== ' ' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    // Escribir sobre el botón ya busca.
                    e.preventDefault();
                    this.abrir(e.key);
                }
                return;
            }
            if (e.key === 'ArrowDown') { e.preventDefault(); this.mover(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.mover(-1); }
            else if (e.key === 'PageDown') { e.preventDefault(); this.mover(8); }
            else if (e.key === 'PageUp') { e.preventDefault(); this.mover(-8); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                const o = this.planas[this.activo];
                if (o && o.accion) this.hacerAccion();
                else if (o) this.elegir(o.valor);
            } else if (e.key === 'Escape') {
                // Que no cierre también el diálogo.
                e.preventDefault();
                e.stopPropagation();
                this.cerrar(true);
            } else if (e.key === 'Tab') {
                this.cerrar(false);
            }
        },
        alSalir(e) {
            // $refs y no $el: con el comentario de arriba la plantilla es un fragmento y $el es texto.
            if (this.abierto && !this.$refs.raiz.contains(e.relatedTarget)) this.cerrar(false);
        },
    },
};
</script>

<style scoped>
.cs { position: relative; min-width: 0; }

/* .cs delante: el campo general (.cp-raiz .cp-campo) pesa igual y lo dejaba en bloque. */
.cs .cs-boton {
    display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; cursor: pointer;
    transition: border-color .15s, box-shadow .15s, background-color .15s;
}
.cs-boton:hover:not(:disabled) { border-color: var(--cp-acento); }
.cs-boton:disabled { cursor: not-allowed; opacity: .65; }
.cs--abierto .cs-boton { border-color: var(--cp-acento); box-shadow: 0 0 0 3px var(--cp-acento-suave); }
.cs-valor { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--cp-tinta); }
.cs-valor--todas { color: var(--cp-tenue); }
.cs-n {
    flex: none; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 600;
    background: var(--cp-acento-suave); color: var(--cp-acento);
}
.cs-flecha { flex: none; font-size: 11px; color: var(--cp-tenue); transition: transform .24s cubic-bezier(.16, 1, .3, 1); }
.cs--abierto .cs-flecha { transform: rotate(180deg); color: var(--cp-acento); }

.cs-panel {
    position: absolute; z-index: 40; top: calc(100% + 6px); left: 0; width: max(100%, 280px);
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 10px;
    box-shadow: 0 16px 32px -8px rgba(15, 23, 42, .22), 0 4px 10px -4px rgba(15, 23, 42, .12);
    transform-origin: top center;
}
.cd-dark .cs-panel { box-shadow: 0 16px 32px -8px rgba(0, 0, 0, .6), 0 4px 10px -4px rgba(0, 0, 0, .4); }
.cs--derecha .cs-panel { left: auto; right: 0; }
.cs--arriba .cs-panel { top: auto; bottom: calc(100% + 6px); transform-origin: bottom center; }

.cs-buscar { position: relative; flex: none; padding: 8px; border-bottom: 1px solid var(--cp-borde); }
.cs-lupa { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--cp-tenue); pointer-events: none; }
.cs-buscar-campo {
    width: 100%; min-height: 36px; padding: 6px 34px 6px 34px; border: 1px solid var(--cp-borde-campo); border-radius: 8px;
    background: var(--cp-superficie); color: var(--cp-tinta); font: inherit; font-size: 13.5px; caret-color: var(--cp-acento);
}
.cs-buscar-campo::placeholder { color: var(--cp-tenue); opacity: 1; }
.cs-buscar-campo:focus { outline: none; border-color: var(--cp-acento); box-shadow: 0 0 0 3px var(--cp-acento-suave); }
.cs-limpiar {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 6px;
    background: transparent; color: var(--cp-tenue); font-size: 12px; cursor: pointer;
}
.cs-limpiar:hover { background: var(--cp-hover); color: var(--cp-tinta); }

.cs-lista {
    max-height: 272px; overflow-y: auto; overscroll-behavior: contain; padding: 0 0 6px;
    scrollbar-width: thin; scrollbar-color: var(--cp-borde-campo) transparent;
}
.cs-lista::-webkit-scrollbar { width: 8px; }
.cs-lista::-webkit-scrollbar-thumb { background: var(--cp-barra); border-radius: 8px; border: 2px solid var(--cp-superficie); }
.cs-lista::-webkit-scrollbar-thumb:hover { background: var(--cp-borde-campo); }

/* El distrito se queda arriba mientras se recorren sus zonas. */
.cs-grupo {
    position: sticky; top: 0; z-index: 1; padding: 8px 14px 4px; background: var(--cp-superficie);
    font-size: 11.5px; font-weight: 600; letter-spacing: .02em; color: var(--cp-tenue);
}
.cs-op {
    display: flex; align-items: center; gap: 10px; min-height: 36px; margin: 0 6px; padding: 6px 8px 6px 10px;
    border-radius: 7px; font-size: 13.5px; color: var(--cp-tinta); cursor: pointer;
    transition: background-color .12s;
}
.cs-op--activa { background: var(--cp-hover); }
.cs-op--elegida { color: var(--cp-acento); font-weight: 600; }
.cs-op--todas { margin-top: 4px; margin-bottom: 2px; }
.cs-op-texto { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
.cs-op-nombre, .cs-op-detalle { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cs-op-detalle { margin-top: 1px; font-size: 12px; font-weight: 400; color: var(--cp-tenue); }
.cs-op--accion { margin-top: 4px; color: var(--cp-acento); font-weight: 600; box-shadow: 0 -1px 0 var(--cp-borde); border-radius: 0 0 7px 7px; }
.cs-op--accion.cs-op--activa { background: var(--cp-acento-suave); }
.cs-accion-icono { flex: none; font-size: 14px; }
.cs-op-n { flex: none; font-size: 12.5px; color: var(--cp-tenue); font-weight: 500; }
.cs-op--elegida .cs-op-n { color: var(--cp-acento); }
.cs-op-check { flex: none; width: 14px; font-size: 12px; color: var(--cp-acento); visibility: hidden; }
.cs-op--elegida .cs-op-check { visibility: visible; }
.cs-marca { padding: 0 1px; border-radius: 3px; background: var(--cp-acento-suave); color: inherit; font-weight: 700; }
.cs-vacio { margin: 0; padding: 14px 16px; font-size: 13px; color: var(--cp-tenue); }

/* La lista se despliega desde el campo: crece, se aclara y se enfoca en un solo gesto. */
@media (prefers-reduced-motion: no-preference) {
    .cs-panel-enter-active { transition: opacity .16s cubic-bezier(.16, 1, .3, 1), transform .26s cubic-bezier(.16, 1, .3, 1), clip-path .26s cubic-bezier(.16, 1, .3, 1), filter .2s ease-out; }
    .cs-panel-leave-active { transition: opacity .12s ease-in, transform .12s ease-in; }
    .cs-panel-enter-from { opacity: 0; transform: translateY(-6px) scale(.97); clip-path: inset(0 0 70% 0 round 10px); filter: blur(2px); }
    .cs--arriba .cs-panel-enter-from { transform: translateY(6px) scale(.97); clip-path: inset(70% 0 0 0 round 10px); }
    .cs-panel-leave-to { opacity: 0; transform: translateY(-4px) scale(.985); }
    .cs--arriba .cs-panel-leave-to { transform: translateY(4px) scale(.985); }
}
@media (prefers-reduced-motion: reduce) {
    .cs-panel-enter-active, .cs-panel-leave-active { transition: opacity .12s linear; }
    .cs-panel-enter-from, .cs-panel-leave-to { opacity: 0; }
    .cs-flecha { transition: none; }
}

@media (max-width: 767px) {
    .cs-panel { width: max(100%, 260px); }
    .cs-op { min-height: 44px; }
    .cs-buscar-campo { min-height: 44px; font-size: 16px; }
}
</style>
