<template>
    <div class="d-inline-block">
        <!-- Engrane de la cabecera: visible desde todas las pestañas. -->
        <button class="btn header-item position-relative" type="button"
                :title="tituloBoton" @click="abrir">
            <i class="fas fa-cog" :class="{ 'text-danger': hayBloqueo, 'fa-spin': cargando }"></i>
            <span v-if="hayBloqueo"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ rojos }}
            </span>
        </button>

        <!-- Tablero -->
        <div v-if="visible" class="tc-backdrop" @click.self="cerrar">
            <div class="tc-panel">
                <div class="tc-head">
                    <div>
                        <div class="tc-linea" :class="corriendo ? 'tc-ok' : 'tc-stop'">
                            {{ linea }}
                        </div>
                        <div class="tc-sub">
                            Medido {{ medidoTxt }}
                            <span v-if="snapshot && snapshot.edad_seg !== null">
                                · lectura del SO hace {{ snapshot.edad_seg }}s
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" @click="cargar" :disabled="cargando">
                            <i class="fas fa-sync" :class="{ 'fa-spin': cargando }"></i> Volver a medir
                        </button>
                        <button class="btn btn-sm" :class="pestana === 'permisos' ? 'btn-secondary' : 'btn-outline-secondary'"
                                @click="togglePermisos">
                            <i class="fas fa-key"></i> Permisos
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" @click="verBitacora = !verBitacora">
                            <i class="fas fa-history"></i> Bitácora
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" @click="cerrar">Cerrar</button>
                    </div>
                </div>

                <!-- Bitácora (regla 4) -->
                <div v-if="verBitacora" class="tc-bitacora">
                    <div v-if="!cambios.length" class="text-muted small">Sin cambios registrados todavía.</div>
                    <table v-else class="table table-sm mb-0">
                        <thead><tr><th>Cuándo</th><th>Quién</th><th>Compuerta</th><th>De</th><th>A</th></tr></thead>
                        <tbody>
                            <tr v-for="c in cambios" :key="c.id">
                                <td class="text-nowrap small">{{ c.cuando }}</td>
                                <td class="small">{{ c.quien }}</td>
                                <td class="small">{{ c.compuerta }} / {{ c.accion }}</td>
                                <td class="small text-muted">{{ c.de || '—' }}</td>
                                <td class="small">{{ c.a || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pestaña de permisos del circuito -->
                <div v-if="pestana === 'permisos'" class="tc-tabla">
                    <p class="small text-muted mb-2">
                        La recomendación es una sugerencia. Tu decisión se guarda aunque la contradiga,
                        con fecha y autor, y queda marcada como tal.
                    </p>
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Permiso</th><th>Qué habilita</th>
                                <th v-for="r in roles" :key="r">{{ r }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in permisos" :key="p.permiso">
                                <td>
                                    <code class="small">{{ p.permiso }}</code>
                                    <div v-if="!p.existe" class="text-danger" style="font-size:.68rem">no existe en permissions</div>
                                </td>
                                <td style="max-width:340px">
                                    <div class="small">{{ p.habilita }}</div>
                                    <div class="text-muted" style="font-size:.7rem">{{ p.consecuencia }}</div>
                                </td>
                                <td v-for="r in roles" :key="r">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               :checked="p.roles[r].concedido"
                                               :disabled="!puedeEditar"
                                               @change.prevent="pedirTogglePermiso(p, r, $event)">
                                    </div>
                                    <span class="tc-rec" :class="'rec-' + p.roles[r].recomendacion">
                                        {{ p.roles[r].recomendacion }}
                                    </span>
                                    <div v-if="p.roles[r].contradice" class="text-warning" style="font-size:.68rem">
                                        ⚠ contradice la recomendación
                                    </div>
                                    <div v-if="p.roles[r].decision" class="text-muted" style="font-size:.66rem">
                                        {{ p.roles[r].decision.por }} · {{ (p.roles[r].decision.cuando || '').substring(0,16) }}
                                        <span v-if="p.roles[r].decision.contradijo">· contra la recomendación</span>
                                    </div>
                                    <div v-if="!puedeEditar" class="tc-motivo tc-m-sin_permiso">
                                        <span class="tc-etiqueta">TE FALTA PERMISO</span>
                                        <span class="tc-permiso">torre.config.edit</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Filas: una por compuerta, seis columnas -->
                <div v-if="pestana === 'compuertas'" class="tc-tabla">
                    <div class="tc-fila tc-cabecera">
                        <div></div><div>Compuerta</div><div>Valor real</div>
                        <div>Origen</div><div>Por qué</div><div>Acción</div>
                    </div>

                    <div v-for="c in compuertas" :key="c.clave" class="tc-fila" :class="'tc-' + c.semaforo">
                        <div><span class="tc-luz" :class="'luz-' + c.semaforo"></span></div>
                        <div class="tc-nombre">{{ c.nombre }}</div>
                        <div class="tc-valor">{{ c.valor }}</div>
                        <div><span class="tc-origen" :class="'org-' + c.origen">{{ etiquetaOrigen(c.origen) }}</span></div>
                        <div class="tc-porque">{{ c.por_que || '—' }}</div>
                        <div class="tc-accion">
                            <!-- Acción ejecutable. Deshabilitada NUNCA va muda: lleva su motivo. -->
                            <div v-for="a in c.acciones" :key="a.clave" class="mb-1">
                                <select v-if="a.opciones && a.disponible" v-model="valorSel[c.clave]"
                                        class="form-select form-select-sm mb-1">
                                    <option v-for="o in a.opciones" :key="o" :value="o">{{ o }}</option>
                                </select>
                                <button class="btn btn-sm"
                                        :class="a.peligrosa ? 'btn-outline-danger' : 'btn-outline-primary'"
                                        :disabled="!a.disponible"
                                        @click="a.disponible && pedirConfirmacion(c, a)">
                                    {{ a.etiqueta }}
                                </button>
                                <div v-if="!a.disponible" class="tc-motivo tc-m-permiso">{{ a.motivo }}</div>
                            </div>

                            <!-- Por qué está gris, cuando no hay ninguna acción usable (punto 1) -->
                            <div v-if="c.control !== 'disponible' && c.control !== 'sin_necesidad'"
                                 class="tc-motivo" :class="'tc-m-' + c.control">
                                <span class="tc-etiqueta">{{ etiquetaControl(c.control) }}</span>
                                {{ c.control_motivo }}
                                <span v-if="c.permiso_faltante" class="tc-permiso">{{ c.permiso_faltante }}</span>
                            </div>

                            <!-- Regla 2: lo que necesita sudo se muestra igual, listo para copiar -->
                            <div v-if="c.comando" class="tc-cmd">
                                <code @click="copiar(c.comando)" :title="'Clic para copiar'">{{ c.comando }}</code>
                                <div v-if="c.quien_puede" class="tc-quien">Puede: {{ c.quien_puede }}</div>
                            </div>

                            <div v-if="c.sin_salida" class="text-danger small fw-bold">
                                ⚠ Rojo sin salida — falta definir acción o comando
                            </div>
                            <span v-if="!c.acciones.length && !c.comando && c.control === 'sin_necesidad'"
                                  class="text-muted small">—</span>
                        </div>
                    </div>
                </div>

                <!-- Segundo paso: confirmación (regla 3) -->
                <div v-if="pendiente" class="tc-confirm-back" @click.self="pendiente = null">
                    <div class="tc-confirm">
                        <h6 class="mb-2">{{ pendiente.accion.etiqueta }} — ¿confirmas?</h6>
                        <p class="mb-2 small"><strong>Compuerta:</strong> {{ pendiente.compuerta.nombre }}</p>
                        <p class="mb-3 small">{{ pendiente.accion.confirmar }}</p>
                        <p v-if="pendiente.accion.opciones" class="mb-3 small">
                            <strong>Nuevo valor:</strong> {{ valorSel[pendiente.compuerta.clave] }}
                        </p>
                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-sm btn-secondary" @click="pendiente = null">Cancelar</button>
                            <button class="btn btn-sm btn-danger" @click="ejecutar" :disabled="enviando">
                                Sí, hacerlo
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="mensaje" class="tc-msg" :class="mensajeOk ? 'ok' : 'err'">{{ mensaje }}</div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'TorreCompuertas',
    data() {
        return {
            visible: false, cargando: false, enviando: false,
            linea: '', corriendo: true, compuertas: [], snapshot: null, medidoEn: null,
            pendiente: null, mensaje: '', mensajeOk: true,
            verBitacora: false, cambios: [], valorSel: {},
            pestana: 'compuertas', permisos: [], roles: [], puedeEditar: false,
            timer: null,
        };
    },
    computed: {
        rojos() { return this.compuertas.filter(c => c.semaforo === 'rojo').length; },
        hayBloqueo() { return this.rojos > 0; },
        tituloBoton() { return this.linea || 'Tablero de compuertas de la Torre'; },
        medidoTxt() {
            if (!this.medidoEn) return '—';
            return new Date(this.medidoEn).toLocaleTimeString();
        },
    },
    mounted() {
        // Una medición al cargar, para que el engrane ya avise sin abrir el panel.
        this.cargar();
    },
    beforeUnmount() { this.detenerPolling(); },
    methods: {
        async togglePermisos() {
            this.pestana = this.pestana === 'permisos' ? 'compuertas' : 'permisos';
            if (this.pestana === 'permisos') await this.cargarPermisos();
        },
        async cargarPermisos() {
            try {
                const { data } = await axios.get('/api/roadmap/torre/compuertas/permisos');
                this.permisos = data.permisos;
                this.roles = data.roles;
                this.puedeEditar = data.puede_editar;
            } catch (e) {
                this.mostrar(e?.response?.data?.message || 'No se pudieron leer los permisos.', false);
            }
        },
        pedirTogglePermiso(p, rol, ev) {
            // El input no cambia solo: primero se confirma (regla 3), después se recarga
            // desde el servidor. Así la pantalla nunca muestra un estado que no se guardó.
            const conceder = ev.target.checked;
            ev.target.checked = p.roles[rol].concedido;
            const rec = p.roles[rol].recomendacion;
            const contradice = (rec === 'conceder' && !conceder) || (rec === 'negar' && conceder);
            this.pendiente = {
                compuerta: { clave: 'permisos', nombre: `${p.permiso} · ${rol}` },
                accion: {
                    clave: 'permiso_toggle',
                    etiqueta: conceder ? 'Conceder permiso' : 'Revocar permiso',
                    confirmar: `${p.consecuencia} ${p.porque}` +
                        (contradice ? ` ⚠ Esto CONTRADICE la recomendación (${rec}); se guardará marcado como tal.` : ''),
                    permisoDatos: { permiso: p.permiso, rol, conceder },
                },
            };
        },
        etiquetaControl(c) {
            return {
                sin_privilegio: 'EL PANEL NO PUEDE',
                sin_permiso: 'TE FALTA PERMISO',
                no_implementado: 'NO IMPLEMENTADO',
            }[c] || '';
        },
        etiquetaOrigen(o) {
            return { bd: 'base de datos', config: 'archivo de config', env: '.env', so: 'sistema operativo', 'bd+so': 'BD + sistema' }[o] || o;
        },
        abrir() {
            this.visible = true;
            this.cargar();
            // Mientras el panel está abierto se re-mide solo: el tablero debe mostrar
            // el presente, no la foto de cuando se abrió.
            this.timer = setInterval(this.cargar, 15000);
        },
        cerrar() { this.visible = false; this.detenerPolling(); },
        detenerPolling() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
        async cargar() {
            this.cargando = true;
            try {
                const { data } = await axios.get('/api/roadmap/torre/compuertas');
                this.linea = data.linea;
                this.corriendo = data.corriendo;
                this.compuertas = data.compuertas;
                this.snapshot = data.snapshot;
                this.medidoEn = data.medido_en;
                this.compuertas.forEach(c => {
                    (c.acciones || []).forEach(a => {
                        if (a.opciones && this.valorSel[c.clave] === undefined) {
                            this.valorSel[c.clave] = a.opciones[0];
                        }
                    });
                });
                if (this.verBitacora) this.cargarBitacora();
            } catch (e) {
                this.mostrar(e?.response?.data?.message || 'No se pudo medir el tablero.', false);
            } finally {
                this.cargando = false;
            }
        },
        async cargarBitacora() {
            try {
                const { data } = await axios.get('/api/roadmap/torre/compuertas/bitacora');
                this.cambios = data.cambios;
            } catch (e) { /* la bitácora no debe romper el tablero */ }
        },
        pedirConfirmacion(compuerta, accion) {
            this.pendiente = { compuerta, accion };
        },
        async ejecutar() {
            if (!this.pendiente) return;
            this.enviando = true;
            const { compuerta, accion } = this.pendiente;
            try {
                if (accion.permisoDatos) {
                    const { data } = await axios.post('/api/roadmap/torre/compuertas/permisos', {
                        ...accion.permisoDatos, confirmado: true,
                    });
                    this.mostrar(data.mensaje, data.ok);
                    this.pendiente = null;
                    await this.cargarPermisos();
                    if (this.verBitacora) await this.cargarBitacora();
                    return;
                }
                const { data } = await axios.post('/api/roadmap/torre/compuertas/accion', {
                    accion: accion.clave,
                    confirmado: true,
                    valor: this.valorSel[compuerta.clave] || null,
                    ids: accion.ids || null,
                });
                this.mostrar(data.mensaje, data.ok);
                this.pendiente = null;
                await this.cargar();
                if (this.verBitacora) await this.cargarBitacora();
            } catch (e) {
                this.mostrar(e?.response?.data?.mensaje || 'La acción no se pudo completar.', false);
            } finally {
                this.enviando = false;
            }
        },
        copiar(txt) {
            navigator.clipboard?.writeText(txt).then(
                () => this.mostrar('Comando copiado.', true),
                () => this.mostrar('No se pudo copiar; selecciónalo a mano.', false),
            );
        },
        mostrar(m, ok) {
            this.mensaje = m; this.mensajeOk = ok;
            setTimeout(() => { this.mensaje = ''; }, 6000);
        },
    },
};
</script>

<style scoped>
.tc-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1090;
    display: flex; align-items: flex-start; justify-content: center; padding: 3vh 2vw; }
.tc-panel { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529);
    border-radius: .5rem; width: 100%; max-width: 1500px; max-height: 94vh; overflow: auto;
    box-shadow: 0 1rem 3rem rgba(0,0,0,.4); position: relative; }
.tc-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    position: sticky; top: 0; background: inherit; z-index: 2; }
.tc-linea { font-size: 1.35rem; font-weight: 700; letter-spacing: .01em; }
.tc-ok { color: #198754; } .tc-stop { color: #dc3545; }
.tc-sub { font-size: .78rem; opacity: .7; }
.tc-tabla { padding: .5rem 1.25rem 1.25rem; }
.tc-fila { display: grid; grid-template-columns: 34px 1.5fr 1.7fr .8fr 2.2fr 1.8fr;
    gap: .6rem; padding: .6rem .25rem; border-bottom: 1px solid var(--bs-border-color, #eee);
    align-items: start; font-size: .84rem; }
.tc-cabecera { font-weight: 600; text-transform: uppercase; font-size: .68rem; opacity: .6; }
.tc-rojo { background: rgba(220,53,69,.07); }
.tc-ambar { background: rgba(255,193,7,.07); }
.tc-luz { display: inline-block; width: 13px; height: 13px; border-radius: 50%; margin-top: .18rem; }
.luz-verde { background: #198754; } .luz-rojo { background: #dc3545; } .luz-ambar { background: #ffc107; }
.tc-nombre { font-weight: 600; }
.tc-valor { font-variant-numeric: tabular-nums; }
.tc-origen { font-size: .68rem; padding: .1rem .4rem; border-radius: .2rem;
    background: rgba(120,120,120,.16); white-space: nowrap; }
.tc-porque { opacity: .85; }
.tc-motivo { font-size: .72rem; margin: .25rem 0; padding: .3rem .45rem; border-radius: .25rem;
    line-height: 1.35; }
.tc-etiqueta { display: inline-block; font-weight: 700; font-size: .62rem; letter-spacing: .04em;
    margin-right: .3rem; }
.tc-m-sin_privilegio { background: rgba(13,110,253,.13); }
.tc-m-sin_permiso, .tc-m-permiso { background: rgba(220,53,69,.13); }
.tc-m-no_implementado { background: rgba(120,120,120,.15); font-style: italic; }
.tc-permiso { font-family: monospace; background: rgba(0,0,0,.12); padding: 0 .25rem; border-radius: .2rem; }
.tc-rec { font-size: .66rem; padding: .08rem .35rem; border-radius: .2rem; text-transform: uppercase; }
.rec-conceder { background: rgba(25,135,84,.18); }
.rec-revisar { background: rgba(255,193,7,.22); }
.rec-negar { background: rgba(220,53,69,.18); }
.tc-cmd code { display: block; font-size: .72rem; background: rgba(120,120,120,.14);
    padding: .3rem .4rem; border-radius: .25rem; cursor: pointer; word-break: break-all; }
.tc-quien { font-size: .68rem; opacity: .7; margin-top: .2rem; }
.tc-bitacora { padding: .5rem 1.25rem; max-height: 240px; overflow: auto;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6); }
.tc-confirm-back { position: absolute; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 3; }
.tc-confirm { background: var(--bs-body-bg, #fff); border-radius: .5rem; padding: 1.25rem;
    max-width: 460px; box-shadow: 0 .5rem 2rem rgba(0,0,0,.4); }
.tc-msg { position: sticky; bottom: 0; padding: .6rem 1.25rem; font-size: .85rem; }
.tc-msg.ok { background: rgba(25,135,84,.15); } .tc-msg.err { background: rgba(220,53,69,.15); }
@media (max-width: 1200px) {
    .tc-fila { grid-template-columns: 26px 1fr 1fr; }
    .tc-fila > div:nth-child(4), .tc-cabecera > div:nth-child(4) { grid-column: 2 / 4; }
    .tc-porque, .tc-accion { grid-column: 1 / 4; }
}
</style>
