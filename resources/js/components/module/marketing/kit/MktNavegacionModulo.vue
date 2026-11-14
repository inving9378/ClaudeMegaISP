<template>
    <mkt-pestanas
        v-if="pestanas.length || externos.length"
        :items="pestanas"
        :model-value="subActivo ? (subActivo.clave || subActivo.url) : null"
        :etiqueta="`Secciones de ${activo.etiqueta}`"
    >
        <template v-if="externos.length" #extra>
            <a
                v-for="ext in externos"
                :key="ext.url"
                class="btn btn-outline-secondary btn-sm waves-effect waves-light"
                :href="ext.url"
                target="_blank"
                rel="noopener"
                :title="`${ext.etiqueta} es otro módulo del sistema: se abre en otra pestaña del navegador`"
            >Abrir {{ ext.etiqueta }} <i class="fa fa-external-link-alt ms-1" aria-hidden="true"></i></a>
        </template>
    </mkt-pestanas>
</template>

<script>
import axios from "axios";
import MktPestanas from "./MktPestanas.vue";

/** Normaliza una ruta: sin barra final, sin query. */
const limpiar = (u) => {
    try {
        return new URL(u, window.location.origin).pathname.replace(/\/+$/, "") || "/";
    } catch (e) {
        return String(u || "");
    }
};

/** Pantallas que existen pero no se ofrecen en la barra (cambio de alcance: solo Meganet). */
const OCULTAS = "/marketing/empresas";
const RAIZ = "/marketing";

/** ¿La URL es de otro módulo (fuera de /marketing)? */
const esExterna = (u) => {
    const r = limpiar(u);
    return r !== RAIZ && !r.startsWith(RAIZ + "/");
};

/** ¿`ruta` es `url` o está debajo de ella? Devuelve el largo de la coincidencia (0 = no). */
const coincide = (ruta, url) => {
    const base = limpiar(url);
    if (!base) return 0;
    if (ruta === base) return base.length;
    // La raíz del módulo (/marketing = Panel) solo cuenta exacta: si no, se marcaría en pantallas cuya
    // sección no aparece en la barra (p. ej. sin permiso para Video).
    if (base === RAIZ) return 0;
    return ruta.startsWith(base + "/") ? base.length : 0;
};

/**
 * Pestañas de la sección de Marketing en la que está el usuario (diseño unificado con Clientes, §2.13).
 * Las 7 secciones están en el submenú lateral («Marketing» → Panel, Campañas 360, …): aquí va UN SOLO grupo de
 * pestañas tipo carpeta, el de las sub-secciones de la sección activa (Reels y videos, Publicación, Leads,
 * Configuración), como las pestañas de la ficha de un cliente. Si la sección no tiene sub-secciones, no pinta nada.
 *
 * Origen (Fase 1, contrato §2.9):
 * Lee GET /api/marketing/navegacion (orden de module.json, ya filtrado por rol y permiso).
 * Marca el item cuya URL (o la de uno de sus `sub`) coincide más largo con la ruta actual.
 * Si la petición falla no pinta nada: la pantalla sigue funcionando.
 */
export default {
    name: "MktNavegacionModulo",
    components: { MktPestanas },
    data() {
        return { items: [], ruta: "", observador: null };
    },
    computed: {
        /** Item activo: la coincidencia más larga entre todas las URL de items y subs. */
        activo() {
            let mejor = null;
            let largo = 0;
            this.items.forEach((item) => {
                [item.url, ...(item.sub || []).map((s) => s.url)].forEach((u) => {
                    const n = coincide(this.ruta, u);
                    if (n > largo) { largo = n; mejor = item; }
                });
            });
            return mejor;
        },
        /** Sub-secciones DE Marketing: son las pestañas. */
        pestanas() {
            const subs = (this.activo?.sub || []).filter((s) => !esExterna(s.url));
            return subs.map((s) => ({ clave: s.clave || s.url, etiqueta: s.etiqueta, url: s.url }));
        },
        /**
         * Sub-secciones que son pantallas de OTRO módulo (p. ej. «Integraciones» → Hub, `/integraciones`): no son
         * pestañas (sacarían al usuario del módulo con otro diseño), sino un enlace «Abrir …» que abre en otra pestaña
         * del navegador. El permiso lo sigue filtrando la API (`manage-integrations`), igual que antes.
         */
        externos() {
            return (this.activo?.sub || []).filter((s) => esExterna(s.url));
        },
        subActivo() {
            const subs = this.activo?.sub || [];
            let mejor = null;
            let largo = 0;
            subs.forEach((s) => {
                const n = coincide(this.ruta, s.url);
                if (n > largo) { largo = n; mejor = s; }
            });
            return mejor;
        },
    },
    mounted() {
        this.leerRuta();
        this.cargar();
        window.addEventListener("popstate", this.leerRuta);
    },
    beforeUnmount() {
        window.removeEventListener("popstate", this.leerRuta);
        this.observador?.disconnect();
    },
    methods: {
        leerRuta() {
            this.ruta = limpiar(window.location.pathname);
        },
        async cargar() {
            try {
                const { data } = await axios.get("/api/marketing/navegacion");
                const lista = Array.isArray(data?.items) ? data.items : [];
                // Cambio de alcance (Henry, 2026-11-13): solo Meganet → «Empresas y equipo» no se ofrece aunque llegue.
                this.items = lista.filter((i) => limpiar(i?.url) !== OCULTAS && i?.clave !== "empresas");
            } catch (e) {
                this.items = [];
            }
            // La navegación SPA monta Vue antes del pushState: al volver la respuesta la URL ya es la nueva.
            this.leerRuta();
        },
    },
};
</script>
