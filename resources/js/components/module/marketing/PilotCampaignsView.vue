<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-piloto">
            <mkt-encabezado-pantalla
                titulo="Pruebas A/B"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton v-if="estado === 'listo'" tipo="crear" etiqueta="Nuevo piloto" @click="openCreate" />
                </template>

                <div v-if="estado === 'sin_permiso'" class="mkt-piloto__cuerpo">
                    <mkt-estado-vacio icono="lock" titulo="No tienes acceso a esta sección" texto="No tienes acceso a esta sección. Pídelo a Irving." />
                </div>

                <template v-else>
                    <div class="mkt-piloto__cuerpo">
                        <div class="alert alert-warning mkt-piloto__aviso" role="note">
                            Solo para la lista de prueba interna (empleados o cuentas de prueba), nunca clientes reales. El envío
                            real de correos está apagado y solo lo enciende Irving; antes de enviar siempre se corre una simulación.
                        </div>
                    </div>

                    <div v-if="estado === 'error'" class="mkt-piloto__cuerpo">
                        <mkt-estado-vacio icono="cloud_off" titulo="No se pudieron cargar los pilotos" :texto="mensajeError" />
                    </div>
                    <mkt-tabla-ejecutiva
                        v-else
                        :columnas="columns"
                        :filas="campaigns"
                        :cargando="estado === 'cargando'"
                        vacio-icono="science"
                        vacio-titulo="Aún no hay pilotos"
                        vacio-texto="Crea uno con dos versiones de correo y una lista de prueba interna."
                        @row-click="(_ev, row) => openDetail(row.id)"
                        @actualizar="load"
                    >
                        <template #body-cell-name="props">
                            <q-td :props="props"><span class="mkt-piloto__fuerte">{{ props.row.name }}</span></q-td>
                        </template>
                        <template #body-cell-status="props">
                            <q-td :props="props"><mkt-insignia-estado :texto="statusLabel(props.value)" :tono="statusTono(props.value)" /></q-td>
                        </template>
                        <template #body-cell-acciones="props">
                            <q-td :props="props">
                                <mkt-boton tipo="secundario" chico etiqueta="Ver" :cargando="abriendo === props.row.id" @click.stop="openDetail(props.row.id)" />
                            </q-td>
                        </template>
                    </mkt-tabla-ejecutiva>
                </template>
            </mkt-encabezado-pantalla>

            <!-- Crear -->
            <mkt-dialogo v-model="showCreate" titulo="Nuevo piloto A/B" :guardando="saving" :ancho="680" persistente>
                <q-input v-model="form.name" label="Nombre del piloto *" outlined dense :error="!!errores.name" :error-message="errores.name" />
                <div v-for="v in ['a', 'b']" :key="v" class="mkt-piloto__variante">
                    <div class="mkt-piloto__subtitulo">Versión {{ v.toUpperCase() }}</div>
                    <q-input
                        v-model="form[`variant_${v}_subject`]"
                        :label="`Asunto ${v.toUpperCase()} *`"
                        outlined
                        dense
                        :error="!!errores[`variant_${v}_subject`]"
                        :error-message="errores[`variant_${v}_subject`]"
                    />
                    <q-input
                        v-model="form[`variant_${v}_body`]"
                        :label="`Cuerpo ${v.toUpperCase()} *`"
                        type="textarea"
                        outlined
                        dense
                        rows="3"
                        :error="!!errores[`variant_${v}_body`]"
                        :error-message="errores[`variant_${v}_body`]"
                    />
                    <div class="mkt-piloto__par">
                        <q-input v-model="form[`variant_${v}_cta_label`]" label="Texto del botón (opcional)" outlined dense />
                        <q-input
                            v-model="form[`variant_${v}_cta_url`]"
                            label="Enlace del botón (opcional)"
                            outlined
                            dense
                            :error="!!errores[`variant_${v}_cta_url`]"
                            :error-message="errores[`variant_${v}_cta_url`]"
                        />
                    </div>
                </div>
                <div class="mkt-piloto__variante">
                    <div class="mkt-piloto__subtitulo">Destinatarios de prueba</div>
                    <q-input
                        v-model="recipientsRaw"
                        type="textarea"
                        outlined
                        dense
                        rows="4"
                        label="Uno por línea: correo, o «correo, nombre»"
                        placeholder="empleado1@meganet.mx, Empleado 1"
                        :error="!!errorDestinatarios"
                        :error-message="errorDestinatarios"
                    />
                    <div class="mkt-piloto__par">
                        <q-input v-model.number="form.batch_size" type="number" min="1" label="Tamaño de lote" outlined dense />
                        <q-input v-model.number="form.batch_pause_seconds" type="number" min="0" label="Pausa entre lotes (s)" outlined dense />
                    </div>
                </div>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="saving" @click="showCreate = false" />
                    <mkt-boton tipo="crear" etiqueta="Crear piloto" :cargando="saving" @click="submitCreate" />
                </template>
            </mkt-dialogo>

            <!-- Detalle -->
            <mkt-dialogo v-model="showDetail" :titulo="detail?.campaign?.name || 'Piloto'" :guardando="dryRunning || sending" :ancho="680" @hide="detail = null">
                <template v-if="detail">
                    <div class="mkt-piloto__estado">
                        <span class="mkt-piloto__suave">Estado</span>
                        <mkt-insignia-estado :texto="statusLabel(detail.campaign.status)" :tono="statusTono(detail.campaign.status)" />
                    </div>
                    <div class="mkt-piloto__comparativa">
                        <div v-for="v in ['a', 'b']" :key="v" class="mkt-piloto__version">
                            <div class="mkt-piloto__subtitulo">Versión {{ v.toUpperCase() }}</div>
                            <dl class="mkt-piloto__cifras">
                                <div><dt>Enviados</dt><dd>{{ formatoNumero(stat(v, 'total')) }}</dd></div>
                                <div><dt>Abiertos</dt><dd>{{ formatoNumero(stat(v, 'opened')) }}</dd></div>
                                <div><dt>Clics</dt><dd>{{ formatoNumero(stat(v, 'clicked')) }}</dd></div>
                                <div><dt>Conversiones</dt><dd>{{ formatoNumero(stat(v, 'converted')) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                    <div v-if="detail.campaign.dry_run_report" class="mkt-piloto__simulacion">
                        <q-icon name="fact_check" size="18px" aria-hidden="true" />
                        <span>
                            Simulación: {{ detail.campaign.dry_run_report.total }} destinatarios
                            ({{ detail.campaign.dry_run_report.variant_a }} A / {{ detail.campaign.dry_run_report.variant_b }} B),
                            {{ detail.campaign.dry_run_report.batches }} lotes. Correo:
                            <strong :class="detail.campaign.dry_run_report.smtp?.valid ? 'mkt-piloto__ok' : 'mkt-piloto__mal'">
                                {{ detail.campaign.dry_run_report.smtp?.message }}
                            </strong>
                        </span>
                    </div>
                </template>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cerrar" :deshabilitado="dryRunning || sending" @click="showDetail = false" />
                    <mkt-boton
                        tipo="secundario"
                        etiqueta="Simular envío"
                        :cargando="dryRunning"
                        :deshabilitado="dryRunning || sending"
                        @click="runDryRun(detail.campaign.id)"
                    />
                    <mkt-boton
                        tipo="guardar"
                        etiqueta="Enviar de verdad"
                        :deshabilitado="sending || dryRunning || !['dry_run', 'ready_to_send'].includes(detail?.campaign?.status)"
                        @click="confirmarEnvio = true"
                    />
                </template>
            </mkt-dialogo>

            <!-- Confirmar envío real -->
            <mkt-dialogo v-model="confirmarEnvio" titulo="Confirmar envío real" :guardando="sending" :ancho="420">
                <p class="mkt-piloto__texto">Esto envía correos de verdad a la lista de prueba. ¿Confirmas?</p>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="sending" @click="confirmarEnvio = false" />
                    <mkt-boton tipo="guardar" etiqueta="Sí, enviar" :cargando="sending" @click="doSend(detail.campaign.id)" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { mensajeDeError, erroresPorCampo, formatoNumero } from "./kit";

const ESTADOS = {
    draft: { label: "Borrador", tono: "neutro" },
    dry_run: { label: "Simulación lista", tono: "info" },
    ready_to_send: { label: "Listo para enviar", tono: "primario" },
    sending: { label: "Enviando", tono: "aviso" },
    sent: { label: "Enviado", tono: "exito" },
    canceled: { label: "Cancelado", tono: "peligro" },
};

/** Piloto A/B por correo (/marketing/pilot-campaigns) — Fase 1, rediseño con el kit, mismos endpoints. */
export default {
    name: "MarketingPilotCampaignsView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            saving: false,
            dryRunning: false,
            sending: false,
            abriendo: null,
            campaigns: [],
            showCreate: false,
            showDetail: false,
            confirmarEnvio: false,
            detail: null,
            recipientsRaw: "",
            errores: {},
            errorDestinatarios: "",
            columns: [
                { name: "id", label: "#", field: "id", align: "left", ocultarEnMovil: true },
                { name: "name", label: "Nombre", field: "name", align: "left" },
                { name: "sends_count", label: "Destinatarios", field: "sends_count", align: "right" },
                { name: "status", label: "Estado", field: "status", align: "left" },
                { name: "acciones", label: "Acciones", field: "id", align: "right" },
            ],
            form: this.emptyForm(),
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        formatoNumero,
        emptyForm() {
            return {
                name: "",
                variant_a_subject: "", variant_a_body: "", variant_a_cta_label: "", variant_a_cta_url: "",
                variant_b_subject: "", variant_b_body: "", variant_b_cta_label: "", variant_b_cta_url: "",
                batch_size: 10, batch_pause_seconds: 5,
            };
        },
        statusLabel(s) {
            return ESTADOS[s]?.label || s || "—";
        },
        statusTono(s) {
            return ESTADOS[s]?.tono || "neutro";
        },
        stat(v, campo) {
            return this.detail?.stats?.by_variant?.[v]?.[campo] ?? 0;
        },
        async load() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/pilot-campaigns");
                this.campaigns = data?.campaigns ?? [];
                this.estado = "listo";
            } catch (e) {
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                } else {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            }
        },
        openCreate() {
            this.form = this.emptyForm();
            this.recipientsRaw = "";
            this.errores = {};
            this.errorDestinatarios = "";
            this.showCreate = true;
        },
        parseRecipients() {
            return this.recipientsRaw
                .split("\n")
                .map((line) => line.trim())
                .filter(Boolean)
                .map((line) => {
                    const [email, name] = line.split(",").map((s) => s && s.trim());
                    return { email, name: name || null };
                });
        },
        async submitCreate() {
            if (this.saving) return;
            const recipients = this.parseRecipients();
            this.errorDestinatarios = recipients.length ? "" : "Agrega al menos un destinatario de prueba.";
            if (!recipients.length) return;
            this.saving = true;
            this.errores = {};
            try {
                await axios.post("/api/marketing/pilot-campaigns", { ...this.form, recipients });
                message("Piloto creado.");
                this.showCreate = false;
                this.load();
            } catch (e) {
                this.errores = erroresPorCampo(e);
                const errDest = Object.keys(this.errores).find((k) => k.startsWith("recipients"));
                if (errDest) this.errorDestinatarios = this.errores[errDest];
                message(mensajeDeError(e, "No se pudo crear el piloto."), "error");
            } finally {
                this.saving = false;
            }
        },
        async openDetail(id) {
            if (this.abriendo) return;
            this.abriendo = id;
            try {
                const { data } = await axios.get(`/api/marketing/pilot-campaigns/${id}`);
                this.detail = data;
                this.showDetail = true;
            } catch (e) {
                message(
                    e?.response?.status === 404 ? "No se encontró el piloto." : mensajeDeError(e, "No se pudo abrir el piloto."),
                    "error",
                );
            } finally {
                this.abriendo = null;
            }
        },
        async recargarDetalle(id) {
            try {
                const { data } = await axios.get(`/api/marketing/pilot-campaigns/${id}`);
                this.detail = data;
            } catch (e) {
                /* el detalle anterior sigue a la vista */
            }
        },
        async runDryRun(id) {
            if (this.dryRunning) return;
            this.dryRunning = true;
            try {
                await axios.post(`/api/marketing/pilot-campaigns/${id}/dry-run`);
                message("Simulación lista.");
                await this.recargarDetalle(id);
                this.load();
            } catch (e) {
                message(mensajeDeError(e, "No se pudo simular el envío."), "error");
            } finally {
                this.dryRunning = false;
            }
        },
        async doSend(id) {
            if (this.sending) return;
            this.sending = true;
            try {
                const { data } = await axios.post(`/api/marketing/pilot-campaigns/${id}/send`);
                this.confirmarEnvio = false;
                message(`Enviados: ${data?.result?.sent ?? 0}, fallidos: ${data?.result?.failed ?? 0}.`);
                await this.recargarDetalle(id);
                this.load();
            } catch (e) {
                message(mensajeDeError(e, "No se pudo enviar el piloto."), "error");
            } finally {
                this.sending = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-piloto { min-width: 0; }
.mkt-piloto__cuerpo { padding: 0 16px 8px; }
/* Aviso = `alert alert-warning` del sistema (Bootstrap), sin colores propios. */
.mkt-piloto__aviso { margin: 0; font-size: 13px; line-height: 1.5; }
.mkt-piloto__fuerte { font-weight: 600; color: var(--mkt-texto); }
.mkt-piloto__suave { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-piloto__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
.mkt-piloto__variante {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-1);
    padding-top: var(--mkt-esp-2);
    border-top: 1px solid var(--mkt-borde);
}
.mkt-piloto__subtitulo { font-size: 14px; font-weight: 600; color: var(--mkt-texto); }
.mkt-piloto__par { display: grid; gap: var(--mkt-esp-1) var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-piloto__estado { display: flex; align-items: center; gap: var(--mkt-esp-1); }
.mkt-piloto__comparativa { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-piloto__version {
    padding: var(--mkt-esp-2);
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
}
.mkt-piloto__cifras { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--mkt-esp-1); margin: var(--mkt-esp-1) 0 0; }
.mkt-piloto__cifras dt { font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-piloto__cifras dd { margin: 0; font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--mkt-texto); }
.mkt-piloto__simulacion {
    display: flex;
    align-items: flex-start;
    gap: var(--mkt-esp-1);
    padding: 12px var(--mkt-esp-2);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-suave);
    font-size: 13px;
    color: var(--mkt-texto);
}
.mkt-piloto__ok { color: var(--mkt-exito); }
.mkt-piloto__mal { color: var(--mkt-peligro); }
@media (max-width: 599px) {
    .mkt-piloto__cuerpo { padding: 0 12px 8px; }
    .mkt-piloto__par,
    .mkt-piloto__comparativa { grid-template-columns: minmax(0, 1fr); }
}
</style>
