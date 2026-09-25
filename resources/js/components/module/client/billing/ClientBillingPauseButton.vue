<template>
    <div class="pausa-btn-wrap">
        <button
            v-if="estadoBoton === 'disponible'"
            type="button"
            class="btn pausa-btn-outline"
            @click="abrirModal"
        >
            <i class="fas fa-pause-circle mr-1"></i> Pausar facturación
        </button>

        <button
            v-else-if="estadoBoton === 'programada'"
            type="button"
            class="btn pausa-btn-neutral"
            :disabled="cargandoAccion"
            @click="confirmarCancelar"
        >
            Cancelar pausa programada
        </button>

        <button
            v-else-if="estadoBoton === 'en_curso'"
            type="button"
            class="btn pausa-btn-solid"
            :disabled="cargandoAccion"
            @click="confirmarReanudar"
        >
            Reanudar facturación
        </button>

        <button
            v-else-if="estadoBoton === 'esperando_pago'"
            type="button"
            class="btn pausa-btn-neutral"
            disabled
            :title="`Pausa con cuota de ${pausaViva?.meses} meses · $${pausaViva?.monto_cuota} generados. Se activa al quedar pagado.`"
        >
            Esperando pago de la cuota
        </button>

        <button
            v-else-if="estadoBoton === 'bloqueado'"
            type="button"
            class="btn pausa-btn-disabled"
            disabled
            :title="elegibilidad?.motivo"
        >
            <i class="fas fa-lock mr-1"></i> Pausar facturación
        </button>

        <span v-if="estadoBoton === 'bloqueado'" class="pausa-motivo-inline">{{ elegibilidad?.motivo }}</span>

        <!-- Modal: configurar la pausa -->
        <div v-if="mostrarModal" class="pausa-modal-backdrop" @click.self="cerrarModal">
            <div class="pausa-modal">
                <div class="pausa-modal-header">
                    <div>
                        <div class="pausa-modal-title">Pausar facturación</div>
                        <div class="pausa-modal-subtitle">Cliente #{{ clientId }}</div>
                    </div>
                    <button type="button" class="pausa-modal-close" @click="cerrarModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="pausa-modal-body">
                    <div class="pausa-section-label">TIPO DE PAUSA</div>
                    <div class="pausa-tipo-grid">
                        <button
                            type="button"
                            class="pausa-tipo-card"
                            :class="{ activo: form.tipo === 'sin_cuota' }"
                            @click="form.tipo = 'sin_cuota'"
                        >
                            <div class="pausa-tipo-title">Sin cuota</div>
                            <div class="pausa-tipo-desc">Hasta {{ maxMesesSinCuota }} meses · Servicio suspendido · $0</div>
                        </button>
                        <button
                            type="button"
                            class="pausa-tipo-card"
                            :class="{ activo: form.tipo === 'con_cuota' }"
                            @click="form.tipo = 'con_cuota'"
                        >
                            <div class="pausa-tipo-title">Con cuota de conservación</div>
                            <div class="pausa-tipo-desc">Hasta {{ maxMesesConCuota }} meses · Servicio suspendido · $99/mes por adelantado</div>
                        </button>
                    </div>

                    <div class="pausa-section-label mt-3">DURACIÓN</div>
                    <div class="pausa-meses-grid">
                        <button
                            v-for="n in 6"
                            :key="n"
                            type="button"
                            class="pausa-mes-btn"
                            :class="{ activo: form.meses === n, disabled: n > maxMesesActual }"
                            :disabled="n > maxMesesActual"
                            @click="form.meses = n"
                        >
                            {{ n }} {{ n === 1 ? 'mes' : 'meses' }}
                        </button>
                    </div>
                    <div class="pausa-hint">
                        {{ form.tipo === 'con_cuota'
                            ? `Con cuota: de 1 a ${maxMesesConCuota} meses. Siempre meses completos, sin prorrateo.`
                            : `Sin cuota: máximo ${maxMesesSinCuota} meses. Para más meses elige la cuota de conservación.` }}
                    </div>

                    <div class="pausa-grid-2 mt-3">
                        <div>
                            <div class="pausa-section-label">REQUISITOS</div>
                            <ul class="pausa-checklist">
                                <li v-for="(c, i) in requisitos" :key="i"><i class="fas fa-check pausa-check-icon"></i> {{ c }}</li>
                            </ul>
                        </div>
                        <div>
                            <div class="pausa-section-label">SOLICITUD</div>
                            <label class="pausa-field-label">Motivo
                                <select v-model="form.motivo" class="pausa-select">
                                    <option value="Viaje / ausencia">Viaje / ausencia</option>
                                    <option value="Temporada vacacional">Temporada vacacional</option>
                                    <option value="Económico">Económico</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </label>
                            <label class="pausa-field-label">Canal
                                <select v-model="form.canal" class="pausa-select">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="llamada">Llamada</option>
                                    <option value="oficina">Oficina</option>
                                </select>
                            </label>
                            <label class="pausa-field-label">Evidencia (captura / solicitud firmada)
                                <input type="file" class="pausa-file" @change="onEvidenciaChange" accept=".jpg,.jpeg,.png,.pdf">
                            </label>
                        </div>
                    </div>

                    <div class="pausa-timeline-box mt-3">
                        <div class="pausa-section-label">LÍNEA DE TIEMPO</div>
                        <div class="pausa-timeline-grid">
                            <div>
                                <div class="pausa-timeline-label pausa-color-amber">Pausa · {{ form.meses }} {{ form.meses === 1 ? 'mes' : 'meses' }}</div>
                                <div>{{ formatFecha(fechas.inicio) }} → {{ formatFecha(fechas.fin) }}</div>
                            </div>
                            <div>
                                <div class="pausa-timeline-label pausa-color-purple">Facturación mínima · 6 meses</div>
                                <div>{{ formatFecha(fechas.reanuda) }} → {{ formatFecha(fechas.minFin) }}</div>
                            </div>
                            <div>
                                <div class="pausa-timeline-label pausa-color-green">Nueva pausa disponible</div>
                                <div>desde {{ formatFecha(fechas.proxima) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="pausa-resumen-grid mt-3">
                        <div class="pausa-resumen-card">
                            <div class="pausa-resumen-label">Servicio durante la pausa</div>
                            <div class="pausa-resumen-valor">Suspendido</div>
                        </div>
                        <div class="pausa-resumen-card">
                            <div class="pausa-resumen-label">Cobro anticipado</div>
                            <div class="pausa-resumen-valor">{{ form.tipo === 'con_cuota' ? `${form.meses} × $99 = $${form.meses * 99}` : '$0' }}</div>
                        </div>
                        <div class="pausa-resumen-card">
                            <div class="pausa-resumen-label">Fin de contrato se recorre</div>
                            <div class="pausa-resumen-valor">+{{ form.meses }} {{ form.meses === 1 ? 'mes' : 'meses' }}</div>
                        </div>
                    </div>
                </div>

                <div class="pausa-modal-footer">
                    <div class="pausa-footnote">
                        {{ form.tipo === 'con_cuota'
                            ? 'El servicio se suspende igual. La pausa se activa solo cuando el cobro anticipado queda pagado. Se confirma por WhatsApp.'
                            : 'Se confirma al cliente por WhatsApp. El servicio se suspende al iniciar la pausa y se reactiva solo al terminar.' }}
                    </div>
                    <div class="pausa-footer-actions">
                        <button type="button" class="btn pausa-btn-neutral" @click="cerrarModal">Cancelar</button>
                        <button type="button" class="btn pausa-btn-solid" :disabled="enviando" @click="enviarPausa">
                            {{ enviando ? 'Guardando...' : (form.tipo === 'con_cuota' ? `Generar cobro de $${form.meses * 99}` : 'Programar pausa') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, computed } from "vue";
import Swal from "sweetalert2";
import {
    requestEstadoPausa,
    crearPausa,
    cancelarPausa,
    reanudarPausa,
    pausaRefreshTrigger,
} from "./helpers/request";

export default {
    name: "ClientBillingPauseButton",
    props: {
        clientId: [String, Number],
    },
    setup(props) {
        const elegibilidad = ref(null);
        const pausaViva = ref(null);
        const cargando = ref(true);
        const cargandoAccion = ref(false);
        const mostrarModal = ref(false);
        const enviando = ref(false);

        const form = reactive({
            tipo: "sin_cuota",
            meses: 1,
            motivo: "Viaje / ausencia",
            canal: "whatsapp",
            evidencia: null,
        });

        const maxMesesSinCuota = computed(() => elegibilidad.value?.maxMesesSinCuota ?? 0);
        const maxMesesConCuota = computed(() => elegibilidad.value?.maxMesesConCuota ?? 0);
        const maxMesesActual = computed(() =>
            form.tipo === "con_cuota" ? maxMesesConCuota.value : maxMesesSinCuota.value
        );

        const requisitos = computed(() => [
            "Cliente activo",
            "Sin saldo pendiente",
            "6+ meses facturados desde la última pausa",
            `Pausas en los últimos 12 meses: ${elegibilidad.value?.pausasEn12Meses ?? 0} de 2`,
            `Meses pausados en 12 meses: ${elegibilidad.value?.mesesPausadosEn12Meses ?? 0} de 6`,
            "Sin otra pausa programada",
        ]);

        const estadoBoton = computed(() => {
            if (cargando.value) return "cargando";
            if (pausaViva.value) {
                if (pausaViva.value.estado === "esperando_pago") return "esperando_pago";
                if (pausaViva.value.estado === "programada") return "programada";
                if (pausaViva.value.estado === "en_curso") return "en_curso";
            }
            if (elegibilidad.value?.elegible) return "disponible";
            return "bloqueado";
        });

        const cargarEstado = async () => {
            cargando.value = true;
            try {
                const data = await requestEstadoPausa(props.clientId);
                elegibilidad.value = data.elegibilidad;
                pausaViva.value = data.pausa_viva;
            } catch (e) {
                console.error("No se pudo cargar el estado de la pausa de facturación", e);
            } finally {
                cargando.value = false;
            }
        };

        onMounted(cargarEstado);

        const abrirModal = () => {
            form.tipo = maxMesesSinCuota.value > 0 ? "sin_cuota" : "con_cuota";
            form.meses = 1;
            form.motivo = "Viaje / ausencia";
            form.canal = "whatsapp";
            form.evidencia = null;
            mostrarModal.value = true;
        };

        const cerrarModal = () => {
            mostrarModal.value = false;
        };

        const onEvidenciaChange = (e) => {
            form.evidencia = e.target.files[0] || null;
        };

        const parseFechaCorte = () => {
            // fecha_corte del cliente no viaja en este endpoint (solo elegibilidad) — la
            // línea de tiempo es orientativa a partir de HOY, la fecha real la fija el
            // backend en BillingPauseService::crearPausa() (día siguiente a fecha_corte).
            return new Date();
        };

        const fechas = computed(() => {
            const base = parseFechaCorte();
            const inicio = new Date(base);
            inicio.setDate(inicio.getDate() + 1);
            const fin = new Date(inicio);
            fin.setMonth(fin.getMonth() + form.meses);
            fin.setDate(fin.getDate() - 1);
            const reanuda = new Date(fin);
            reanuda.setDate(reanuda.getDate() + 1);
            const minFin = new Date(reanuda);
            minFin.setMonth(minFin.getMonth() + 6);
            minFin.setDate(minFin.getDate() - 1);
            const proxima = new Date(minFin);
            proxima.setDate(proxima.getDate() + 1);
            return { inicio, fin, reanuda, minFin, proxima };
        });

        const formatFecha = (d) => {
            if (!d) return "-";
            return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
        };

        const enviarPausa = async () => {
            enviando.value = true;
            try {
                const data = await crearPausa(props.clientId, {
                    tipo: form.tipo,
                    meses: form.meses,
                    motivo: form.motivo,
                    canal: form.canal,
                    evidencia: form.evidencia,
                });
                if (data.success) {
                    Swal.fire("Listo", data.message, "success");
                    mostrarModal.value = false;
                    pausaRefreshTrigger.value++;
                    await cargarEstado();
                } else {
                    Swal.fire("No se pudo programar", data.message, "warning");
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "Ocurrió un error inesperado.", "error");
            } finally {
                enviando.value = false;
            }
        };

        const confirmarCancelar = () => {
            Swal.fire({
                title: "¿Cancelar la pausa programada?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, cancelar",
                cancelButtonText: "Volver",
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                cargandoAccion.value = true;
                try {
                    const data = await cancelarPausa(props.clientId, pausaViva.value.id);
                    Swal.fire(data.success ? "Listo" : "Error", data.message, data.success ? "success" : "error");
                    pausaRefreshTrigger.value++;
                    await cargarEstado();
                } catch (e) {
                    Swal.fire("Error", e.response?.data?.message || "Ocurrió un error inesperado.", "error");
                } finally {
                    cargandoAccion.value = false;
                }
            });
        };

        const confirmarReanudar = () => {
            Swal.fire({
                title: "¿Reanudar la facturación ahora?",
                text: "El servicio se reactivará de inmediato y se consumirá esta pausa.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, reanudar",
                cancelButtonText: "Volver",
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                cargandoAccion.value = true;
                try {
                    const data = await reanudarPausa(props.clientId, pausaViva.value.id);
                    Swal.fire(data.success ? "Listo" : "Error", data.message, data.success ? "success" : "error");
                    pausaRefreshTrigger.value++;
                    await cargarEstado();
                } catch (e) {
                    Swal.fire("Error", e.response?.data?.message || "Ocurrió un error inesperado.", "error");
                } finally {
                    cargandoAccion.value = false;
                }
            });
        };

        return {
            elegibilidad,
            pausaViva,
            cargando,
            cargandoAccion,
            mostrarModal,
            enviando,
            form,
            maxMesesSinCuota,
            maxMesesConCuota,
            maxMesesActual,
            requisitos,
            estadoBoton,
            abrirModal,
            cerrarModal,
            onEvidenciaChange,
            fechas,
            formatFecha,
            enviarPausa,
            confirmarCancelar,
            confirmarReanudar,
        };
    },
};
</script>

<style scoped>
.pausa-btn-wrap {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.pausa-btn-outline {
    height: 44px;
    padding: 0 18px;
    border-radius: 8px;
    border: 2px solid #b45309;
    color: #92400e;
    background: #fffbeb;
    font-weight: 700;
}
.pausa-btn-outline:hover {
    background: #fef3c7;
}
.pausa-btn-solid {
    height: 44px;
    padding: 0 18px;
    border-radius: 8px;
    border: none;
    color: #ffffff;
    background: #b45309;
    font-weight: 700;
}
.pausa-btn-solid:disabled {
    opacity: 0.6;
}
.pausa-btn-neutral {
    height: 44px;
    padding: 0 18px;
    border-radius: 8px;
    border: 1px solid #d6d3e0;
    color: #3d3a4d;
    background: #ffffff;
    font-weight: 700;
}
.pausa-btn-disabled {
    height: 44px;
    padding: 0 18px;
    border-radius: 8px;
    border: 1px solid #d6d3e0;
    color: #6b6880;
    background: #f4f3f8;
    font-weight: 700;
    cursor: not-allowed;
}
.pausa-motivo-inline {
    font-size: 13px;
    color: #5e5b6e;
    max-width: 260px;
}

.pausa-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(74, 70, 88, 0.7);
    z-index: 10500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.pausa-modal {
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
}
.pausa-modal-header {
    padding: 20px 28px;
    border-bottom: 1px solid #e4e2ec;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.pausa-modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #1f1d2b;
}
.pausa-modal-subtitle {
    font-size: 13px;
    color: #5e5b6e;
}
.pausa-modal-close {
    border: none;
    background: none;
    color: #5e5b6e;
    font-size: 18px;
}
.pausa-modal-body {
    padding: 16px 28px 0;
}
.pausa-section-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #5e5b6e;
    margin-bottom: 6px;
}
.pausa-tipo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}
.pausa-tipo-card {
    text-align: left;
    padding: 14px 16px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #d6d3e0;
    color: #1f1d2b;
}
.pausa-tipo-card.activo {
    background: #fffbeb;
    border: 2px solid #b45309;
}
.pausa-tipo-title {
    font-size: 16px;
    font-weight: 700;
}
.pausa-tipo-desc {
    font-size: 13px;
    color: #5e5b6e;
}
.pausa-meses-grid {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.pausa-mes-btn {
    flex-grow: 1;
    min-width: 90px;
    height: 44px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    background: #ffffff;
    border: 1px solid #d6d3e0;
    color: #3d3a4d;
}
.pausa-mes-btn.activo {
    background: #fffbeb;
    border: 2px solid #b45309;
    color: #92400e;
}
.pausa-mes-btn.disabled {
    background: #f4f3f8;
    border: 1px dashed #d6d3e0;
    color: #9a97a8;
    cursor: not-allowed;
}
.pausa-hint {
    font-size: 13px;
    color: #5e5b6e;
    margin-top: 6px;
}
.pausa-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px 28px;
}
.pausa-checklist {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 14px;
}
.pausa-check-icon {
    color: #15803d;
    margin-right: 6px;
}
.pausa-field-label {
    display: block;
    font-size: 13px;
    color: #5e5b6e;
    margin-bottom: 8px;
}
.pausa-select,
.pausa-file {
    display: block;
    width: 100%;
    height: 40px;
    border: 1px solid #d6d3e0;
    border-radius: 8px;
    font-size: 14px;
    margin-top: 4px;
}
.pausa-timeline-box {
    background: #f7f6fb;
    border-radius: 10px;
    padding: 16px;
}
.pausa-timeline-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    font-size: 13px;
    margin-top: 8px;
}
.pausa-timeline-label {
    font-weight: 700;
}
.pausa-color-amber { color: #92400e; }
.pausa-color-purple { color: #4b36c4; }
.pausa-color-green { color: #15803d; }
.pausa-resumen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}
.pausa-resumen-card {
    border: 1px solid #e4e2ec;
    border-radius: 10px;
    padding: 12px;
}
.pausa-resumen-label {
    font-size: 12px;
    color: #5e5b6e;
}
.pausa-resumen-valor {
    font-size: 15px;
    font-weight: 700;
}
.pausa-modal-footer {
    padding: 20px 28px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: space-between;
    align-items: center;
}
.pausa-footnote {
    font-size: 13px;
    color: #5e5b6e;
    max-width: 380px;
}
.pausa-footer-actions {
    display: flex;
    gap: 12px;
}
</style>
