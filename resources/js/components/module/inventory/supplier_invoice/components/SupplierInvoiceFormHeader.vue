<template>
    <div class="row g-3">
        <div class="col-md-6">
            <SelectLongOptions
                :property="supplierField"
                v-model="form.supplier_id"
                :errors="errors"
                @update-field="(f) => { form.supplier_id = f.value; $emit('supplier-change'); }"
            />
        </div>
        <div class="col-md-6">
            <SelectLongOptions
                :key="'vendor-' + vendorKey"
                :property="vendorField"
                v-model="form.supplier_vendor_id"
                :errors="errors"
                @update-field="(f) => form.supplier_vendor_id = f.value"
            />
        </div>
        <div class="col-md-6">
            <label class="form-label">No. Factura</label>
            <div class="form-group">
                <input
                    type="text"
                    class="form-control"
                    name="invoice_number"
                    v-model="form.invoice_number"
                    placeholder="Generando..."
                />
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">
                Fecha <span class="text-danger">*</span>
            </label>
            <input
                type="date"
                class="form-control"
                :class="{ 'is-invalid': errors.has('date') }"
                v-model="form.date"
            />
            <div v-if="errors.has('date')" class="invalid-feedback">
                {{ errors.get('date') }}
            </div>
        </div>
        <div class="col-12">
            <InputTextArea
                :property="fields.notes"
                :modelValue="form.notes"
                :errors="errors"
                @update-field="(f) => form.notes = f.value"
            />
        </div>
    </div>
</template>

<script>
import InputTextArea from "../../../../../shared/InputTextArea.vue";
import SelectLongOptions from "../../../../../shared/SelectLongOptions.vue";

export default {
    name: "SupplierInvoiceFormHeader",
    components: {
        InputTextArea,
        SelectLongOptions,
    },
    props: {
        form:          { type: Object, required: true },
        errors:        { type: Object, required: true },
        supplierField: { type: Object, required: true },
        vendorField:   { type: Object, required: true },
        vendorKey:     { type: Number, required: true },
        fields:        { type: Object, required: true },
    },
    emits: ['supplier-change'],
};
</script>
