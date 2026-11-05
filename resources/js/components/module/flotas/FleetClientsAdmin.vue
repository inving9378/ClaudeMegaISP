<template>
    <div class="flt-cli-wrap">
        <!-- ── Listado ───────────────────────────────────────────── -->
        <template v-if="!clientId">
            <h4 class="mb-3"><i class="bi bi-truck me-2 text-primary"></i>Flotas de clientes</h4>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><input v-model="f.q" class="form-control" placeholder="Buscar por nombre o ID de cliente" @input="debounced"></div>
                <div class="col-md-2">
                    <select v-model="f.gps" class="form-select" @change="load">
                        <option value="">GPS: todos</option><option value="con">Con GPS</option><option value="sin">Sin GPS</option>
                    </select>
                </div>
                <div class="col-auto d-flex align-items-center">
                    <div class="form-check me-3"><input id="fp" v-model="f.promo" type="checkbox" class="form-check-input" @change="load"><label for="fp" class="form-check-label">En promoción</label></div>
                    <div class="form-check"><input id="fa" v-model="f.alertas" type="checkbox" class="form-check-input" @change="load"><label for="fa" class="form-check-label">Con alertas</label></div>
                </div>
            </div>
            <div v-if="loading" class="text-center text-muted py-5"><div class="spinner-border text-primary"></div></div>
            <div v-else-if="!rows.length" class="text-center text-muted py-5">No hay clientes con servicio de flotas con esos filtros.</div>
            <div v-else class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <th>Cliente</th><th>Plan / promoción</th><th class="text-center">Unidades</th>
                        <th>GPS</th><th class="text-center">Alertas ({{ alertDays }} d)</th><th>Mensajes del mes</th><th>Última actividad</th><th></th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.client_id">
                            <td><a :href="`${baseUrl}/clientes/${r.client_id}`" class="fw-semibold">{{ r.nombre }}</a><div class="small text-muted">#{{ r.client_id }}</div></td>
                            <td>
                                <span v-if="r.plan">{{ r.plan }}</span><span v-else class="text-muted">Sin plan</span>
                                <span v-if="r.en_promocion" class="badge bg-info ms-1">Promoción<template v-if="r.promo_hasta"> hasta {{ fecha(r.promo_hasta) }}</template></span>
                            </td>
                            <td class="text-center">{{ r.unidades }}</td>
                            <td class="small">
                                <span class="badge bg-success me-1" title="Activos">{{ r.gps_activos }} activos</span>
                                <span class="badge bg-warning text-dark me-1" title="Esperando primera conexión">{{ r.gps_esperando }} esperando</span>
                                <span class="badge bg-danger" title="Sin conexión > 24 h">{{ r.gps_sin_conexion }} sin conexión</span>
                            </td>
                            <td class="text-center"><span class="badge" :class="r.alertas ? 'bg-danger' : 'bg-secondary'">{{ r.alertas }}</span></td>
                            <td class="small" style="min-width:150px">
                                <div>WhatsApp {{ r.msj_whatsapp }}/{{ r.tope_whatsapp }} · Correo {{ r.msj_correo }}</div>
                                <div class="progress" style="height:6px" :title="`${r.tope_pct}% del tope`">
                                    <div class="progress-bar" :class="r.tope_pct >= 100 ? 'bg-danger' : r.tope_pct >= 80 ? 'bg-warning' : 'bg-success'" :style="{ width: r.tope_pct + '%' }"></div>
                                </div>
                                <span v-if="r.tope_pct >= 100" class="badge bg-danger mt-1">Tope alcanzado</span>
                                <span v-else-if="r.tope_pct >= 80" class="badge bg-warning text-dark mt-1">Cerca del tope</span>
                            </td>
                            <td class="small">{{ r.ultima_actividad ? fecha(r.ultima_actividad) : '—' }}</td>
                            <td><a :href="`${baseUrl}/clientes/${r.client_id}`" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── Detalle ───────────────────────────────────────────── -->
        <template v-else>
            <div v-if="loading" class="text-center text-muted py-5"><div class="spinner-border text-primary"></div></div>
            <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
            <template v-else-if="d">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h4 class="mb-0">{{ d.cliente.nombre }} <small class="text-muted">#{{ d.cliente.id }}</small></h4>
                    <div>
                        <a :href="`${baseUrl}/clientes`" class="btn btn-outline-secondary me-2">Volver</a>
                        <a :href="`/cliente/editar/${d.cliente.id}`" class="btn btn-outline-primary">Ficha del cliente</a>
                    </div>
                </div>
                <div v-if="d.suscripcion" class="alert alert-light border small">
                    Plan <strong>{{ d.suscripcion.plan }}</strong> · {{ d.suscripcion.status }}
                    <span v-if="d.suscripcion.is_promo" class="badge bg-info ms-1">Promoción</span>
                    · {{ d.vehicles.length }} unidades
                </div>
                <div ref="mapEl" class="flt-cli-map mb-3"></div>

                <h6>Unidades y GPS</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Unidad</th><th>Placas</th><th>GPS</th><th>Última posición</th><th v-if="canManage"></th></tr></thead>
                        <tbody>
                            <tr v-if="!d.vehicles.length"><td colspan="5" class="text-muted">Este cliente no tiene unidades.</td></tr>
                            <tr v-for="v in d.vehicles" :key="v.id">
                                <td>{{ v.brand }} {{ v.model }}</td><td>{{ v.plates }}</td>
                                <td><span v-if="v.gps" class="badge" :class="v.gps.status === 'active' ? 'bg-success' : 'bg-warning text-dark'">{{ v.gps.status }}</span><span v-else class="text-muted">Sin GPS</span></td>
                                <td class="small">{{ v.last_position ? fecha(v.last_position.recorded_at) : '—' }}</td>
                                <td v-if="canManage" class="text-end">
                                    <button v-if="v.gps" class="btn btn-sm btn-outline-danger" @click="quitarGps(v)">Quitar GPS</button>
                                    <button v-else class="btn btn-sm btn-outline-primary" @click="vincularGps(v)">Vincular GPS</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6>Historial de alertas</h6>
                        <div v-if="!d.eventos.length" class="text-muted small mb-3">Sin alertas.</div>
                        <ul v-else class="list-group mb-3">
                            <li v-for="e in d.eventos" :key="e.id" class="list-group-item small">
                                {{ e.event_type === 'enter' ? 'Entró a' : 'Salió de' }} <strong>{{ e.geocerca }}</strong> — {{ e.brand }} {{ e.model }} {{ e.plates }}
                                <span class="text-muted float-end">{{ fecha(e.occurred_at) }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Geocercas y reglas</h6>
                        <div class="small mb-2">Geocercas: <span v-if="!d.geocercas.length" class="text-muted">ninguna</span><span v-for="g in d.geocercas" :key="g.id" class="badge bg-light text-dark border me-1">{{ g.name }}</span></div>
                        <div class="small mb-3">Reglas: <span v-if="!d.reglas.length" class="text-muted">ninguna</span><span v-for="g in d.reglas" :key="g.id" class="badge bg-light text-dark border me-1">{{ g.name }}</span></div>
                        <h6>Bitácora de consultas</h6>
                        <ul class="list-group">
                            <li v-for="(b, i) in d.bitacora" :key="i" class="list-group-item small">{{ b.usuario || 'Sistema' }} — {{ b.action }}<span class="text-muted float-end">{{ fecha(b.created_at) }}</span></li>
                        </ul>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>

<script>
import axios from 'axios';
import L from 'leaflet';
import { ref, onMounted, nextTick } from 'vue';

export default {
    name: 'FleetClientsAdmin',
    props: {
        baseUrl:  { type: String, default: '/flotas' },
        clientId: { type: Number, default: null },
    },
    setup(props) {
        const loading = ref(true);
        const error = ref('');
        const rows = ref([]);
        const alertDays = ref(7);
        const d = ref(null);
        const mapEl = ref(null);
        const canManage = ref(true); // el backend valida fleet.clients.manage (403)
        const f = ref({ q: '', gps: '', promo: false, alertas: false });
        let timer = null;

        const fecha = (v) => (v ? new Date(v).toLocaleString('es-MX') : '—');
        const debounced = () => { clearTimeout(timer); timer = setTimeout(load, 350); };

        async function load() {
            loading.value = true;
            try {
                if (props.clientId) {
                    const { data } = await axios.get(`${props.baseUrl}/api/clientes-flota/${props.clientId}`);
                    d.value = data;
                    loading.value = false;
                    await nextTick();
                    initMap();
                    return;
                }
                const params = { q: f.value.q, gps: f.value.gps, promo: f.value.promo ? 1 : 0, alertas: f.value.alertas ? 1 : 0 };
                const { data } = await axios.get(`${props.baseUrl}/api/clientes-flota`, { params });
                rows.value = data.clientes;
                alertDays.value = data.alert_days;
            } catch (e) {
                error.value = e.response?.data?.message || 'No se pudo cargar.';
            }
            loading.value = false;
        }

        function initMap() {
            if (!mapEl.value) return;
            const map = L.map(mapEl.value).setView([19.43, -99.13], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(map);
            const pts = [];
            d.value.vehicles.forEach((v) => {
                const p = v.last_position;
                if (!p) return;
                pts.push([p.lat, p.lng]);
                L.circleMarker([p.lat, p.lng], { radius: 8, color: '#0d6efd' }).addTo(map).bindPopup(`${v.brand} ${v.model} ${v.plates}`);
            });
            if (pts.length) map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
        }

        async function vincularGps(v) {
            const imei = window.prompt('IMEI del GPS (Ruptela):');
            if (!imei) return;
            try {
                await axios.post(`${props.baseUrl}/api/clientes-flota/${props.clientId}/vehiculos/${v.id}/gps`, { brand: 'ruptela', imei });
                load();
            } catch (e) { window.alert(e.response?.data?.errors?.imei?.[0] || e.response?.data?.message || 'Error'); }
        }
        async function quitarGps(v) {
            if (!window.confirm('¿Quitar el GPS de esta unidad?')) return;
            try { await axios.delete(`${props.baseUrl}/api/clientes-flota/${props.clientId}/vehiculos/${v.id}/gps`); load(); }
            catch (e) { window.alert(e.response?.data?.message || 'Error'); }
        }

        onMounted(load);
        return { loading, error, rows, alertDays, d, mapEl, canManage, f, fecha, debounced, load, vincularGps, quitarGps };
    },
};
</script>

<style scoped>
.flt-cli-map { height: 340px; border-radius: 8px; border: 1px solid #dee2e6; }
</style>
