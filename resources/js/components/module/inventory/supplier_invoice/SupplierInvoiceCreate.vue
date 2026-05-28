<template>
    <div class="card">
        <div class="card-body">
            <form @submit.prevent="onSubmit">
                <SupplierInvoiceFormHeader
                    :form="form"
                    :errors="errors"
                    :supplierField="supplierField"
                    :vendorField="vendorField"
                    :vendorKey="vendorKey"
                    :fields="fields"
                    @supplier-change="onSupplierChange"
                />

                <SupplierInvoiceItemsTable
                    :form="form"
                    :errors="errors"
                    :catalogItems="catalogItems"
                    :total="total"
                    :itemSubtotal="itemSubtotal"
                    :formatCurrency="formatCurrency"
                    :getProductField="getProductField"
                    :isBelowMin="isBelowMin"
                    @add-item="addItem"
                    @item-field-change="onItemFieldChange"
                    @purchase-type-change="onPurchaseTypeChange"
                    @item-cost-edit="onItemCostEdit"
                    @remove-item="removeItem"
                />

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="/inventory/supplier-invoice" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="loading || form.items.length === 0"
                    >
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Guardar Factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import Errors from "../../../../helpers/Errors";

import InputTextArea from "../../../../shared/InputTextArea.vue";
import SelectLongOptions from "../../../../shared/SelectLongOptions.vue";
import SelectPaginated from "../../../../shared/SelectPaginated.vue";
import SupplierInvoiceFormHeader from "./components/SupplierInvoiceFormHeader.vue";
import SupplierInvoiceItemsTable from "./components/SupplierInvoiceItemsTable.vue";

export default {
    name: "SupplierInvoiceCreate",
    components: {
        InputTextArea,
        SelectLongOptions,
        SelectPaginated,
        SupplierInvoiceFormHeader,
        SupplierInvoiceItemsTable,
    },
    setup() {
        const loading = ref(false);
        const errors = reactive(new Errors());
        const vendors = ref([]);
        const catalogItems = ref([]);

        const fields = reactive({
            invoice_number: {
                field: "invoice_number",
                label: "No. Factura",
                placeholder: "Generando...",
                class_col: "full",
                class_label: "form-label",
                class_field: "form-group",
                type: "input-string",
            },
            notes: {
                field: "notes",
                label: "Notas",
                placeholder: "",
                class_col: "full",
                class_label: "form-label",
                class_field: "form-group",
                type: "input-text-area",
            },
        });

        const supplierField = computed(() => ({
            field: "supplier_id",
            label: "Proveedor",
            placeholder: "Seleccione proveedor...",
            class_col: "full",
            class_label: "form-label",
            class_field: "form-group",
            type: "select",
            search: {
                model: "App\\Models\\Supplier",
                id: "id",
                text: "name",
                order_by: "name",
            },
        }));

        const vendorField = computed(() => {
            const supplierId = parseInt(form.value.supplier_id);
            return {
                field: "supplier_vendor_id",
                label: "Vendedor del Proveedor",
                placeholder: "Sin vendedor",
                class_col: "full",
                class_label: "form-label",
                class_field: "form-group",
                type: "select",
                disabled: !supplierId,
                search: {
                    model: "App\\Models\\SupplierVendor",
                    id: "id",
                    text: "name",
                    order_by: "name",
                    extra_fields: ["last_name"],
                    extra_text: "{name} {last_name}",
                    filters: { supplier_id: supplierId || 0 },
                },
            };
        });

        const form = ref({
            supplier_id: "",
            supplier_vendor_id: "",
            invoice_number: "",
            date: new Date().toISOString().split("T")[0],
            notes: "",
            items: [],
        });

        const vendorKey = ref(0);

        const getCatalogEntry = (inventoryItemId) =>
            catalogItems.value.find((p) => p.inventory_item_id == inventoryItemId) ?? null;

        const defaultPriceFor = (entry, purchaseType) => {
            if (!entry) return 0;
            if (purchaseType === "bulk") return parseFloat(entry.bulk_price) || 0;
            return parseFloat(entry.base_price) || 0;
        };

        const isBelowMin = (item) => {
            if (item.purchase_type !== "bulk") return false;
            const min = item.catalog_data?.bulk_min_quantity;
            const qty = parseFloat(item.quantity) || 0;
            return min ? qty < min : false;
        };

        const itemSubtotal = (item) => {
            const price = parseFloat(item.store_price) || 0;
            const qty   = parseFloat(item.quantity) || 0;
            return price * qty;
        };

        const total = computed(() =>
            form.value.items.reduce((acc, i) => acc + itemSubtotal(i), 0)
        );

        const onItemCostEdit = (item) => {
            item.price_changed = true;
        };

        const onItemFieldChange = (item, { value }) => {
            item.inventory_item_id = value;
            item.catalog_data = getCatalogEntry(value);
            item.purchase_type = "unit";
            item.price_changed = false;
            item.store_price = defaultPriceFor(item.catalog_data, "unit");
            item.quantity = 1;
        };

        const onPurchaseTypeChange = (item) => {
            if (!item.catalog_data) return;
            if (item.purchase_type === "bulk") {
                item.store_price = defaultPriceFor(item.catalog_data, "bulk");
                item.quantity = item.catalog_data.bulk_min_quantity ?? 1;
                item.price_changed = false;
            } else if (item.purchase_type === "unit") {
                item.store_price = defaultPriceFor(item.catalog_data, "unit");
                item.price_changed = false;
            }
        };

        const addItem = () => {
            form.value.items.push({
                inventory_item_id: "",
                purchase_type: "unit",
                quantity: 1,
                store_price: 0,
                catalog_data: null,
                price_changed: false,
            });
        };

        const removeItem = (idx) => form.value.items.splice(idx, 1);

        const getProductField = (idx) => ({
            field: `items[${idx}].inventory_item_id`,
            label: "Artículo",
            placeholder: "Seleccione...",
            class_col: "full",
            class_label: "form-label",
            class_field: "form-group",
            type: "select",
            options: catalogItems.value.map(item => ({
                value: item.inventory_item_id,
                label: item.name,
            })),
            paginated: null,
        });

        const generateNumber = async () => {
            try {
                const { data } = await axios.get("/inventory/supplier-invoice/generate-number");
                if (data.success) form.value.invoice_number = data.number;
            } catch (e) {
                console.error(e);
            }
        };

        onMounted(generateNumber);

        const onSupplierChange = async () => {
            form.value.supplier_vendor_id = "";
            form.value.items = [];
            vendors.value = [];
            catalogItems.value = [];
            vendorKey.value++;

            if (!form.value.supplier_id) return;
            try {
                const [vRes, cRes] = await Promise.all([
                    axios.get(`/inventory/supplier/${form.value.supplier_id}/vendors/get-all`),
                    axios.get(`/inventory/supplier/${form.value.supplier_id}/product-prices`),
                ]);
                vendors.value = vRes.data.data ?? [];
                catalogItems.value = cRes.data.data ?? [];
            } catch (e) {
                console.error(e);
            }
        };

        const validateForm = () => {
            errors.clear();
            let valid = true;

            if (!form.value.supplier_id) {
                errors.set("supplier_id", "El proveedor es obligatorio.");
                valid = false;
            }
            if (!form.value.date) {
                errors.set("date", "La fecha es obligatoria.");
                valid = false;
            }
            if (form.value.items.length === 0) {
                errors.set("items", "Debe agregar al menos un producto.");
                valid = false;
            }
            form.value.items.forEach((item, idx) => {
                if (!item.inventory_item_id) {
                    errors.set(`items[${idx}].inventory_item_id`, "Seleccione un artículo.");
                    valid = false;
                }
                const qty = parseFloat(item.quantity);
                if (!qty || qty <= 0) {
                    errors.set(`items[${idx}].quantity`, "La cantidad debe ser mayor a 0.");
                    valid = false;
                }
                if (item.purchase_type === "bulk") {
                    const min = item.catalog_data?.bulk_min_quantity;
                    if (min && qty < min) {
                        errors.set(`items[${idx}].quantity`, `La cantidad mínima por volumen es ${min}.`);
                        valid = false;
                    }
                }
                const price = parseFloat(item.store_price);
                if (isNaN(price) || price < 0) {
                    errors.set(`items[${idx}].store_price`, "El precio debe ser mayor o igual a 0.");
                    valid = false;
                }
            });

            return valid;
        };

        const onSubmit = async () => {
            if (!validateForm()) {
                Swal.fire("Error", "Por favor complete todos los campos requeridos.", "error");
                return;
            }

            loading.value = true;
            try {
                const payload = {
                    supplier_id:        form.value.supplier_id,
                    supplier_vendor_id: form.value.supplier_vendor_id || null,
                    invoice_number:     form.value.invoice_number,
                    date:               form.value.date,
                    notes:              form.value.notes,
                    items: form.value.items.map((item) => ({
                        inventory_item_id: item.inventory_item_id,
                        purchase_type:     item.purchase_type,
                        quantity:          item.quantity,
                        store_price:       item.store_price,
                        bulk_quantity:     item.purchase_type === "bulk"
                            ? (item.catalog_data?.bulk_min_quantity ?? null)
                            : null,
                    })),
                };

                const { data } = await axios.post("/inventory/supplier-invoice/add", payload);
                if (data.success) {
                    await Swal.fire("Éxito", data.message, "success");
                    window.location.href = `/inventory/supplier-invoice/show/${data.model.id}`;
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            } catch (e) {
                const msg = e.response?.data?.message ?? "Ocurrió un error inesperado.";
                Swal.fire("Error", msg, "error");
            } finally {
                loading.value = false;
            }
        };

        const formatCurrency = (val) =>
            Number(val ?? 0).toLocaleString("es-MX", { minimumFractionDigits: 2 });

        return {
            form,
            errors,
            fields,
            supplierField,
            vendorField,
            vendorKey,
            getProductField,
            loading,
            vendors,
            catalogItems,
            total,
            itemSubtotal,
            isBelowMin,
            onSupplierChange,
            onItemFieldChange,
            onPurchaseTypeChange,
            onItemCostEdit,
            addItem,
            removeItem,
            onSubmit,
            formatCurrency,
        };
    },
};
</script>
