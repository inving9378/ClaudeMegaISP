<template>
    <q-card flat bordered class="de" :dark="darkMode">
        <q-card-section class="de-encabezado">
            <div class="text-subtitle2 text-weight-bold">Eventos del periodo</div>
            <div v-if="eventos.total" class="de-cuenta">{{ eventos.total }} {{ eventos.total === 1 ? "evento" : "eventos" }}</div>
        </q-card-section>
        <q-card-section v-if="!eventos.total" class="de-vacio">Sin eventos en el periodo</q-card-section>
        <q-card-section v-else class="de-cuerpo">
            <div class="de-desplazable">
                <table class="de-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Inicio</th>
                            <th scope="col">Duración</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in visibles" :key="`${e.tipo}-${e.inicio}`">
                            <td class="de-nowrap">{{ fechaHora(e.inicio, zona) }}</td>
                            <td class="de-nowrap">{{ duracion(e.minutos) }}</td>
                            <td class="de-nowrap">
                                <span class="de-tipo" :class="`de-${e.tipo}`">{{ TIPOS[e.tipo] || e.tipo }}</span>
                            </td>
                            <td>{{ e.detalle }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="eventos.lista.length > INICIALES || eventos.total > eventos.lista.length" class="de-pie">
                <q-btn
                    v-if="eventos.lista.length > INICIALES"
                    flat
                    no-caps
                    color="primary"
                    class="de-boton"
                    :label="todos ? 'Mostrar solo los más recientes' : `Mostrar los ${eventos.lista.length} eventos`"
                    @click="todos = !todos"
                />
                <span v-if="eventos.total > eventos.lista.length" class="de-cuenta">
                    Se muestran los {{ eventos.lista.length }} más recientes de {{ eventos.total }}.
                </span>
            </div>
        </q-card-section>
    </q-card>
</template>

<script>
import { computed, ref, watch } from "vue";
import { darkMode } from "../../../../../hook/appConfig.js";
import { duracion, fechaHora } from "./formato.js";

// #988 Fase 4, sección 8: tabla de eventos, el más reciente primero; el tipo siempre con texto.
const TIPOS = {
    sin_respuesta: "Sin respuesta",
    degradado: "Degradado",
    perdida_alta: "Pérdida alta",
    latencia_alta: "Latencia alta",
    sin_datos: "Sin datos del colector",
    pausado: "Monitoreo pausado",
};
const INICIALES = 15;

export default {
    name: "DedicadoEventos",
    props: {
        eventos: { type: Object, required: true },
        zona: { type: String, required: true },
    },
    setup(props) {
        const todos = ref(false);
        watch(() => props.eventos, () => (todos.value = false));
        const visibles = computed(() => (todos.value ? props.eventos.lista : props.eventos.lista.slice(0, INICIALES)));

        return { darkMode, TIPOS, INICIALES, todos, visibles, duracion, fechaHora };
    },
};
</script>

<style scoped>
.de {
    border-radius: 10px;
    --de-rojo-fondo: #fee2e2;
    --de-rojo: #991b1b;
    --de-ambar-fondo: #fef3c7;
    --de-ambar: #92400e;
    --de-pausado-fondo: #ccfbf1;
    --de-pausado: #115e59;
    --de-gris-fondo: #f3f4f6;
    --de-gris: #374151;
    --de-borde: rgba(0, 0, 0, 0.08);
}
.de.q-dark {
    --de-rojo-fondo: rgba(248, 113, 113, 0.18);
    --de-rojo: #fca5a5;
    --de-ambar-fondo: rgba(251, 191, 36, 0.16);
    --de-ambar: #fcd34d;
    --de-pausado-fondo: rgba(45, 212, 191, 0.16);
    --de-pausado: #5eead4;
    --de-gris-fondo: rgba(156, 163, 175, 0.16);
    --de-gris: #d1d5db;
    --de-borde: rgba(255, 255, 255, 0.12);
}
.de-encabezado {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 4px;
}
.de-cuenta {
    font-size: 0.8rem;
    opacity: 0.8;
}
.de-vacio {
    opacity: 0.8;
}
.de-cuerpo {
    padding-top: 0;
}
.de-desplazable {
    overflow-x: auto;
}
.de-tabla {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.de-tabla th {
    text-align: left;
    font-size: 0.7rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-weight: 600;
    opacity: 0.75;
    padding: 6px 8px;
    border-bottom: 1px solid var(--de-borde);
}
.de-tabla td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--de-borde);
    vertical-align: top;
}
.de-nowrap {
    white-space: nowrap;
}
.de-tipo {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.de-sin_respuesta {
    background: var(--de-rojo-fondo);
    color: var(--de-rojo);
}
.de-degradado,
.de-perdida_alta,
.de-latencia_alta {
    background: var(--de-ambar-fondo);
    color: var(--de-ambar);
}
.de-pausado {
    background: var(--de-pausado-fondo);
    color: var(--de-pausado);
}
.de-sin_datos {
    background: var(--de-gris-fondo);
    color: var(--de-gris);
}
.de-pie {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.de-boton {
    min-height: 44px;
}
</style>
