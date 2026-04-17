<template>
    <div
        class="
                row
                card-header
                customer-billing-sticky-sidebar
                spl-sticky-sidebar
                border border-dark
            "
    >
        <div
            class="
                    col-lg-12 col-md-12
                    customer-billing-sticky-sidebar-inner
                "
        >
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-7 col-md-7 col-sm-7">
                            <b class="customer-name-wrapper"
                            >MARIA DE LOS ANGELES CRUZ LOPEZ (MARIA DE
                                LOS ANGELES CRUZ LOPEZ)</b
                            >
                            |
                            <span class="customer-billing-balance-title"
                            >Account balance:</span
                            ><b class="customer-balance">-369.07 pesos</b>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div
                                class="float-right customer-buttons-wrapper"
                            >
                                <input
                                    type="button"
                                    value="Charge &amp; Invoice"
                                    id="customers_billing_overview_to_bill"
                                    class="btn btn-success btn-sm mr-1"
                                />
                                <input
                                    type="button"
                                    value="Calendar"
                                    id="customers_billing_overview_payment_calendar"
                                    class="btn btn-primary btn-sm mr-1"
                                />
                                <input
                                    type="button"
                                    value="Cancelar el último cargo"
                                    id="customers_billing_overview_cancel_to_bill_button"
                                    class="btn btn-warning btn-sm mr-1"
                                />
                                <input
                                    type="submit"
                                    value="Guardar"
                                    class="btn btn-primary btn-sm"
                                    id="admin_customers_billing_settings_submit_top"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "ViewTopNameWithBalance"
}
</script>

<style scoped>

</style>
