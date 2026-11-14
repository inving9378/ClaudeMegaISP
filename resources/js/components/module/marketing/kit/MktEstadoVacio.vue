<template>
    <div class="mkt-vacio" role="status">
        <div class="mkt-vacio__icono" aria-hidden="true">
            <q-icon :name="icono" size="28px" />
        </div>
        <div class="mkt-vacio__titulo">{{ titulo }}</div>
        <p v-if="texto" class="mkt-vacio__texto">{{ texto }}</p>
    </div>
</template>

<script>
/**
 * Estado vacío / error / sin permiso: SOLO texto (regla de Henry, 13-nov): cada pantalla tiene UN botón de acción
 * principal y va arriba a la derecha, en el encabezado; aquí no se repite ni se agrega ninguno.
 */
export default {
    name: "MktEstadoVacio",
    props: {
        icono: { type: String, default: "inbox" },
        titulo: { type: String, required: true },
        texto: { type: String, default: "" },
    },
};
</script>

<style scoped>
.mkt-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: var(--mkt-esp-4, 32px) var(--mkt-esp-2, 16px);
    gap: var(--mkt-esp-1, 8px);
}
.mkt-vacio__icono {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    background: var(--mkt-kpi-fondo, #f8fafc);
    margin-bottom: var(--mkt-esp-1, 8px);
}
.mkt-vacio__titulo {
    font-size: 16px;
    font-weight: 600;
    color: var(--mkt-texto, #343a40);
}
.mkt-vacio__texto {
    margin: 0;
    max-width: 44ch;
    font-size: 14px;
    line-height: 1.5;
    color: var(--mkt-texto-suave, #74788d);
}
</style>
