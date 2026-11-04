<template>
    <q-card flat bordered class="do" :dark="darkMode">
        <q-card-section class="do-encabezado">
            <div class="text-subtitle2 text-weight-bold">Origen de los datos</div>
        </q-card-section>
        <q-card-section class="do-cuerpo">
            <dl class="do-lista">
                <div class="do-fila">
                    <dt>IPs de monitoreo</dt>
                    <dd>
                        <div v-for="ip in origen.ips" :key="ip.ip">
                            {{ ip.etiqueta || "Equipo" }} · <span class="do-mono">{{ ip.ip }}</span>
                        </div>
                        <div v-if="!origen.ips.length">Ninguna marcada para monitorear</div>
                    </dd>
                </div>
                <div class="do-fila">
                    <dt>Latencia y pérdida</dt>
                    <dd>ICMP desde el MikroTik · {{ origen.paquetes_por_minuto }} paquetes por minuto</dd>
                </div>
                <div class="do-fila">
                    <dt>Tráfico</dt>
                    <dd>
                        <div>MikroTik · VLAN dedicada del cliente</div>
                        <div class="do-tenue">{{ routerEInterfaz }}</div>
                    </dd>
                </div>
                <div class="do-fila">
                    <dt>Colector</dt>
                    <dd>
                        <span class="do-punto" :class="origen.colector.operando ? 'do-ok' : 'do-viejo'" aria-hidden="true"></span>
                        {{ colector }}
                        <span v-if="simulado" class="do-tenue">· datos simulados</span>
                    </dd>
                </div>
                <div class="do-fila">
                    <dt>Conservación</dt>
                    <dd>
                        Detalle por minuto: {{ origen.conservacion.minuto_dias }} días · promedios de 5 min:
                        {{ origen.conservacion.promedio_5min_meses }} meses
                    </dd>
                </div>
            </dl>
            <q-btn
                v-if="puedeEditar"
                outline
                no-caps
                color="primary"
                icon="settings_ethernet"
                label="Configurar monitoreo"
                class="do-boton"
                @click="$emit('configurar')"
            />
        </q-card-section>
    </q-card>
</template>

<script>
import { computed, onUnmounted, ref } from "vue";
import { darkMode } from "../../../../../hook/appConfig.js";

// #988 Fase 4, sección 9: de dónde salen los datos. La potencia de la ONT no se muestra (#992).
export default {
    name: "DedicadoOrigen",
    props: {
        origen: { type: Object, required: true },
        simulado: { type: Boolean, default: false },
        puedeEditar: { type: Boolean, default: false },
    },
    emits: ["configurar"],
    setup(props) {
        const ahora = ref(Date.now());
        const reloj = setInterval(() => (ahora.value = Date.now()), 1000);
        onUnmounted(() => clearInterval(reloj));

        const routerEInterfaz = computed(() => {
            const o = props.origen;
            const router = o.router ? `Router ${o.router}` : "Sin router";
            if (o.interfaz) return `${router} · interfaz «${o.interfaz}»`;
            return o.vlan_id ? `${router} · VLAN ${o.vlan_id} (la interfaz se busca por VLAN ID)` : `${router} · sin interfaz ni VLAN`;
        });

        const colector = computed(() => {
            const c = props.origen.colector;
            if (!c.ultimo) return "Sin lecturas recientes";
            const s = Math.max(0, Math.round((ahora.value - c.ultimo) / 1000));
            const hace = s < 60 ? `hace ${s} s` : s < 3600 ? `hace ${Math.floor(s / 60)} min` : s < 86400 ? `hace ${Math.floor(s / 3600)} h` : `hace ${Math.floor(s / 86400)} d`;
            return c.operando && s <= 180 ? `Operando · ${hace}` : `Sin lecturas recientes · la última, ${hace}`;
        });

        return { darkMode, routerEInterfaz, colector };
    },
};
</script>

<style scoped>
.do {
    border-radius: 10px;
}
.do-encabezado {
    padding-bottom: 4px;
}
.do-cuerpo {
    padding-top: 0;
}
.do-lista {
    margin: 0 0 8px;
}
.do-fila {
    display: grid;
    grid-template-columns: minmax(120px, 0.8fr) 2fr;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid rgba(127, 127, 127, 0.18);
    font-size: 0.875rem;
}
.do-fila dt {
    font-size: 0.7rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-weight: 600;
    opacity: 0.75;
    padding-top: 2px;
}
.do-fila dd {
    margin: 0;
}
.do-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.do-tenue {
    opacity: 0.75;
}
.do-punto {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}
.do-ok {
    background: #16a34a;
}
.do-viejo {
    background: #9ca3af;
}
.do-boton {
    min-height: 44px;
}
@media (max-width: 480px) {
    .do-fila {
        grid-template-columns: 1fr;
        gap: 2px;
    }
}
</style>
