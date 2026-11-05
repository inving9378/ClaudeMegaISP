<template>
    <div class="plr" aria-live="polite">
        <div class="plr-titulo">
            <q-icon :name="r.ok ? 'check_circle' : 'error'" :color="r.ok ? 'positive' : 'negative'" size="20px" />
            {{ r.ok ? "La lectura funciona" : "La lectura tiene problemas" }}
            <span class="plr-tenue">· sin guardar lecturas · tardó {{ segundos }} s</span>
        </div>

        <q-banner v-if="r.simulado" dense rounded class="plr-aviso q-mb-sm">
            <template #avatar><q-icon name="science" /></template>
            Datos simulados: en este entorno no se conecta a ningún router real.
        </q-banner>
        <q-banner v-if="r.router.redirigido" dense rounded class="plr-aviso q-mb-sm">
            <template #avatar><q-icon name="warning" color="warning" /></template>
            Redirigido al laboratorio: no es el router registrado ({{ r.router.host_registrado }}).
        </q-banner>

        <dl class="plr-datos">
            <div>
                <dt>Router</dt>
                <dd>{{ r.router.titulo || "—" }}</dd>
            </div>
            <div>
                <dt>Se conectó a</dt>
                <dd class="plr-mono">{{ r.router.host_real || "—" }}</dd>
            </div>
            <div v-if="r.usuario">
                <dt>Usuario</dt>
                <dd>{{ r.usuario.nombre }} <span class="plr-tenue">({{ tipoUsuario }})</span></dd>
            </div>
            <div v-if="r.identidad">
                <dt>Identidad</dt>
                <dd>
                    Contestó «{{ r.identidad.contesto }}» <span class="plr-tenue">{{ textoIdentidad }}</span>
                    <div v-if="r.identidad.puede_confirmar" class="q-mt-xs">
                        <q-btn color="primary" no-caps dense padding="4px 12px" label="Sí, es este router" :loading="confirmando" @click="$emit('confirmar')" />
                    </div>
                </dd>
            </div>
            <div v-if="r.interfaz">
                <dt>Interfaz</dt>
                <dd>
                    <template v-if="r.interfaz.encontrada">
                        {{ r.interfaz.nombres.join(", ") }}
                        <span class="plr-tenue">(por {{ r.interfaz.por === "nombre" ? "nombre" : "VLAN ID" }}{{ r.interfaz.activa === false ? ", apagada" : "" }})</span>
                    </template>
                    <template v-else-if="r.interfaz.varias">Hay varias: {{ r.interfaz.nombres.join(", ") }}</template>
                    <template v-else>No encontrada</template>
                </dd>
            </div>
            <div v-if="r.trafico">
                <dt>Tráfico ahora <span class="plr-tenue">(en {{ r.trafico.segundos }} s)</span></dt>
                <dd>Bajada del cliente {{ mbps(r.trafico.bajada_bps) }} · Subida del cliente {{ mbps(r.trafico.subida_bps) }}</dd>
            </div>
        </dl>

        <div v-if="r.pings.length" class="plr-tabla">
            <q-markup-table dense flat bordered :dark="darkMode">
                <thead>
                    <tr>
                        <th class="text-left">IP</th>
                        <th class="text-left">Equipo</th>
                        <th class="text-left">Latencia</th>
                        <th class="text-left">Pérdida</th>
                        <th class="text-left">Ping</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in r.pings" :key="p.ip">
                        <td class="text-left plr-mono">{{ p.ip }}</td>
                        <td class="text-left">{{ p.etiqueta || "—" }}</td>
                        <td class="text-left">{{ ms(p.latencia_ms) }}</td>
                        <td class="text-left">{{ pct(p.perdida_pct) }}</td>
                        <td class="text-left">{{ ESTADOS_PING[p.estado] || p.estado }}</td>
                    </tr>
                </tbody>
            </q-markup-table>
        </div>

        <ul v-if="r.fallas.length" class="plr-fallas">
            <li v-for="f in r.fallas" :key="`${f.codigo}-${f.mensaje}`">
                <strong>{{ f.mensaje }}</strong> {{ f.causa }}
            </li>
        </ul>
    </div>
</template>

<script>
import { computed } from "vue";
import { darkMode } from "../../../../../hook/appConfig";
import { mbps, ms, pct } from "../../statistics/dedicado/formato.js";

const ESTADOS_PING = { responde: "Responde", sin_respuesta: "Sin respuesta", error: "Error", omitido: "Sin tiempo" };
const TIPOS_USUARIO = {
    simulado: "simulado",
    laboratorio: "del laboratorio",
    solo_lectura: "solo lectura",
    principal: "principal: el router no tiene usuario de solo lectura",
};

// #988: lo que encontró «Probar lectura», con la causa probable de cada falla.
export default {
    name: "ProbarLecturaResultado",
    props: {
        r: { type: Object, required: true },
        confirmando: { type: Boolean, default: false },
    },
    emits: ["confirmar"],
    setup(props) {
        const segundos = computed(() => (props.r.duracion_ms / 1000).toLocaleString("es-MX", { maximumFractionDigits: 1 }));
        const tipoUsuario = computed(() => TIPOS_USUARIO[props.r.usuario?.tipo] || props.r.usuario?.tipo);
        const textoIdentidad = computed(() => {
            const i = props.r.identidad;
            if (i.guardada_sola) return "· igual al título del router: se guardó sola";
            if (i.estado === "coincide") return "· es la que se espera";
            if (i.estado === "distinta") return `· se esperaba «${i.esperada}»`;
            return "· no es el título del router";
        });

        return { darkMode, ESTADOS_PING, segundos, tipoUsuario, textoIdentidad, mbps, ms, pct };
    },
};
</script>

<style scoped>
.plr {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(128, 128, 128, 0.3);
}
.plr-titulo {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-weight: 600;
    margin-bottom: 8px;
}
.plr-tenue {
    font-weight: 400;
    opacity: 0.72;
}
.plr-aviso {
    background: rgba(245, 158, 11, 0.12);
}
.plr-datos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 10px 16px;
    margin: 0 0 10px;
}
.plr-datos dt {
    font-weight: 500;
    font-size: 0.8rem;
    opacity: 0.75;
}
.plr-datos dd {
    margin: 2px 0 0;
    overflow-wrap: anywhere;
}
.plr-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.plr-tabla {
    overflow-x: auto;
}
.plr-fallas {
    margin: 10px 0 0;
    padding-left: 18px;
    color: var(--q-negative);
}
.plr-fallas li {
    margin-bottom: 4px;
}
</style>
