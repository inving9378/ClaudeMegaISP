<template>
    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

    <template v-else-if="invoice">
        <!-- Header -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">
                            Factura {{ invoice.invoice_number ?? '#' + invoice.id }}
                            <span class="badge ms-2" :class="statusBadge(invoice.status)">{{ invoice.status_name }}</span>
                        </h5>
                        <div class="text-muted small">
                            <span class="me-3"><i class="fas fa-building me-1"></i>{{ invoice.supplier?.name }}</span>
                            <span class="me-3" v-if="invoice.supplier_vendor"><i class="fas fa-user me-1"></i>{{ invoice.supplier_vendor.full_name }}</span>
                            <span class="me-3"><i class="fas fa-calendar me-1"></i>{{ invoice.date }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold">${{ formatCurrency(invoice.total) }}</div>
                        <div v-if="invoice.status === 'pending'" class="d-flex gap-2 mt-2 justify-content-end">
                            <button class="btn btn-success btn-sm" @click="openReceiveModal">
                                <i class="fas fa-warehouse me-1"></i> Distribuir a Almacenes
                            </button>
                            <button class="btn btn-danger btn-sm" @click="denyInvoice" :disabled="denying">
                                <span v-if="denying" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="fas fa-ban me-1"></i> Denegar
                            </button>
                        </div>
                        <div v-if="invoice.status === 'dispatched'" class="mt-2 text-end">
                            <span class="badge bg-info text-dark">
                                <i class="fas fa-clock me-1"></i> Pendiente de aceptación en almacén
                            </span>
                        </div>
                    </div>
                </div>
                <p class="mt-2 mb-0 text-muted small" v-if="invoice.notes"><i class="fas fa-sticky-note me-1"></i>{{ invoice.notes }}</p>
            </div>
        </div>

        <!-- Líneas de productos -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Productos</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Artículo</th>
                                <th class="text-center">Tipo de compra</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td>{{ item.inventory_item?.name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge" :class="purchaseTypeBadge(item.purchase_type)">
                                        {{ purchaseTypeLabel(item.purchase_type) }}
                                    </span>
                                </td>
                                <td class="text-center">{{ item.quantity }}</td>
                                <td class="text-end">${{ formatCurrency(item.store_price) }}</td>
                                <td class="text-end fw-bold">${{ formatCurrency(item.total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bold">${{ formatCurrency(invoice.total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de recepción con distribución por almacén -->
        <div class="modal fade" id="receiveInvoiceModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Distribuir a Almacenes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Se crearán <strong>pedidos pendientes</strong> en cada almacén seleccionado.
                            El stock se registrará oficialmente cuando el responsable del almacén acepte cada pedido.
                            Distribuye el <strong>100%</strong> de cada producto antes de confirmar.
                        </div>

                        <div v-if="loadingStores" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary"></div>
                        </div>

                        <div v-else>
                            <div v-for="item in receiveItems" :key="item.id" class="border rounded mb-3 p-3">
                                <!-- Encabezado del producto --> 
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div>
                                        <strong>{{ item.name }}</strong>
                                        <span class="text-muted ms-2 small">
                                            {{ purchaseTypeLabel(item.purchase_type) }} |
                                            Total factura: {{ item.totalQty }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span :class="distBadgeClass(item)" class="badge">
                                            Distribuido: {{ itemDistributed(item) }} / {{ item.totalQty }}
                                        </span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="addDistribution(item)">
                                            <i class="fas fa-plus me-1"></i> Almacén
                                        </button>
                                    </div>
                                </div>

                                <!-- Filas de distribución -->
                                <div v-if="item.distributions.length > 0" class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Almacén de Destino <span class="text-danger">*</span></th>
                                                <th style="width:140px">Cantidad <span class="text-danger">*</span></th>
                                                <th style="width:40px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(dist, dIdx) in item.distributions" :key="dIdx">
                                                <td>
                                                    <select class="form-select form-select-sm" v-model="dist.inventory_store_id" required>
                                                        <option value="">Seleccione almacén...</option>
                                                        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm"
                                                        v-model.number="dist.quantity"
                                                        min="0.01" step="0.01" required />
                                                </td>
                                                <td class="text-center">
                                                    <a href="javascript:void(0)" @click="removeDistribution(item, dIdx)" class="text-danger">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-muted small text-center py-2">
                                    Haz clic en <strong>+ Almacén</strong> para agregar una distribución.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" @click="confirmReceive"
                            :disabled="receiveSaving || !allDistributed">
                            <span v-if="receiveSaving" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="fas fa-paper-plane me-1"></i> Enviar Pedidos a Almacenes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "SupplierInvoiceShow",
    props: { invoice_id: [Number, String] },
    setup(props) {
        const invoice = ref(null);
        const loading = ref(true);
        const stores = ref([]);
        const loadingStores = ref(false);
        const receiveItems = ref([]);
        const receiveSaving = ref(false);
        const denying = ref(false);

        const load = async () => {
            try {
                const { data } = await axios.get(`/inventory/supplier-invoice/show/${props.invoice_id}`);
                invoice.value = data.invoice ?? data;
            } catch (e) {
                console.error(e);
                Swal.fire("Error", "No se pudo cargar la factura", "error");
            } finally {
                loading.value = false;
            }
        };

        onMounted(load);

        const purchaseTypeLabel = (type) => {
            return { unit: 'Unidad', bulk: 'Volumen', other: 'Otro' }[type] || type;
        };

        const purchaseTypeBadge = (type) => {
            return { unit: 'bg-primary', bulk: 'bg-warning text-dark', other: 'bg-secondary' }[type] || 'bg-light';
        };

        const openReceiveModal = async () => {
            receiveItems.value = (invoice.value.items ?? []).map(item => ({
                id: item.id,
                name: item.inventory_item?.name ?? '—',
                purchase_type: item.purchase_type,
                totalQty: parseFloat(item.quantity),
                distributions: [{ inventory_store_id: '', quantity: parseFloat(item.quantity) }],
            }));

            if (stores.value.length === 0) {
                loadingStores.value = true;
                try {
                    const { data } = await axios.get('/inventory/inventory_store/get-all');
                    stores.value = data;
                } catch (e) {
                    console.error(e);
                    Swal.fire("Error", "No se pudieron cargar los almacenes", "error");
                } finally {
                    loadingStores.value = false;
                }
            }

            const modal = new bootstrap.Modal(document.getElementById('receiveInvoiceModal'));
            modal.show();
        };

        const itemDistributed = (item) =>
            Math.round(item.distributions.reduce((sum, d) => sum + (parseFloat(d.quantity) || 0), 0) * 10000) / 10000;

        const distBadgeClass = (item) => {
            const dist = itemDistributed(item);
            if (Math.abs(dist - item.totalQty) < 0.001) return 'bg-success';
            if (dist > item.totalQty) return 'bg-danger';
            return 'bg-warning text-dark';
        };

        const allDistributed = computed(() =>
            receiveItems.value.length > 0 &&
            receiveItems.value.every(item => {
                const dist = itemDistributed(item);
                return Math.abs(dist - item.totalQty) < 0.001 &&
                    item.distributions.length > 0 &&
                    item.distributions.every(d => d.inventory_store_id !== '' && (parseFloat(d.quantity) || 0) > 0);
            })
        );

        const addDistribution = (item) => {
            item.distributions.push({ inventory_store_id: '', quantity: 0 });
        };

        const removeDistribution = (item, dIdx) => {
            item.distributions.splice(dIdx, 1);
        };

        const confirmReceive = async () => {
            receiveSaving.value = true;
            try {
                const items = [];
                for (const item of receiveItems.value) {
                    for (const dist of item.distributions) {
                        items.push({ id: item.id, inventory_store_id: dist.inventory_store_id, quantity: dist.quantity });
                    }
                }

                const { data } = await axios.post(`/inventory/supplier-invoice/receive/${props.invoice_id}`, { items });

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('receiveInvoiceModal'))?.hide();
                    Swal.fire("Pedidos enviados", data.message, "success");
                    await load();
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            } catch (e) {
                const msg = e.response?.data?.message ?? "Ocurrió un error.";
                Swal.fire("Error", msg, "error");
            } finally {
                receiveSaving.value = false;
            }
        };

        const denyInvoice = async () => {
            const confirm = await Swal.fire({
                title: '¿Denegar factura?',
                text: 'La factura quedará marcada como Denegada y no podrá recibirse.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, denegar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!confirm.isConfirmed) return;

            denying.value = true;
            try {
                const { data } = await axios.post(`/inventory/supplier-invoice/deny/${props.invoice_id}`);
                if (data.success) {
                    Swal.fire("Denegada", data.message, "success");
                    await load();
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message ?? "Ocurrió un error.", "error");
            } finally {
                denying.value = false;
            }
        };

        const formatCurrency = (val) => Number(val ?? 0).toLocaleString("es-MX", { minimumFractionDigits: 2 });
        const statusBadge = (s) => ({
            "bg-warning text-dark": s === "pending",
            "bg-info text-dark":    s === "dispatched",
            "bg-success":           s === "received",
            "bg-danger":            s === "cancelled" || s === "denied",
        });

        return {
            invoice, loading, stores, loadingStores, receiveItems, receiveSaving, denying,
            allDistributed, openReceiveModal, confirmReceive, denyInvoice,
            itemDistributed, distBadgeClass, addDistribution, removeDistribution,
            formatCurrency, statusBadge, purchaseTypeLabel, purchaseTypeBadge,
        };
    },
};
</script>
