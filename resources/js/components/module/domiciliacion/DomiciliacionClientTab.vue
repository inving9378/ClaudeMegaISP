<template>
    <div class="domiciliacion-tab mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">
                <i class="fa fa-credit-card me-2 text-primary"></i>
                Domiciliación a tarjeta
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <div v-if="loading" class="text-center text-muted py-4">
            <div class="spinner-border text-primary"></div>
        </div>

        <template v-else>
            <!-- ── Tarjeta activa ──────────────────────────────────────── -->
            <div v-if="card" class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div style="font-size:2rem;">💳</div>
                        <div>
                            <div class="fw-bold font-monospace fs-5">
                                •••• •••• •••• {{ card.last4 }}
                            </div>
                            <div class="text-muted small mt-1">
                                {{ (card.brand || '').toUpperCase() }} &nbsp;|&nbsp;
                                Vence {{ card.exp }} &nbsp;|&nbsp;
                                {{ card.cardholder || '—' }}
                            </div>
                            <div class="text-muted" style="font-size:.78rem; margin-top:.2rem;">
                                Registrada el {{ formatDate(card.consent_at) }}
                                vía <span class="badge bg-secondary">{{ card.channel }}</span>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-success">Activa</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <button class="btn btn-sm btn-outline-danger" :disabled="cancelling"
                                @click="cancelar">
                            <i class="fa fa-times me-1"></i>
                            {{ cancelling ? 'Cancelando…' : 'Cancelar domiciliación' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="alert alert-light border text-muted text-center py-4">
                <i class="fa fa-credit-card fa-2x mb-2 d-block opacity-50"></i>
                Este cliente no tiene domiciliación activa.
            </div>

            <!-- ── Captura directa ────────────────────────────────────── -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <strong><i class="fa fa-keyboard me-1"></i> Captura directa (solo escritura)</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Ingresa los datos de la tarjeta del cliente. El número de tarjeta se tokeniza
                        en el navegador y nunca llega al servidor.
                    </p>

                    <div id="domiciliacion-enroll-error" class="alert alert-danger d-none" role="alert"></div>

                    <form id="domiciliacion-admin-form" novalidate @submit.prevent="submitDirecta">
                        <div class="mb-2">
                            <label class="form-label form-label-sm">Número de tarjeta</label>
                            <input type="tel" id="dom-card-number" data-openpay-card="card_number"
                                   class="form-control form-control-sm"
                                   placeholder="0000 0000 0000 0000" maxlength="19"
                                   autocomplete="off" @input="formatCardNumber">
                        </div>
                        <div class="mb-2">
                            <label class="form-label form-label-sm">Titular</label>
                            <input type="text" id="dom-card-holder" data-openpay-card="holder_name"
                                   class="form-control form-control-sm"
                                   placeholder="Nombre como aparece en la tarjeta"
                                   autocomplete="off">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col">
                                <label class="form-label form-label-sm">Mes exp.</label>
                                <input type="tel" id="dom-card-month" data-openpay-card="expiration_month"
                                       class="form-control form-control-sm"
                                       placeholder="MM" maxlength="2">
                            </div>
                            <div class="col">
                                <label class="form-label form-label-sm">Año exp.</label>
                                <input type="tel" id="dom-card-year" data-openpay-card="expiration_year"
                                       class="form-control form-control-sm"
                                       placeholder="YY" maxlength="2">
                            </div>
                            <div class="col">
                                <label class="form-label form-label-sm">CVV</label>
                                <input type="tel" id="dom-card-cvv" data-openpay-card="cvv2"
                                       class="form-control form-control-sm"
                                       placeholder="123" maxlength="4">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="enrolling">
                            <i class="fa fa-save me-1"></i>
                            {{ enrolling ? 'Guardando…' : 'Registrar tarjeta' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Enviar liga ─────────────────────────────────────────── -->
            <div class="card">
                <div class="card-header py-2">
                    <strong><i class="fa fa-link me-1"></i> Enviar liga de enrolamiento</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Genera un enlace de un solo uso (válido 48 h) y envíalo al cliente
                        para que registre su tarjeta desde su dispositivo.
                    </p>
                    <div class="d-flex gap-2 align-items-end flex-wrap">
                        <div>
                            <label class="form-label form-label-sm">Canal</label>
                            <select v-model="ligaChannel" class="form-select form-select-sm" style="width:auto;">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" :disabled="sendingLiga"
                                @click="enviarLiga">
                            <i class="fa fa-paper-plane me-1"></i>
                            {{ sendingLiga ? 'Enviando…' : 'Enviar liga' }}
                        </button>
                    </div>
                    <div v-if="ligaUrl" class="mt-2">
                        <div class="alert alert-success py-2 px-3 small mb-0">
                            Liga enviada. URL: <a :href="ligaUrl" target="_blank">{{ ligaUrl }}</a>
                        </div>
                    </div>
                    <div v-if="ligaError" class="mt-2">
                        <div class="alert alert-danger py-2 px-3 small mb-0">{{ ligaError }}</div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: 'DomiciliacionClientTab',

    props: {
        clientId: { type: [Number, String], required: true },
    },

    data() {
        return {
            loading:     true,
            card:        null,
            cancelling:  false,
            enrolling:   false,
            sendingLiga: false,
            ligaChannel: 'whatsapp',
            ligaUrl:     null,
            ligaError:   null,
            openpayReady: false,
        };
    },

    mounted() {
        this.fetch();
        this.loadOpenpay();
    },

    methods: {
        async fetch() {
            this.loading = true;
            try {
                const res  = await axios.get(`/domiciliacion/clientes/${this.clientId}`);
                this.card  = res.data.card;
            } catch (e) {
                console.error('DomiciliacionTab fetch error', e);
            } finally {
                this.loading = false;
            }
        },

        async cancelar() {
            if (!confirm('¿Cancelar la domiciliación de este cliente?')) return;
            this.cancelling = true;
            try {
                await axios.delete(`/domiciliacion/clientes/${this.clientId}/${this.card.id}`);
                this.card = null;
            } catch (e) {
                alert('Error al cancelar: ' + (e.response?.data?.error || e.message));
            } finally {
                this.cancelling = false;
            }
        },

        async enviarLiga() {
            this.sendingLiga = true;
            this.ligaUrl     = null;
            this.ligaError   = null;
            try {
                const res    = await axios.post(`/domiciliacion/clientes/${this.clientId}/liga`, { channel: this.ligaChannel });
                this.ligaUrl = res.data.url;
            } catch (e) {
                this.ligaError = e.response?.data?.error || 'No se pudo enviar la liga.';
            } finally {
                this.sendingLiga = false;
            }
        },

        submitDirecta() {
            if (!this.openpayReady) {
                alert('El procesador de pagos aún no está listo. Espera un momento.');
                return;
            }
            const errorEl = document.getElementById('domiciliacion-enroll-error');
            errorEl.classList.add('d-none');
            this.enrolling = true;

            const form = document.getElementById('domiciliacion-admin-form');

            window.OpenPay.token.create(form, async (response) => {
                const token = response.data.id;
                // Limpiar PAN antes de continuar
                document.getElementById('dom-card-number').value = '';
                document.getElementById('dom-card-cvv').value    = '';
                try {
                    const res   = await axios.post(`/domiciliacion/clientes/${this.clientId}`, { token });
                    this.card   = res.data.card;
                    // Limpiar campos del formulario
                    ['dom-card-holder','dom-card-month','dom-card-year'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                } catch (e) {
                    errorEl.textContent = e.response?.data?.message || 'Error al registrar la tarjeta.';
                    errorEl.classList.remove('d-none');
                } finally {
                    this.enrolling = false;
                }
            }, (response) => {
                const msgs = {
                    2001: 'La tarjeta ya expiró.',
                    2004: 'El CVV es incorrecto.',
                    2005: 'Fecha de vencimiento incorrecta.',
                    3001: 'Tarjeta rechazada.',
                    3002: 'Tarjeta caducada.',
                    3004: 'Fondos insuficientes.',
                };
                const code = response.data?.error_code;
                errorEl.textContent = msgs[code] || `Datos de tarjeta inválidos (código ${code}).`;
                errorEl.classList.remove('d-none');
                this.enrolling = false;
            });
        },

        loadOpenpay() {
            if (window.OpenPay) {
                this.initOpenpay();
                return;
            }
            const script = document.createElement('script');
            script.src   = 'https://js.openpay.mx/openpay.v1.min.js';
            script.onload = () => this.initOpenpay();
            document.head.appendChild(script);
        },

        initOpenpay() {
            window.OpenPay.setId(window.__OPENPAY_ID__ || '');
            window.OpenPay.setApiKey(window.__OPENPAY_PK__ || '');
            window.OpenPay.setSandboxMode(window.__OPENPAY_SANDBOX__ !== false);
            this.openpayReady = true;
        },

        formatCardNumber(e) {
            e.target.value = e.target.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
        },

        formatDate(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
    },
};
</script>
