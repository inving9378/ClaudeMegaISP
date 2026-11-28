<template>
    <!-- Nuevo grupo de timbrado sin salir de Campañas/Blaster (28-nov, pedido de Irving). Usa el alta de VoIP
         (POST /voip/grupos-timbrado): la misma validación, la cola y el plan de marcado que arma esa pantalla. -->
    <campanas-dialogo :abierto="abierto" variante="ancho" :oscuro="oscuro" titulo="Nuevo grupo de timbrado"
                      descripcion="Elige quién contesta y cómo suena. Al crearlo queda elegido para las llamadas que entran."
                      @cerrar="cerrar">
        <form class="cgn" novalidate @submit.prevent="crear">
            <div class="cgn-col">
                <fieldset class="cgn-seccion">
                    <legend class="cgn-titulo">El grupo</legend>
                    <div class="cp-campo-grupo">
                        <label for="cgn-nombre" class="cp-etiqueta">Nombre</label>
                        <input id="cgn-nombre" v-model="form.nombre" data-autofoco class="cp-campo" maxlength="100"
                               placeholder="Ej. Cobranza, Soporte técnico, Ventas" :aria-invalid="!!errores.nombre"
                               aria-describedby="cgn-nombre-error">
                        <p v-if="errores.nombre" id="cgn-nombre-error" class="cp-error">{{ errores.nombre }}</p>
                    </div>
                </fieldset>

                <fieldset class="cgn-seccion">
                    <legend class="cgn-titulo">Cómo suena</legend>
                    <div class="cgn-modos" role="radiogroup" aria-label="Cómo suena el grupo">
                        <label v-for="m in MODOS" :key="m.id" class="cgn-modo" :class="{ 'cgn-modo--elegido': form.estrategia === m.id }">
                            <input v-model="form.estrategia" type="radio" name="cgn-estrategia" :value="m.id" class="cgn-oculto">
                            <span class="cgn-modo-icono" aria-hidden="true"><i class="fa" :class="m.icono"></i></span>
                            <span class="cgn-modo-texto">
                                <strong>{{ m.titulo }}</strong>
                                <span>{{ m.ayuda }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="cp-campo-grupo cgn-tiempo">
                        <label for="cgn-tiempo" class="cp-etiqueta">
                            {{ form.estrategia === 'ringall' ? 'Cuánto suena' : 'Cuánto suena cada extensión' }}
                            <strong class="cgn-valor cp-num">{{ form.ring_time }} s</strong>
                        </label>
                        <input id="cgn-tiempo" v-model.number="form.ring_time" type="range" min="5" max="120" step="5" class="cgn-rango"
                               :aria-valuetext="`${form.ring_time} segundos`">
                        <div class="cgn-rango-extremos" aria-hidden="true"><span>5 s</span><span>2 min</span></div>
                    </div>

                    <div class="cp-campo-grupo">
                        <span id="cgn-fallback-etiqueta" class="cp-etiqueta">Si nadie contesta</span>
                        <div class="cgn-segmentos" role="radiogroup" aria-labelledby="cgn-fallback-etiqueta">
                            <label v-for="f in FALLBACK" :key="f.id" class="cgn-segmento" :class="{ 'cgn-segmento--elegido': form.destino_fallback === f.id }">
                                <input v-model="form.destino_fallback" type="radio" name="cgn-fallback" :value="f.id" class="cgn-oculto">
                                <i class="fa" :class="f.icono" aria-hidden="true"></i>{{ f.titulo }}
                            </label>
                        </div>
                    </div>

                    <label class="cgn-interruptor">
                        <input v-model="form.es_cola" type="checkbox" role="switch">
                        <span class="cgn-interruptor-pista" aria-hidden="true"><span></span></span>
                        <span class="cgn-interruptor-texto">
                            <strong>Cola con espera</strong>
                            <span>Contesta primero, avisa que espere y solo pasa la llamada cuando alguien está libre.</span>
                        </span>
                    </label>
                </fieldset>
            </div>

            <fieldset class="cgn-seccion cgn-seccion--quien">
                <legend class="cgn-titulo">
                    Quién contesta
                    <span class="cgn-cuenta cp-num">{{ elegidas.length }} {{ elegidas.length === 1 ? 'elegida' : 'elegidas' }}</span>
                </legend>
                <div class="cgn-buscar-fila">
                    <label class="cgn-buscar">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <input v-model="buscar" type="search" class="cp-campo" placeholder="Buscar extensión por número o nombre"
                               aria-label="Buscar extensión" autocomplete="off">
                    </label>
                    <button v-if="visibles.length" type="button" class="cgn-enlace" @click="elegirVisibles(!todasVisiblesElegidas)">
                        {{ todasVisiblesElegidas ? 'Quitar las que se ven' : `Elegir las ${visibles.length} que se ven` }}
                    </button>
                </div>
                <p v-if="ordenImporta && elegidas.length > 1" class="cgn-ayuda">
                    <i class="fa fa-list-ol" aria-hidden="true"></i> Suenan en el orden en que las eliges: el número de la izquierda.
                </p>

                <p v-if="errorCarga" class="cgn-aviso" role="alert">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    <span>{{ errorCarga }} <button type="button" class="cgn-enlace" @click="cargar">Reintentar</button></span>
                </p>
                <ul v-else class="cgn-extensiones" :aria-busy="cargando">
                    <template v-if="cargando">
                        <li v-for="i in 6" :key="`e${i}`" class="cgn-ext cgn-ext--esqueleto" aria-hidden="true"><span class="cgn-esqueleto"></span></li>
                    </template>
                    <li v-else-if="!extensiones.length" class="cgn-vacio">
                        <strong>No hay extensiones</strong>
                        <span>Da de alta extensiones en VoIP → Extensiones y vuelve aquí.</span>
                    </li>
                    <li v-else-if="!visibles.length" class="cgn-vacio">
                        <strong>Ninguna extensión coincide con «{{ buscar.trim() }}»</strong>
                    </li>
                    <li v-for="ext in visibles" v-else :key="ext.id">
                        <label class="cgn-ext" :class="{ 'cgn-ext--elegida': posicion(ext.id) }">
                            <input type="checkbox" :checked="!!posicion(ext.id)" @change="alternar(ext.id)">
                            <span class="cgn-orden cp-num" aria-hidden="true">{{ posicion(ext.id) || '' }}</span>
                            <span class="cgn-ext-numero cp-num">{{ ext.numero }}</span>
                            <span class="cgn-ext-nombre">{{ ext.nombre || 'Sin nombre' }}</span>
                            <span v-if="conectadas" class="cgn-ext-estado" :class="{ 'cgn-ext-estado--si': conectadas.has(String(ext.numero)) }">
                                <span class="cgn-punto" aria-hidden="true"></span>{{ conectadas.has(String(ext.numero)) ? 'Conectada' : 'Sin conectar' }}
                            </span>
                        </label>
                    </li>
                </ul>
                <p v-if="errores.miembros" class="cp-error">{{ errores.miembros }}</p>
            </fieldset>
        </form>

        <template #pie>
            <p v-if="errores.general" class="cgn-pie-error" role="alert">{{ errores.general }}</p>
            <span v-else-if="!puedeCrear && !creando" class="cgn-pie-ayuda">{{ faltante }}</span>
            <button type="button" class="cgn-boton" @click="cerrar">Cancelar</button>
            <button type="button" class="cgn-boton cgn-boton--primario" :disabled="!puedeCrear || creando" @click="crear">
                <i class="fa" :class="creando ? 'fa-spinner fa-spin' : 'fa-check'" aria-hidden="true"></i>
                {{ creando ? 'Creando…' : 'Crear grupo y usarlo' }}
            </button>
        </template>
    </campanas-dialogo>
</template>

<script>
import CampanasDialogo from './CampanasDialogo.vue';

const MODOS = [
    { id: 'ringall', titulo: 'Todas a la vez', ayuda: 'Suenan todas y contesta quien llegue primero.', icono: 'fa-users' },
    { id: 'hunt', titulo: 'Una por una, en orden', ayuda: 'Suena la primera; si no contesta, la siguiente.', icono: 'fa-list-ol' },
    { id: 'memoryhunt', titulo: 'Una por una, rotando', ayuda: 'En orden, pero empieza después de la que contestó la última vez.', icono: 'fa-sync-alt' },
];
const FALLBACK = [
    { id: 'colgar', titulo: 'Colgar', icono: 'fa-phone-slash' },
    { id: 'buzon', titulo: 'Buzón de voz', icono: 'fa-voicemail' },
    { id: 'repetir', titulo: 'Volver a sonar', icono: 'fa-redo' },
];
const plano = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const vacio = (nombre = '') => ({ nombre, estrategia: 'ringall', ring_time: 20, destino_fallback: 'colgar', es_cola: false });

export default {
    name: 'CampanasGrupoNuevo',
    components: { CampanasDialogo },
    props: {
        abierto:       { type: Boolean, default: false },
        voipUrl:       { type: String, required: true },
        csrfToken:     { type: String, default: '' },
        oscuro:        { type: Boolean, default: false },
        // Lo que se buscó en el selector: el nombre con el que arranca.
        nombreInicial: { type: String, default: '' },
    },
    emits: ['cerrar', 'creado'],
    setup() {
        return { MODOS, FALLBACK };
    },
    data() {
        return {
            form: vacio(),
            elegidas: [],          // ids de extensión, en el orden en que se eligieron
            extensiones: [],
            conectadas: null,      // Set de números conectados (null = no se supo)
            buscar: '',
            cargando: false,
            errorCarga: '',
            creando: false,
            errores: {},
        };
    },
    computed: {
        visibles() {
            const q = plano(this.buscar.trim());
            if (!q) return this.extensiones;
            return this.extensiones.filter(e => plano(`${e.numero} ${e.nombre}`).includes(q));
        },
        todasVisiblesElegidas() {
            return this.visibles.length > 0 && this.visibles.every(e => this.elegidas.includes(e.id));
        },
        ordenImporta() {
            return this.form.estrategia !== 'ringall';
        },
        puedeCrear() {
            return this.form.nombre.trim().length > 0 && this.elegidas.length > 0;
        },
        faltante() {
            if (!this.form.nombre.trim()) return 'Escribe el nombre del grupo.';
            if (!this.elegidas.length) return 'Elige al menos una extensión.';
            return '';
        },
    },
    watch: {
        abierto(v) {
            if (!v) return;
            this.form = vacio(this.nombreInicial);
            this.elegidas = [];
            this.buscar = '';
            this.errores = {};
            this.cargar();
        },
    },
    methods: {
        async cargar() {
            this.cargando = true;
            this.errorCarga = '';
            try {
                const { data } = await axios.get(`${this.voipUrl}/grupos-timbrado/extensiones-disponibles`);
                this.extensiones = Array.isArray(data) ? data : [];
            } catch (e) {
                this.errorCarga = e.response?.status === 403
                    ? 'No tienes permiso para ver las extensiones de VoIP.'
                    : 'No se pudieron cargar las extensiones.';
            } finally {
                this.cargando = false;
            }
            // Quién está conectado ahora: ayuda a elegir, pero si no se sabe no estorba.
            try {
                const { data } = await axios.get(`${this.voipUrl}/grupos-timbrado/estados`);
                this.conectadas = Array.isArray(data?.conectadas) ? new Set(data.conectadas.map(String)) : null;
            } catch (e) {
                this.conectadas = null;
            }
        },
        posicion(id) {
            const i = this.elegidas.indexOf(id);
            return i === -1 ? 0 : i + 1;
        },
        alternar(id) {
            this.elegidas = this.elegidas.includes(id) ? this.elegidas.filter(x => x !== id) : [...this.elegidas, id];
            if (this.errores.miembros) this.errores = { ...this.errores, miembros: '' };
        },
        elegirVisibles(elegir) {
            const ids = this.visibles.map(e => e.id);
            this.elegidas = elegir
                ? [...this.elegidas, ...ids.filter(id => !this.elegidas.includes(id))]
                : this.elegidas.filter(id => !ids.includes(id));
        },
        cerrar() {
            if (!this.creando) this.$emit('cerrar');
        },
        async crear() {
            if (!this.puedeCrear || this.creando) return;
            this.creando = true;
            this.errores = {};
            const datos = {
                ...this.form,
                nombre: this.form.nombre.trim(),
                activo: true,
                miembros: this.elegidas.map((id, i) => ({ id, orden: i })),
            };
            try {
                const { data } = await axios.post(`${this.voipUrl}/grupos-timbrado`, datos, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, Accept: 'application/json' },
                });
                this.$emit('creado', { id: data.id, nombre: datos.nombre });
            } catch (e) {
                const r = e.response;
                if (r?.status === 422 && r.data?.errors) {
                    const err = r.data.errors;
                    const primero = k => (Array.isArray(err[k]) ? err[k][0] : err[k]);
                    this.errores = {
                        nombre: err.nombre ? (/taken|ocupad|registrad|existe/i.test(primero('nombre')) ? 'Ya hay un grupo con ese nombre.' : primero('nombre')) : '',
                        miembros: Object.keys(err).some(k => k.startsWith('miembros')) ? 'Una de las extensiones ya no existe: recarga la lista.' : '',
                        general: Object.keys(err).filter(k => k !== 'nombre' && !k.startsWith('miembros')).map(primero)[0] || '',
                    };
                } else if (r?.status === 403) {
                    this.errores = { general: 'No tienes permiso para crear grupos de timbrado.' };
                } else {
                    this.errores = { general: 'No se pudo crear el grupo. Intenta de nuevo.' };
                }
            } finally {
                this.creando = false;
            }
        },
    },
};
</script>

<style scoped>
.cgn { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: 28px; align-items: start; font-size: 13.5px; }
.cgn-col { min-width: 0; }
.cgn-seccion { border: 0; margin: 0 0 22px; padding: 0; min-width: 0; }
.cgn-col .cgn-seccion:last-child { margin-bottom: 0; }
.cgn-titulo {
    display: flex; align-items: baseline; justify-content: space-between; gap: 10px; float: left; width: 100%;
    margin: 0 0 12px; padding: 0 0 8px; border-bottom: 1px solid var(--cp-borde); font-size: 14px; font-weight: 600; color: var(--cp-tinta);
}
.cgn-titulo + * { clear: both; }
.cgn-seccion--quien { background: var(--cp-cabecera); border: 1px solid var(--cp-borde); border-radius: 12px; padding: 16px 18px; }
.cgn-cuenta { font-size: 12.5px; font-weight: 600; color: var(--cp-acento); }
.cgn-oculto { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }

/* Cómo suena: tres opciones que se leen, no un desplegable con «ringall». */
.cgn-modos { display: grid; gap: 8px; margin-bottom: 18px; }
.cgn-modo {
    position: relative; display: flex; align-items: flex-start; gap: 12px; margin: 0; padding: 12px 14px; cursor: pointer;
    border: 1px solid var(--cp-borde-campo); border-radius: 10px; background: var(--cp-superficie);
    transition: border-color .15s, background-color .15s, box-shadow .15s;
}
.cgn-modo:hover { border-color: var(--cp-acento); }
.cgn-modo--elegido { border-color: var(--cp-acento); background: var(--cp-acento-suave); box-shadow: inset 0 0 0 1px var(--cp-acento); }
.cgn-modo:has(input:focus-visible) { box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento); }
.cgn-modo-icono {
    flex: none; width: 34px; height: 34px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center;
    background: var(--cp-cabecera); color: var(--cp-tenue); font-size: 14px; transition: background-color .15s, color .15s;
}
.cgn-modo--elegido .cgn-modo-icono { background: var(--cp-acento); color: var(--cp-acento-texto); }
.cgn-modo-texto { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cgn-modo-texto strong { font-size: 13.5px; font-weight: 600; color: var(--cp-tinta); }
.cgn-modo-texto span { font-size: 12.5px; line-height: 1.4; color: var(--cp-tenue); }

.cgn-tiempo .cp-etiqueta { display: flex; justify-content: space-between; align-items: baseline; }
.cgn-valor { color: var(--cp-acento); font-weight: 700; }
.cgn-rango { width: 100%; height: 34px; accent-color: var(--cp-acento); cursor: pointer; }
.cgn-rango-extremos { display: flex; justify-content: space-between; margin-top: -6px; font-size: 12px; color: var(--cp-tenue); }

.cgn-segmentos { display: inline-flex; flex-wrap: wrap; padding: 3px; border-radius: 9px; background: var(--cp-cabecera); border: 1px solid var(--cp-borde); }
.cgn-segmento {
    position: relative; display: inline-flex; align-items: center; gap: 7px; margin: 0; padding: 6px 12px; border-radius: 7px;
    font-size: 13px; font-weight: 500; color: var(--cp-tenue); cursor: pointer; transition: background-color .15s, color .15s;
}
.cgn-segmento i { font-size: 12px; }
.cgn-segmento:hover { color: var(--cp-tinta); }
.cgn-segmento--elegido { background: var(--cp-superficie); color: var(--cp-tinta); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.cd-dark .cgn-segmento--elegido { background: rgba(255, 255, 255, .12); box-shadow: none; }
.cgn-segmento:has(input:focus-visible) { box-shadow: 0 0 0 2px var(--cp-acento); }

.cgn-interruptor { display: flex; align-items: flex-start; gap: 12px; margin: 4px 0 0; cursor: pointer; }
.cgn-interruptor input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.cgn-interruptor-pista {
    flex: none; position: relative; width: 38px; height: 22px; margin-top: 1px; border-radius: 999px;
    background: var(--cp-barra); transition: background-color .18s;
}
.cgn-interruptor-pista span {
    position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 2px rgba(16, 24, 40, .25); transition: transform .2s cubic-bezier(.16, 1, .3, 1);
}
.cgn-interruptor input:checked + .cgn-interruptor-pista { background: var(--cp-acento); }
.cgn-interruptor input:checked + .cgn-interruptor-pista span { transform: translateX(16px); }
.cgn-interruptor input:focus-visible + .cgn-interruptor-pista { box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento); }
.cgn-interruptor-texto { display: flex; flex-direction: column; gap: 2px; }
.cgn-interruptor-texto strong { font-size: 13.5px; font-weight: 600; color: var(--cp-tinta); }
.cgn-interruptor-texto span { font-size: 12.5px; line-height: 1.4; color: var(--cp-tenue); }

/* Quién contesta */
.cgn-buscar-fila { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.cgn-buscar { position: relative; flex: 1 1 240px; margin: 0; }
.cgn-buscar i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--cp-tenue); font-size: 12.5px; pointer-events: none; }
.cgn-buscar .cp-campo { padding-left: 32px; }
.cgn-enlace { border: 0; background: none; padding: 0; color: var(--cp-acento); font-size: 13px; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; white-space: nowrap; }
.cgn-ayuda { display: flex; align-items: center; gap: 7px; margin: 0 0 10px; font-size: 12.5px; color: var(--cp-tenue); }
.cgn-extensiones {
    list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 6px;
    max-height: min(56vh, 520px); overflow-y: auto; overscroll-behavior: contain;
    scrollbar-width: thin; scrollbar-color: var(--cp-borde-campo) transparent;
}
.cgn-ext {
    display: flex; align-items: center; gap: 10px; min-height: 44px; margin: 0; padding: 8px 12px; cursor: pointer;
    border: 1px solid var(--cp-borde); border-radius: 9px; background: var(--cp-superficie); color: var(--cp-tinta);
    transition: border-color .15s, background-color .15s;
}
.cgn-ext:hover { border-color: var(--cp-borde-campo); }
.cgn-ext input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.cgn-ext:has(input:focus-visible) { box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento); }
.cgn-ext--elegida { border-color: var(--cp-acento); background: var(--cp-acento-suave); }
/* La casilla y, ya elegida, su lugar en el orden. */
.cgn-orden {
    flex: none; width: 22px; height: 22px; border-radius: 6px; border: 1.5px solid var(--cp-borde-campo);
    display: inline-flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700; color: var(--cp-acento-texto);
    transition: background-color .15s, border-color .15s;
}
.cgn-ext--elegida .cgn-orden { background: var(--cp-acento); border-color: var(--cp-acento); }
.cgn-ext-numero { flex: none; font-weight: 600; }
.cgn-ext-nombre { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--cp-tenue); }
.cgn-ext--elegida .cgn-ext-nombre { color: var(--cp-tinta); }
.cgn-ext-estado { flex: none; display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: var(--cp-tenue); }
.cgn-punto { width: 7px; height: 7px; border-radius: 50%; background: var(--cp-borde-campo); }
.cgn-ext-estado--si { color: #15803d; }
.cgn-ext-estado--si .cgn-punto { background: currentColor; }
.cd-dark .cgn-ext-estado--si { color: #86efac; }
.cgn-ext--esqueleto { cursor: default; }
.cgn-esqueleto { display: block; width: 70%; height: 11px; border-radius: 6px; background: var(--cp-barra); }
@media (prefers-reduced-motion: no-preference) {
    .cgn-esqueleto { animation: cgn-pulso 1.4s ease-in-out infinite; }
}
@keyframes cgn-pulso { 50% { opacity: .45; } }
.cgn-vacio { grid-column: 1 / -1; padding: 28px 12px; text-align: center; color: var(--cp-tenue); }
.cgn-vacio strong { display: block; margin-bottom: 4px; color: var(--cp-tinta); }
.cgn-aviso { display: flex; gap: 10px; align-items: flex-start; margin: 0; padding: 10px 14px; border-radius: 10px; background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
.cd-dark .cgn-aviso { background: rgba(239, 68, 68, .12); color: #fca5a5; border-color: rgba(239, 68, 68, .3); }

/* Pie del diálogo */
.cgn-pie-error { margin: 0 auto 0 0; font-size: 13px; font-weight: 500; color: var(--cp-alerta); }
.cgn-pie-ayuda { margin-right: auto; font-size: 12.5px; color: var(--cp-tenue); }
.cgn-boton {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 36px;
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta);
    border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;
    transition: background-color .15s, border-color .15s, color .15s, filter .15s;
}
.cgn-boton:hover:not(:disabled) { border-color: var(--cp-acento); color: var(--cp-acento); }
.cgn-boton:disabled { opacity: .5; cursor: default; }
.cgn-boton--primario { background: var(--cp-acento); border-color: var(--cp-acento); color: var(--cp-acento-texto); font-weight: 600; }
.cgn-boton--primario:hover:not(:disabled) { filter: brightness(1.08); color: var(--cp-acento-texto); }

@media (max-width: 899px) {
    .cgn { grid-template-columns: minmax(0, 1fr); gap: 20px; }
    .cgn-extensiones { max-height: none; }
}
@media (max-width: 767px) {
    .cgn-boton { min-height: 44px; }
    .cgn-pie-ayuda, .cgn-pie-error { flex-basis: 100%; }
}
</style>
