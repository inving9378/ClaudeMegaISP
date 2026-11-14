<template>
    <div class="mkt-revisar">
        <section v-for="s in secciones" :key="s.paso" class="mkt-revisar__bloque">
            <header class="mkt-revisar__cabeza">
                <h3 class="mkt-revisar__titulo">{{ s.titulo }}</h3>
                <mkt-boton tipo="enlace" chico icono-fa="fa-edit" etiqueta="Cambiar" :aria-label="`Cambiar ${s.titulo}`" @click="$emit('ir-a', s.paso)" />
            </header>
            <dl class="mkt-revisar__datos">
                <template v-for="d in s.datos" :key="d.etiqueta">
                    <dt>{{ d.etiqueta }}</dt>
                    <dd :class="{ 'mkt-revisar__vacio': !d.valor }">{{ d.valor || "—" }}</dd>
                </template>
            </dl>
        </section>

        <section class="mkt-revisar__bloque">
            <header class="mkt-revisar__cabeza">
                <h3 class="mkt-revisar__titulo">Lo que va a preparar la IA</h3>
            </header>
            <ul v-if="piezas.length" class="mkt-revisar__piezas">
                <li><q-icon name="insights" size="18px" aria-hidden="true" /> Estrategia: resumen, mensajes clave, plan por semana y reparto del presupuesto</li>
                <li v-for="p in piezas" :key="p.pieza">
                    <q-icon name="auto_awesome" size="18px" aria-hidden="true" />
                    {{ p.variantes.length }} × {{ p.etiqueta }} ({{ p.variantes.join(", ") }})
                </li>
            </ul>
            <p v-else class="mkt-revisar__vacio">Elige al menos un canal.</p>
        </section>
    </div>
</template>

<script>
import { formatoMXN, formatoDia, MktBoton } from "../kit";
import { etiquetaObjetivo, etiquetaCanal, piezasEsperadas } from "./catalogos360";

/** Asistente 360 · paso 6: revisar antes de guardar o generar. Emite `ir-a` con el número de paso. */
export default {
    name: "PasoRevisar",
    components: { MktBoton },
    props: {
        modelValue: { type: Object, required: true },
    },
    emits: ["ir-a"],
    computed: {
        piezas() {
            return piezasEsperadas(this.modelValue.canales || []);
        },
        secciones() {
            const f = this.modelValue;
            const p = f.publico || {};
            const o = f.oferta || {};
            const limpio = (v) => String(v ?? "").replace(/[,\s$]/g, "");
            return [
                { paso: 1, titulo: "Objetivo", datos: [
                    { etiqueta: "Nombre", valor: f.nombre },
                    { etiqueta: "Objetivo", valor: f.objetivo ? etiquetaObjetivo(f.objetivo) : "" },
                ] },
                { paso: 2, titulo: "Público", datos: [
                    { etiqueta: "Tipo de público", valor: p.descripcion },
                ] },
                { paso: 3, titulo: "Oferta", datos: [
                    { etiqueta: "Qué ofrecemos", valor: o.descripcion },
                    { etiqueta: "Plan", valor: o.plan },
                    { etiqueta: "Precio al mes", valor: limpio(o.precio_mxn) ? formatoMXN(limpio(o.precio_mxn)) : "" },
                    { etiqueta: "Condiciones", valor: o.condiciones },
                    { etiqueta: "Vale hasta", valor: o.vigencia_hasta ? formatoDia(o.vigencia_hasta) : "" },
                ] },
                { paso: 4, titulo: "Canales", datos: [
                    { etiqueta: "Canales", valor: (f.canales || []).map(etiquetaCanal).join(", ") },
                ] },
                { paso: 5, titulo: "Presupuesto y fechas", datos: [
                    { etiqueta: "Presupuesto", valor: limpio(f.presupuesto_mxn) !== "" ? formatoMXN(limpio(f.presupuesto_mxn)) : "" },
                    { etiqueta: "Fechas", valor: f.fecha_inicio || f.fecha_fin ? `${formatoDia(f.fecha_inicio)} – ${formatoDia(f.fecha_fin)}` : "" },
                ] },
            ];
        },
    },
};
</script>

<style scoped>
.mkt-revisar { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
.mkt-revisar__bloque { border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); padding: 12px var(--mkt-esp-2); min-width: 0; }
.mkt-revisar__cabeza { display: flex; align-items: center; justify-content: space-between; gap: var(--mkt-esp-1); margin-bottom: var(--mkt-esp-1); }
.mkt-revisar__titulo { margin: 0; font-size: 15px; font-weight: 600; line-height: 24px; color: var(--mkt-texto); }
.mkt-revisar__datos { display: grid; grid-template-columns: minmax(96px, max-content) minmax(0, 1fr); gap: 4px var(--mkt-esp-2); margin: 0; font-size: 14px; }
.mkt-revisar__datos dt { color: var(--mkt-texto-suave); font-weight: 500; }
.mkt-revisar__datos dd { margin: 0; overflow-wrap: anywhere; white-space: pre-line; }
.mkt-revisar__vacio { color: var(--mkt-texto-suave); }
.mkt-revisar__piezas { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
.mkt-revisar__piezas li { display: flex; align-items: flex-start; gap: 8px; }
.mkt-revisar__piezas .q-icon { color: var(--mkt-primario); margin-top: 1px; }
@media (max-width: 1023px) {
    .mkt-revisar { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-revisar__datos { grid-template-columns: minmax(0, 1fr); }
    .mkt-revisar__datos dd { margin-bottom: 6px; }
}
</style>
