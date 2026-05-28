<template>
    <div class="card mb-3">
        <div
            class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2"
        >
            <div>
                <h5 class="mb-1">{{ supplier.name }}</h5>
                <span
                    class="badge"
                    :class="
                        supplier.status === 'active'
                            ? 'bg-success'
                            : 'bg-secondary'
                    "
                >
                    {{ supplier.status_name }}
                </span>
                <small class="text-muted ms-3" v-if="supplier.rfc"
                    >RFC: {{ supplier.rfc }}</small
                >
                <small class="text-muted ms-3" v-if="supplier.phone"
                    ><i class="fas fa-phone me-1"></i
                    >{{ supplier.phone }}</small
                >
                <small class="text-muted ms-3" v-if="supplier.email"
                    ><i class="fas fa-envelope me-1"></i
                    >{{ supplier.email }}</small
                >
            </div>
            <a
                :href="`/inventory/supplier-invoice/create`"
                class="btn btn-primary btn-sm"
            >
                <i class="fas fa-file-invoice me-1"></i> Nueva Factura
            </a>
        </div>
    </div>
</template>

<script>
export default {
    name: "SupplierHeader",
    props: {
        supplier: Object,
    },
    setup(props) {
        const supplier = props.supplier;

        return { supplier };
    },
};
</script>
