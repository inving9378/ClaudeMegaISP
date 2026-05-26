<template>
    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Productos de la Factura</h6>
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                @click="$emit('add-item')"
                :disabled="!form.supplier_id"
            >
                <i class="fas fa-plus me-1"></i> Agregar Producto
            </button>
        </div>
        <div v-if="errors.has('items')" class="alert alert-danger py-2 small mb-2">
            {{ errors.get('items') }}
        </div>
        <div v-if="!form.supplier_id" class="alert alert-info py-2 small">
            Seleccione un proveedor para ver su catálogo de productos.
        </div>
        <div class="table-responsive" v-else>
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Artículo</th>
                        <th style="width: 140px">Tipo de Compra</th>
                        <th style="width: 110px">Cantidad</th>
                        <th style="width: 120px">Cant. Mín. Vol.</th>
                        <th style="width: 160px">Precio</th>
                        <th style="width: 130px">Subtotal</th>
                        <th style="width: 40px"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="form.items.length === 0">
                        <td colspan="7" class="text-center text-muted">
                            Agrega al menos un producto
                        </td>
                    </tr>
                    <tr v-for="(item, idx) in form.items" :key="idx">
                        <td>
                            <SelectPaginated
                                :key="'product-' + idx + '-' + form.supplier_id"
                                :property="getProductField(idx)"
                                :modelValue="item.inventory_item_id"
                                :errors="errors"
                                @update-field="(f) => $emit('item-field-change', item, f)"
                            />
                        </td>
                        <td>
                            <select
                                 class="form-select form-select-sm"
                                 v-model="item.purchase_type"
                                 @change="$emit('purchase-type-change', item)"
                             >
                                 <option value="unit">Por Unidad</option>
                                 <option value="bulk" :disabled="!item.catalog_data?.bulk_price">
                                     Por Volumen
                                 </option>
                             </select>
                        </td>
                        <td>
                            <input
                                type="number"
                                class="form-control form-control-sm"
                                :class="{ 'is-invalid': errors.has(`items[${idx}].quantity`) || isBelowMin(item) }"
                                v-model="item.quantity"
                                min="1"
                                step="1"
                            />
                            <div v-if="errors.has(`items[${idx}].quantity`)" class="invalid-feedback small">
                                {{ errors.get(`items[${idx}].quantity`) }}
                            </div>
                            <small v-if="isBelowMin(item)" class="text-danger d-block" style="font-size:0.7rem">
                                Mínimo {{ item.catalog_data.bulk_min_quantity }}
                            </small>
                        </td>
                        <td>
                            <input
                                type="text"
                                class="form-control form-control-sm bg-light"
                                :value="item.purchase_type === 'bulk' ? (item.catalog_data?.bulk_min_quantity ?? '—') : '—'"
                                readonly
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                class="form-control form-control-sm"
                                :class="{ 'is-invalid': errors.has(`items[${idx}].store_price`) }"
                                v-model="item.store_price"
                                @input="$emit('item-cost-edit', item)"
                                min="0"
                                step="0.01"
                            />
                            <small
                                v-if="item.catalog_data"
                                class="text-muted d-block"
                                style="font-size:0.7rem;line-height:1.3"
                            >
                                <template v-if="item.purchase_type === 'bulk'">
                                    Vol.: {{ formatCurrency(item.catalog_data.bulk_price ?? 0) }}
                                </template>
                                <template v-else>
                                    Base: {{ formatCurrency(item.catalog_data.base_price ?? 0) }}
                                </template>
                                <template v-if="item.catalog_data.price != null">
                                    · Últ.: <strong>{{ formatCurrency(item.catalog_data.price) }}</strong>
                                </template>
                            </small>
                            <div v-if="errors.has(`items[${idx}].store_price`)" class="invalid-feedback small">
                                {{ errors.get(`items[${idx}].store_price`) }}
                            </div>
                        </td>
                        <td class="text-end fw-bold">
                            {{ formatCurrency(itemSubtotal(item)) }}
                        </td>
                        <td class="text-center">
                            <a href="javascript:void(0)" @click="$emit('remove-item', idx)" class="text-danger">
                                <i class="fas fa-times"></i>
                            </a>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="form.items.length > 0" class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Total:</td>
                        <td class="text-end fw-bold">{{ formatCurrency(total) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>

<script>
import SelectPaginated from "../../../../../shared/SelectPaginated.vue";

export default {
    name: "SupplierInvoiceItemsTable",
    components: { SelectPaginated },
    props: {
        form:            { type: Object,   required: true },
        errors:          { type: Object,   required: true },
        catalogItems:    { type: Array,    required: true },
        total:           { type: Number,   required: true },
        itemSubtotal:    { type: Function, required: true },
        formatCurrency:  { type: Function, required: true },
        getProductField: { type: Function, required: true },
        isBelowMin:      { type: Function, required: true },
    },
    emits: ['add-item', 'item-field-change', 'purchase-type-change', 'item-cost-edit', 'remove-item'],
};
</script>
