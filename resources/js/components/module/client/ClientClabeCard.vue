<template>
    <q-card flat bordered class="client-clabe-card q-pa-md">
        <div class="row items-center q-mb-sm">
            <q-icon name="account_balance" size="24px" color="primary" />
            <div class="text-h6 q-ml-sm">CLABE de cobro SPEI</div>
            <q-space />
            <q-badge v-if="loading" color="grey">Cargando...</q-badge>
        </div>

        <!-- Sin CLABE asignada aún -->
        <div v-if="!loading && !clabeData">
            <q-banner class="bg-orange-1 text-orange-9 q-mb-md" dense>
                Este cliente aún no tiene CLABE virtual asignada.
            </q-banner>
            <q-btn
                color="primary"
                icon="add_card"
                label="Generar CLABE en OpenPay"
                :loading="assigning"
                @click="assignClabe"
            />
        </div>

        <!-- CLABE asignada -->
        <div v-if="!loading && clabeData">
            <div class="row q-mb-md">
                <div class="col">
                    <div class="text-caption text-grey-7">CLABE (18 dígitos)</div>
                    <div class="text-h5" style="font-family: 'JetBrains Mono', monospace; letter-spacing: 1.5px">
                        {{ formatClabe(clabeData.clabe) }}
                    </div>
                </div>
                <div class="col-auto">
                    <q-btn flat dense icon="content_copy" @click="copyClabe" title="Copiar al portapapeles">
                        <q-tooltip>Copiar</q-tooltip>
                    </q-btn>
                </div>
            </div>

            <div class="row q-mb-md">
                <div class="col-auto">
                    <img
                        :src="qrUrl"
                        :alt="`QR ${clabeData.clabe}`"
                        width="160"
                        height="160"
                        style="border: 1px solid #ddd; padding: 4px; background: white"
                    />
                </div>
                <div class="col q-pl-md">
                    <div class="text-caption text-grey-7">Estado</div>
                    <q-badge :color="clabeData.is_active ? 'positive' : 'grey'">
                        {{ clabeData.is_active ? 'ACTIVA' : 'INACTIVA' }}
                    </q-badge>
                    <div class="text-caption text-grey-7 q-mt-sm">OpenPay ID</div>
                    <div style="font-family: monospace; font-size: 12px">{{ clabeData.openpay_id || '—' }}</div>
                    <div class="text-caption text-grey-7 q-mt-sm">Asignada</div>
                    <div style="font-size: 12px">{{ formatDate(clabeData.created_at) }}</div>
                </div>
            </div>

            <q-separator class="q-my-md" />
            <div class="text-subtitle2 q-mb-sm">Últimos pagos recibidos</div>
            <q-table
                v-if="recentPayments.length > 0"
                :rows="recentPayments"
                :columns="paymentColumns"
                row-key="id"
                flat
                dense
                hide-pagination
                :pagination="{ rowsPerPage: 10 }"
            >
                <template v-slot:body-cell-amount="props">
                    <q-td :props="props" class="text-right">
                        ${{ Number(props.row.amount).toFixed(2) }}
                    </q-td>
                </template>
                <template v-slot:body-cell-status="props">
                    <q-td :props="props">
                        <q-badge :color="statusColor(props.row.webhook?.status)">
                            {{ props.row.webhook?.status || '—' }}
                        </q-badge>
                    </q-td>
                </template>
            </q-table>
            <div v-else class="text-caption text-grey-7">
                Aún no se han recibido pagos SPEI en esta CLABE.
            </div>
        </div>
    </q-card>
</template>

<script>
import { ref, computed } from 'vue';
import axios from 'axios';

export default {
    name: 'ClientClabeCard',
    props: {
        clientId: { type: [Number, String], required: true },
    },
    setup(props) {
        const loading = ref(false);
        const assigning = ref(false);
        const clabeData = ref(null);
        const recentPayments = ref([]);

        const paymentColumns = [
            { name: 'date', label: 'Fecha', field: 'date', align: 'left' },
            { name: 'number', label: 'Referencia', field: 'number', align: 'left' },
            { name: 'amount', label: 'Monto', field: 'amount', align: 'right' },
            { name: 'status', label: 'Estado', field: 'webhook', align: 'center' },
        ];

        const qrUrl = computed(() => {
            if (!clabeData.value?.clabe) return '';
            // QR externo — sin dependencia npm. Si quieres self-hosted,
            // sustituye por endpoint /finanzas/clients/{id}/clabe/qr.svg que
            // genere SVG con simplesoftwareio/simple-qrcode.
            const data = encodeURIComponent(clabeData.value.clabe);
            return `https://api.qrserver.com/v1/create-qr-code/?data=${data}&size=200x200&margin=4`;
        });

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get(`/finanzas/clients/${props.clientId}/clabe`);
                clabeData.value = data.clabe;
                recentPayments.value = data.recent_payments || [];
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        }

        async function assignClabe() {
            if (!confirm('Generar CLABE virtual en OpenPay para este cliente?')) return;
            assigning.value = true;
            try {
                const { data } = await axios.post(`/finanzas/clients/${props.clientId}/assign-clabe`);
                if (data.is_stub) {
                    alert('Atención: CLABE generada en modo STUB (no hay credenciales reales de OpenPay configuradas). No aceptará depósitos.');
                }
                await load();
            } catch (e) {
                alert('Error generando CLABE: ' + (e.response?.data?.message || e.message));
            } finally {
                assigning.value = false;
            }
        }

        function formatClabe(clabe) {
            // 18 dígitos → 3-3-12 visual (banco-plaza-cuenta)
            if (!clabe || clabe.length !== 18) return clabe;
            return `${clabe.slice(0, 3)} ${clabe.slice(3, 6)} ${clabe.slice(6)}`;
        }

        function copyClabe() {
            if (!clabeData.value?.clabe) return;
            navigator.clipboard.writeText(clabeData.value.clabe).then(() => {
                // Si Quasar Notify está disponible:
                if (window.Quasar?.Notify) {
                    window.Quasar.Notify.create({ message: 'CLABE copiada', color: 'positive', timeout: 1500 });
                } else {
                    alert('CLABE copiada');
                }
            });
        }

        function formatDate(s) {
            if (!s) return '—';
            const d = new Date(s);
            return d.toLocaleString('es-MX');
        }

        function statusColor(status) {
            const map = {
                processed: 'positive',
                pending: 'warning',
                failed: 'negative',
                duplicate: 'grey',
                signature_invalid: 'red-10',
            };
            return map[status] || 'grey';
        }

        load();

        return {
            loading, assigning, clabeData, recentPayments,
            paymentColumns, qrUrl,
            assignClabe, formatClabe, copyClabe, formatDate, statusColor,
        };
    },
};
</script>

<style scoped>
.client-clabe-card {
    max-width: 720px;
}
</style>
