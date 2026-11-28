<template>
    <!-- «A quién se le llama» (27-nov, pedido de Irving): estados, zona y caja opcionales y «solo los
         que deben». Los números salen de la misma lista a la que se llama (ver AudienciaCampana.php). -->
    <div class="ca" :class="{ 'ca--barra': compacta, 'ca--cargando': cargando && resumen }" :aria-busy="cargando">
        <div class="ca-cuerpo">
            <fieldset class="ca-grupo ca-grupo--estados">
                <legend class="cp-etiqueta ca-leyenda">Estado del cliente</legend>
                <div class="ca-estados">
                    <label v-for="(etiqueta, e) in ESTADOS_CLIENTE" :key="e" class="ca-estado" :class="{ 'ca-estado--marcado': marcado(e) }">
                        <input type="checkbox" :checked="marcado(e)" @change="alternar(e)">
                        <span class="ca-estado-texto">{{ etiqueta }}</span>
                        <span class="ca-estado-n cp-num">
                            <span v-if="resumen">{{ n(resumen.por_estado[e]) }}</span>
                            <span v-else-if="cargando" class="ca-esqueleto" aria-hidden="true"></span>
                            <span v-else aria-hidden="true">—</span>
                        </span>
                    </label>
                </div>
                <p v-if="!estados.length" class="cp-error">Marca al menos un estado.</p>
            </fieldset>

            <div class="ca-lugar">
                <!-- Con búsqueda (28-nov): en prod son cientos de zonas y de cajas. -->
                <div class="cp-campo-grupo">
                    <label :id="`${idBase}-zona-etiqueta`" :for="`${idBase}-zona`" class="cp-etiqueta">Zona <span class="cp-opcional">· opcional</span></label>
                    <campanas-selector :id="`${idBase}-zona`" :id-etiqueta="`${idBase}-zona-etiqueta`" :model-value="zona"
                                       :grupos="gruposZonas" texto-todas="Todas las zonas" buscar-etiqueta="Buscar zona o distrito"
                                       vacio="Ningún cliente tiene zona con estos filtros." @update:model-value="elegirZona" />
                </div>
                <div class="cp-campo-grupo">
                    <label :id="`${idBase}-caja-etiqueta`" :for="`${idBase}-caja`" class="cp-etiqueta">Caja <span class="cp-opcional">· opcional</span></label>
                    <campanas-selector :id="`${idBase}-caja`" :id-etiqueta="`${idBase}-caja-etiqueta`" :model-value="caja"
                                       :grupos="gruposCajas" texto-todas="Todas las cajas" buscar-etiqueta="Buscar caja"
                                       vacio="Esta zona no tiene cajas con clientes." :disabled="!zona" texto-deshabilitado="Elige antes la zona"
                                       alinear="derecha" @update:model-value="$emit('update:caja', $event || null)" />
                </div>
            </div>

            <div class="ca-deben">
                <label class="ca-check">
                    <input type="checkbox" :checked="soloDeudores" @change="$emit('update:soloDeudores', $event.target.checked)">
                    <span>Solo los que deben</span>
                </label>
                <span v-if="soloDeudores" class="ca-dias">
                    <label :for="`${idBase}-dias`">factura vencida hace</label>
                    <input :id="`${idBase}-dias`" type="number" min="0" max="3650" inputmode="numeric" class="cp-campo ca-dias-campo cp-num"
                           :value="dias" @change="cambiarDias($event.target)">
                    <span>{{ Number(dias) === 1 ? 'día o más' : 'días o más' }}</span>
                </span>
                <p v-if="!compacta" class="cp-ayuda ca-deben-ayuda">
                    {{ soloDeudores ? 'El mensaje puede decir el saldo de cada cliente, y al que pague ya no se le llama.' : 'Se le llama a todos, deban o no.' }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import { ESTADOS_CLIENTE, nombreZona, nombreCaja, zonasRepetidas, leerClave } from './audiencia.js';
import CampanasSelector from './CampanasSelector.vue';

export default {
    name: 'CampanasAudiencia',
    components: { CampanasSelector },
    props: {
        estados:      { type: Array, default: () => [] },
        zona:         { type: String, default: null },
        caja:         { type: String, default: null },
        soloDeudores: { type: Boolean, default: false },
        dias:         { type: Number, default: 1 },
        // Respuesta de /campanas/audiencia (por_estado, zonas…); null mientras llega la primera.
        resumen:      { type: Object, default: null },
        cargando:     { type: Boolean, default: false },
        compacta:     { type: Boolean, default: false },
        idBase:       { type: String, required: true },
    },
    emits: ['update:estados', 'update:zona', 'update:caja', 'update:soloDeudores', 'update:dias'],
    setup() {
        return { ESTADOS_CLIENTE, nombreZona, nombreCaja };
    },
    computed: {
        zonas() {
            const lista = [...((this.resumen && this.resumen.zonas) || [])];
            // La zona elegida sigue a la vista aunque hoy no tenga clientes con estos filtros.
            if (this.zona && !lista.some(z => z.clave === this.zona)) {
                const k = leerClave(this.zona);
                if (k) lista.push({ clave: this.zona, distrito: k.d, letra: k.l, zona: k.z, clientes: 0, cajas: [] });
            }
            return lista;
        },
        repetidas() {
            return zonasRepetidas(this.zonas);
        },
        distritos() {
            const grupos = {};
            for (const z of this.zonas) (grupos[z.distrito] = grupos[z.distrito] || { distrito: z.distrito, zonas: [] }).zonas.push(z);
            return Object.values(grupos)
                .sort((a, b) => a.distrito - b.distrito)
                .map(g => ({ ...g, zonas: g.zonas.sort((a, b) => (a.letra || 'Z').localeCompare(b.letra || 'Z') || a.zona - b.zona) }));
        },
        cajas() {
            const lista = [...((this.zonas.find(z => z.clave === this.zona) || {}).cajas || [])];
            if (this.caja && !lista.some(c => c.clave === this.caja)) {
                const k = leerClave(this.caja);
                if (k) lista.push({ clave: this.caja, caja: k.c, clientes: 0 });
            }
            return lista.sort((a, b) => a.caja - b.caja);
        },
        // Para el selector: por distrito, y se encuentra también por la clave («D1Z54»), «distrito 1» o «wifi».
        gruposZonas() {
            return this.distritos.map(d => ({
                titulo: `Distrito ${d.distrito}`,
                opciones: d.zonas.map(z => ({
                    valor: z.clave, etiqueta: nombreZona(z.clave, this.repetidas), n: z.clientes,
                    buscar: `${z.clave} ${z.letra === 'W' ? 'wifi' : 'zona'} ${z.zona}`,
                })),
            }));
        },
        gruposCajas() {
            return [{ titulo: null, opciones: this.cajas.map(cj => ({ valor: cj.clave, etiqueta: nombreCaja(cj.clave), n: cj.clientes, buscar: cj.clave })) }];
        },
    },
    methods: {
        n(x) {
            return Number(x || 0).toLocaleString('es-MX');
        },
        marcado(e) {
            return this.estados.includes(e);
        },
        alternar(e) {
            const nuevos = this.marcado(e) ? this.estados.filter(x => x !== e) : [...this.estados, e];
            // Siempre en el mismo orden (Activos, Bloqueados, Inactivos): así se resume igual.
            this.$emit('update:estados', Object.keys(ESTADOS_CLIENTE).filter(x => nuevos.includes(x)));
        },
        elegirZona(clave) {
            this.$emit('update:zona', clave || null);
            this.$emit('update:caja', null);
        },
        cambiarDias(campo) {
            const d = Math.max(0, Math.min(3650, Math.trunc(Number(campo.value) || 0)));
            campo.value = d;
            this.$emit('update:dias', d);
        },
    },
};
</script>

<style scoped>
/* Mide su propio ancho: en la columna del alta va angosta, en la revisión ocupa todo el diálogo. */
.ca { container: ca / inline-size; }
.ca-cuerpo { display: grid; gap: 16px; }
.ca-cuerpo > * { min-width: 0; }
.ca--cargando .ca-estado-n, .ca--cargando .ca-lugar :deep(.cs-n) { opacity: .6; }
.ca-grupo { border: 0; margin: 0; padding: 0; min-width: 0; }
/* Un <legend> se dibuja ENCIMA del borde de su fieldset; flotado queda dentro de la caja. */
.ca-leyenda { float: left; width: 100%; padding: 0; }
.ca-leyenda + * { clear: both; }

/* Una lista de casillas con su número a la derecha; en tres columnas cuando caben. */
.ca-estados { display: grid; grid-template-columns: 1fr; gap: 6px; }
.ca-estado {
    display: flex; align-items: center; gap: 9px; min-height: 38px; margin: 0; padding: 7px 12px; cursor: pointer;
    border: 1px solid var(--cp-borde-campo); border-radius: 8px; background: var(--cp-superficie);
    font-size: 13.5px; font-weight: 500; color: var(--cp-tinta); transition: border-color .15s, background .15s;
}
.ca-estado:hover { border-color: var(--cp-acento); }
.ca-estado input { flex: none; width: 15px; height: 15px; margin: 0; accent-color: var(--cp-acento); cursor: pointer; }
.ca-estado-texto { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ca-estado-n { margin-left: auto; padding-left: 8px; font-size: 12.5px; font-weight: 500; color: var(--cp-tenue); }
.ca-estado--marcado { border-color: var(--cp-acento); background: var(--cp-acento-suave); box-shadow: inset 0 0 0 1px var(--cp-acento); }
.ca-estado--marcado .ca-estado-n { color: var(--cp-acento); font-weight: 600; }
.ca-estado:has(input:focus-visible) { box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento); }
.ca-estado input:focus-visible { box-shadow: none !important; }
.ca-esqueleto { display: inline-block; width: 28px; height: 10px; border-radius: 5px; background: var(--cp-barra); vertical-align: middle; }

.ca-lugar { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0 14px; }
.ca-lugar .cp-campo-grupo { margin-bottom: 0; }

.ca-deben { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 14px; }
.ca-check { display: inline-flex; align-items: center; gap: 9px; margin: 0; min-height: 36px; font-size: 13.5px; font-weight: 600; color: var(--cp-tinta); cursor: pointer; }
.ca-check input { width: 15px; height: 15px; margin: 0; accent-color: var(--cp-acento); cursor: pointer; }
.ca-dias { display: inline-flex; flex: none; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; color: var(--cp-tinta); }
.ca-dias label { margin: 0; font-weight: 400; }
/* .ca delante: el campo general (.cp-raiz .cp-campo) mide 100% y pesaría igual. */
.ca .ca-dias-campo { width: 68px; min-height: 32px; padding-top: 4px; padding-bottom: 4px; text-align: right; }
.ca-deben-ayuda { flex: 1 1 100%; margin-top: 0; }

@container ca (min-width: 540px) {
    .ca-estados { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
}
@container ca (max-width: 339px) {
    .ca-lugar { grid-template-columns: 1fr; gap: 14px; }
}
/* En la revisión: estados y lugar en una franja cuando hay ancho. */
@container ca (min-width: 900px) {
    .ca--barra .ca-cuerpo { grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); align-items: end; gap: 14px 24px; }
    .ca--barra .ca-deben { grid-column: 1 / -1; }
}
@media (max-width: 767px) {
    .ca-estado, .ca-check { min-height: 44px; }
    .ca .ca-dias-campo { min-height: 44px; }
}
</style>
