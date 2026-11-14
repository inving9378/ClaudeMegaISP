<template>
    <div class="card mkt-ficha" :class="{ 'mkt-ficha--oscuro': tema.oscuro }">
        <div class="mkt-ficha__avatar" aria-hidden="true">
            <i v-if="iconoFa" class="fa" :class="iconoFa"></i>
            <span v-else>{{ iniciales }}</span>
        </div>
        <div class="mkt-ficha__principal">
            <h2 class="mkt-ficha__titulo">
                <span>{{ titulo }}</span>
                <span v-if="numero" class="mkt-ficha__numero">· {{ numero }}</span>
                <slot name="estado" />
            </h2>
            <div v-if="$slots.datos" class="mkt-ficha__linea">
                <slot name="datos" />
            </div>
        </div>
        <div v-if="$slots.cifras || $slots.acciones" class="mkt-ficha__acciones">
            <slot name="cifras" />
            <slot name="acciones" />
        </div>
    </div>
</template>

<script>
/**
 * Encabezado de ficha = tarjeta de encabezado de la ficha de Cliente (InformationClientCrud.vue, `.cic-header-card`):
 * avatar con iniciales, nombre en negritas con su número y estado, una línea de datos clave en gris y, a la derecha,
 * cifras (slot `cifras`, p. ej. «Presupuesto») y botones (slot `acciones`).
 * Para las cifras se puede usar el marcado `<div class="mkt-ficha__cifra"><span>Etiqueta</span><strong>Valor</strong></div>`.
 */
export default {
    name: "MktFichaEncabezado",
    inject: { tema: { from: "mktTema", default: () => ({ oscuro: false }) } },
    props: {
        titulo: { type: String, required: true },
        numero: { type: [String, Number], default: "" },
        iconoFa: { type: String, default: "" },
    },
    computed: {
        iniciales() {
            const partes = String(this.titulo || "").trim().split(/\s+/).filter(Boolean);
            return ((partes[0]?.[0] || "") + (partes[1]?.[0] || "")).toUpperCase() || "—";
        },
    },
};
</script>

<style scoped>
.mkt-ficha {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    padding: 14px 18px;
    margin-bottom: 14px;
    background: var(--mkt-bloque-fondo, #fff);
    border: none; /* `.card` de Bootstrap, igual que `.card.cic-header-card` de la ficha de Cliente */
    flex-direction: row;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    min-width: 0;
}
.mkt-ficha__avatar {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 1.6rem;
}
.mkt-ficha__avatar .fa { font-size: 1.3rem; }
.mkt-ficha__principal { flex: 1 1 280px; min-width: 0; }
.mkt-ficha__titulo {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--mkt-texto, #343a40);
    overflow-wrap: anywhere;
}
.mkt-ficha__numero { color: #74788d; font-weight: 400; }
.mkt-ficha__linea {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 3px;
    font-size: 0.82rem;
    color: #6b7280;
}
/* Cifras y botones a la derecha, como «Salvar Cliente» en la ficha de Cliente (también si bajan de línea). */
.mkt-ficha__acciones {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
}
.mkt-ficha__acciones :deep(.mkt-ficha__cifra) {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    margin-right: 6px;
    line-height: 1.1;
}
.mkt-ficha__acciones :deep(.mkt-ficha__cifra > span) {
    font-size: 0.7rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.mkt-ficha__acciones :deep(.mkt-ficha__cifra > strong) {
    font-size: 1.05rem;
    font-weight: 700;
    color: #16a34a;
    white-space: nowrap;
}
.mkt-ficha--oscuro .mkt-ficha__avatar { background: #312e81; color: #c7d2fe; }
.mkt-ficha--oscuro .mkt-ficha__titulo { color: #f1f5f9; }
.mkt-ficha--oscuro .mkt-ficha__linea { color: #9ca3af; }
@media (max-width: 599px) {
    .mkt-ficha__acciones { width: 100%; }
    .mkt-ficha__acciones :deep(.mkt-ficha__cifra) { align-items: flex-start; }
}
</style>
