<template>
    <div class="payment-methods-container q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h5">Proveedores de Pago</div>
            <q-space />
            <q-btn color="primary" icon="add" label="Nuevo proveedor" @click="openCreate" />
        </div>

        <q-table
            :rows="providers"
            :columns="columns"
            row-key="id"
            :loading="loading"
            flat
            bordered
            :pagination="{ rowsPerPage: 25 }"
        >
            <template v-slot:body-cell-status="props">
                <q-td :props="props">
                    <q-badge :color="props.row.is_active ? 'positive' : 'grey'">
                        {{ props.row.is_active ? 'ACTIVO' : 'INACTIVO' }}
                    </q-badge>
                </q-td>
            </template>
            <template v-slot:body-cell-creds="props">
                <q-td :props="props">
                    <q-icon
                        :name="props.row.config_meta.has_api_key ? 'check_circle' : 'cancel'"
                        :color="props.row.config_meta.has_api_key ? 'positive' : 'negative'"
                        :title="props.row.config_meta.has_api_key ? 'API key configurada' : 'Falta API key'"
                    />
                    <q-icon
                        class="q-ml-xs"
                        :name="props.row.config_meta.has_webhook ? 'check_circle' : 'cancel'"
                        :color="props.row.config_meta.has_webhook ? 'positive' : 'negative'"
                        :title="props.row.config_meta.has_webhook ? 'Webhook secret configurado' : 'Falta webhook secret'"
                    />
                    <q-badge v-if="props.row.config_meta.sandbox" color="warning" class="q-ml-sm">SANDBOX</q-badge>
                </q-td>
            </template>
            <template v-slot:body-cell-actions="props">
                <q-td :props="props">
                    <q-btn flat dense icon="edit" color="primary" @click="openEdit(props.row)" title="Editar" />
                    <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)" title="Desactivar" />
                </q-td>
            </template>
        </q-table>

        <!-- Dialog crear/editar -->
        <q-dialog v-model="dialogOpen" persistent>
            <q-card style="min-width: 480px">
                <q-card-section>
                    <div class="text-h6">{{ form.id ? 'Editar' : 'Nuevo' }} proveedor</div>
                </q-card-section>
                <q-card-section class="q-pt-none">
                    <q-input v-model="form.name" label="Nombre interno *" outlined dense class="q-mb-sm" />
                    <q-select
                        v-model="form.provider"
                        :options="providerOptions"
                        label="Proveedor *"
                        outlined
                        dense
                        emit-value
                        map-options
                        class="q-mb-sm"
                    />
                    <q-toggle v-model="form.is_active" label="Activo" class="q-mb-sm" />

                    <!-- Campos OpenPay -->
                    <template v-if="form.provider === 'openpay'">
                        <q-separator class="q-my-md" />
                        <div class="text-subtitle2 q-mb-sm">Credenciales OpenPay</div>
                        <q-input
                            v-model="form.config.merchant_id"
                            label="Merchant ID"
                            outlined
                            dense
                            class="q-mb-sm"
                        />
                        <q-input
                            v-model="form.config.api_key"
                            label="API Key (private)"
                            type="password"
                            outlined
                            dense
                            :placeholder="form.id ? '(dejar vacío para no cambiar)' : ''"
                            class="q-mb-sm"
                        />
                        <q-input
                            v-model="form.config.webhook_secret"
                            label="Webhook Secret (password Basic Auth)"
                            type="password"
                            outlined
                            dense
                            :placeholder="form.id ? '(dejar vacío para no cambiar)' : ''"
                            class="q-mb-sm"
                            hint="Es el password que pones al alta del webhook en el dashboard de OpenPay"
                        />
                        <q-toggle v-model="form.config.sandbox" label="Modo sandbox (sandbox-api.openpay.mx)" />
                    </template>

                    <!-- Campos Stripe -->
                    <template v-if="form.provider === 'stripe'">
                        <q-separator class="q-my-md" />
                        <div class="text-subtitle2 q-mb-sm">Credenciales Stripe</div>
                        <q-input v-model="form.config.publishable_key" label="Publishable Key" outlined dense class="q-mb-sm" />
                        <q-input v-model="form.config.secret_key" label="Secret Key" type="password" outlined dense class="q-mb-sm" />
                        <q-input v-model="form.config.webhook_secret" label="Webhook Secret" type="password" outlined dense />
                    </template>

                    <!-- Campos Conekta -->
                    <template v-if="form.provider === 'conekta'">
                        <q-separator class="q-my-md" />
                        <div class="text-subtitle2 q-mb-sm">Credenciales Conekta</div>
                        <q-input v-model="form.config.public_key" label="Public Key" outlined dense class="q-mb-sm" />
                        <q-input v-model="form.config.private_key" label="Private Key" type="password" outlined dense class="q-mb-sm" />
                        <q-input v-model="form.config.webhook_secret" label="Webhook Secret" type="password" outlined dense />
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" :label="form.id ? 'Actualizar' : 'Crear'" :loading="saving" @click="save" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
import { ref, reactive } from 'vue';
import axios from 'axios';

export default {
    name: 'PaymentMethods',
    setup() {
        const providers = ref([]);
        const loading = ref(false);
        const saving = ref(false);
        const dialogOpen = ref(false);

        const providerOptions = [
            { label: 'OpenPay (SPEI / CLABE virtual)', value: 'openpay' },
            { label: 'Stripe', value: 'stripe' },
            { label: 'Conekta', value: 'conekta' },
            { label: 'PayPal', value: 'paypal' },
            { label: 'SPEI manual', value: 'spei_manual' },
        ];

        const columns = [
            { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
            { name: 'name', label: 'Nombre', field: 'name', align: 'left', sortable: true },
            { name: 'provider', label: 'Proveedor', field: 'provider', align: 'left', sortable: true },
            { name: 'status', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'creds', label: 'Creds', field: 'config_meta', align: 'center' },
            { name: 'actions', label: 'Acciones', field: 'id', align: 'right' },
        ];

        const blankForm = () => ({
            id: null,
            name: '',
            provider: 'openpay',
            is_active: true,
            config: { merchant_id: '', api_key: '', webhook_secret: '', sandbox: true },
        });
        const form = reactive(blankForm());

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get('/finanzas/payment-providers');
                providers.value = data.providers || [];
            } catch (e) {
                console.error(e);
                alert('Error cargando proveedores: ' + (e.response?.data?.message || e.message));
            } finally {
                loading.value = false;
            }
        }

        function openCreate() {
            Object.assign(form, blankForm());
            dialogOpen.value = true;
        }

        async function openEdit(row) {
            // Cargar config completo (incluyendo secrets enmascarados — el backend
            // los devuelve plano para el form. Si NO se modifican, el update no
            // sobreescribe gracias al check de "config vacío" en el controller).
            try {
                const { data } = await axios.get(`/finanzas/payment-providers/${row.id}`);
                Object.assign(form, blankForm(), {
                    id: data.provider.id,
                    name: data.provider.name,
                    provider: data.provider.provider,
                    is_active: data.provider.is_active,
                    // Mostramos solo merchant_id y sandbox; api_key y webhook_secret
                    // van vacíos para que el operador los re-escriba solo si los rota.
                    config: {
                        merchant_id: data.provider.config?.merchant_id || '',
                        api_key: '',
                        webhook_secret: '',
                        sandbox: data.provider.config?.sandbox || false,
                    },
                });
                dialogOpen.value = true;
            } catch (e) {
                alert('Error cargando proveedor: ' + (e.response?.data?.message || e.message));
            }
        }

        async function save() {
            saving.value = true;
            try {
                // Quitar config vacío para que update no lo sobrescriba
                const payload = JSON.parse(JSON.stringify(form));
                if (payload.id) {
                    const cleanConfig = Object.fromEntries(
                        Object.entries(payload.config).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
                    );
                    payload.config = Object.keys(cleanConfig).length ? cleanConfig : null;
                    await axios.put(`/finanzas/payment-providers/${payload.id}`, payload);
                } else {
                    await axios.post('/finanzas/payment-providers', payload);
                }
                dialogOpen.value = false;
                await load();
            } catch (e) {
                alert('Error guardando: ' + (e.response?.data?.message || e.message));
            } finally {
                saving.value = false;
            }
        }

        async function confirmDelete(row) {
            if (!confirm(`¿Desactivar proveedor "${row.name}"?`)) return;
            try {
                await axios.delete(`/finanzas/payment-providers/${row.id}`);
                await load();
            } catch (e) {
                alert('Error eliminando: ' + (e.response?.data?.message || e.message));
            }
        }

        load();

        return {
            providers, loading, saving, dialogOpen, columns, providerOptions, form,
            openCreate, openEdit, save, confirmDelete,
        };
    },
};
</script>
