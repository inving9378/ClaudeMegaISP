<template>
    <!-- Va en <body>: así queda encima de todo el layout (y de los botones flotantes de Ayuda/IA). -->
    <Teleport to="body">
        <div v-if="abierto" class="cd cp-raiz cpd-fondo" :class="[{ 'cd-dark': oscuro }, `cpd-fondo--${variante}`]"
             @mousedown.self="cerrar">
            <div ref="caja" class="cpd-caja" :class="`cpd-caja--${variante}`" role="dialog" aria-modal="true"
                 :aria-labelledby="idTitulo" :aria-describedby="descripcion ? idDesc : null" tabindex="-1"
                 @keydown="tecla">
                <header class="cpd-cabeza">
                    <div class="cpd-cabeza-texto">
                        <h2 :id="idTitulo" class="cpd-titulo"><slot name="titulo">{{ titulo }}</slot></h2>
                        <p v-if="descripcion" :id="idDesc" class="cpd-desc">{{ descripcion }}</p>
                    </div>
                    <button type="button" class="cpd-cerrar" aria-label="Cerrar" @click="cerrar">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="cpd-cuerpo"><slot /></div>
                <footer v-if="$slots.pie" class="cpd-pie"><slot name="pie" /></footer>
            </div>
        </div>
    </Teleport>
</template>

<script>
// Diálogo de Campañas/Blaster (25-nov, crítica de impeccable): antes eran <div> sueltos sin
// role="dialog", Esc no cerraba y el foco se quedaba en la página de atrás.
let contador = 0;
let abiertos = 0;

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), '
    + 'textarea:not([disabled]), audio[controls], summary, [tabindex]:not([tabindex="-1"])';

export default {
    name: 'CampanasDialogo',
    props: {
        abierto:     { type: Boolean, default: false },
        titulo:      { type: String, default: '' },
        descripcion: { type: String, default: '' },
        // normal (560 px) · ancho (toda la pantalla, pedido de Irving) · cajon (panel a la derecha)
        variante:    { type: String, default: 'normal' },
        oscuro:      { type: Boolean, default: false },
    },
    emits: ['cerrar'],
    data() {
        const n = ++contador;
        return { idTitulo: `cpd-titulo-${n}`, idDesc: `cpd-desc-${n}`, previo: null, activo: false };
    },
    watch: {
        abierto: {
            immediate: true,
            handler(v) { v ? this.alAbrir() : this.alCerrar(); },
        },
    },
    beforeUnmount() {
        this.alCerrar();
    },
    methods: {
        alAbrir() {
            if (this.activo) return;
            this.activo = true;
            this.previo = document.activeElement;
            abiertos++;
            document.body.classList.add('cp-sin-scroll');
            this.$nextTick(() => {
                const caja = this.$refs.caja;
                if (!caja) return;
                const primero = caja.querySelector('[data-autofoco]');
                (primero || caja).focus({ preventScroll: true });
            });
        },
        alCerrar() {
            if (!this.activo) return;
            this.activo = false;
            abiertos = Math.max(0, abiertos - 1);
            if (abiertos === 0) document.body.classList.remove('cp-sin-scroll');
            const previo = this.previo;
            this.previo = null;
            if (previo && document.contains(previo) && typeof previo.focus === 'function') {
                this.$nextTick(() => previo.focus({ preventScroll: true }));
            }
        },
        cerrar() {
            this.$emit('cerrar');
        },
        enfocables() {
            const caja = this.$refs.caja;
            if (!caja) return [];
            return [...caja.querySelectorAll(ENFOCABLES)].filter(el => el.getClientRects().length > 0);
        },
        // Esc cierra; Tab da la vuelta dentro del diálogo (no se escapa a la página de atrás).
        tecla(e) {
            if (e.key === 'Escape') {
                // Con un menú de Quasar abierto (estado, columnas, por página), Esc cierra solo el menú.
                if (document.querySelector('.q-menu')) return;
                e.stopPropagation();
                this.cerrar();
                return;
            }
            if (e.key !== 'Tab') return;
            const f = this.enfocables();
            if (!f.length) { e.preventDefault(); return; }
            const primero = f[0];
            const ultimo = f[f.length - 1];
            const actual = document.activeElement;
            if (e.shiftKey && (actual === primero || actual === this.$refs.caja)) {
                e.preventDefault();
                ultimo.focus();
            } else if (!e.shiftKey && actual === ultimo) {
                e.preventDefault();
                primero.focus();
            }
        },
    },
};
</script>

<style>
/* Los menús y avisos de Quasar (estado, columnas, elementos por página) van en <body> con z-index
   6000: con un diálogo de Campañas abierto (10050) quedaban detrás. Solo mientras hay uno abierto. */
body.cp-sin-scroll .q-menu,
body.cp-sin-scroll .q-tooltip { z-index: 10100; }
</style>

<style scoped>
.cpd-fondo {
    position: fixed; inset: 0; z-index: 10050;
    display: flex; align-items: center; justify-content: center; padding: 24px 16px;
    background: rgba(15, 23, 42, .5);
}
.cpd-fondo--cajon { justify-content: flex-end; align-items: stretch; padding: 0; }
/* Ancho (28-nov, pedido de Irving): toda la pantalla, con un margen para ver que es un diálogo. */
.cpd-fondo--ancho { padding: 16px; }
.cpd-caja {
    display: flex; flex-direction: column; max-height: calc(100dvh - 48px); width: min(560px, 100%);
    background: var(--cp-superficie); color: var(--cp-tinta); border-radius: 12px; outline: none;
    box-shadow: 0 24px 48px rgba(15, 23, 42, .22), 0 2px 6px rgba(15, 23, 42, .08);
}
.cpd-caja--ancho { width: 100%; height: 100%; max-height: none; }
.cpd-caja--cajon { width: min(780px, 100vw); max-height: none; height: 100dvh; border-radius: 0; }
.cd-dark .cpd-caja { box-shadow: 0 24px 48px rgba(0, 0, 0, .55); }

.cpd-cabeza {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
    padding: 18px 20px 14px; border-bottom: 1px solid var(--cp-borde);
}
.cpd-cabeza-texto { min-width: 0; }
.cpd-titulo { margin: 0; font-size: 1.0625rem; font-weight: 600; line-height: 1.35; color: var(--cp-tinta); overflow-wrap: anywhere; }
.cpd-desc { margin: 4px 0 0; font-size: 13px; color: var(--cp-tenue); }
.cpd-cerrar {
    flex: none; width: 34px; height: 34px; border-radius: 8px; border: 1px solid transparent; background: transparent;
    color: var(--cp-tenue); display: inline-flex; align-items: center; justify-content: center; font-size: 14px; cursor: pointer;
}
.cpd-cerrar:hover { background: var(--cp-hover); color: var(--cp-tinta); }
.cpd-caja:focus-visible { outline: none; }
.cpd-cerrar:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento) !important; }
/* --cpd-pad-top: lo que una cabecera pegajosa de adentro sube para quedar al ras (el padding cuenta). */
.cpd-cuerpo { --cpd-pad-top: 18px; padding: var(--cpd-pad-top) 20px 18px; overflow-y: auto; overscroll-behavior: contain; flex: 1 1 auto; }
.cpd-pie {
    display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 12px 20px; border-top: 1px solid var(--cp-borde); background: var(--cp-cabecera);
    border-radius: 0 0 12px 12px;
}
.cpd-caja--cajon .cpd-pie { border-radius: 0; }

@media (prefers-reduced-motion: no-preference) {
    .cpd-fondo { animation: cpd-aparece .16s ease-out; }
    .cpd-caja { animation: cpd-sube .2s cubic-bezier(.16, 1, .3, 1); }
    .cpd-caja--cajon { animation: cpd-desliza .24s cubic-bezier(.16, 1, .3, 1); }
}
@keyframes cpd-aparece { from { opacity: 0; } }
@keyframes cpd-sube { from { transform: translateY(10px); opacity: 0; } }
@keyframes cpd-desliza { from { transform: translateX(32px); opacity: 0; } }

/* Celular: el diálogo ocupa la pantalla y el pie queda a la mano */
@media (max-width: 767px) {
    .cpd-fondo { padding: 0; }
    .cpd-caja, .cpd-caja--ancho, .cpd-caja--cajon { width: 100%; height: 100dvh; max-height: none; border-radius: 0; }
    .cpd-cabeza { padding: 14px 16px 12px; }
    .cpd-cuerpo { --cpd-pad-top: 16px; padding: 16px; }
    .cpd-pie { padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-radius: 0; }
    .cpd-pie > :deep(*) { flex: 1 1 auto; justify-content: center; }
    .cpd-cerrar { width: 44px; height: 44px; }
}
</style>
