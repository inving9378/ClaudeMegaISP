<template>
    <div class="p-4 ml-xl-3 border shadow-low">
        <h6>CUENTAS DE PAGO</h6>
        <hr class="mb-2">
        <div class="table-responsive">
            <table class="table table-striped mb-0">

                <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <th scope="row">1</th>
                    <td>PayPal</td>
                    <td>
<!--                        <span class="badge badge-soft-success font-size-12">Activo</span>-->
                        <span class="badge badge-soft-danger font-size-12">Inactivo</span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
export default {
    name: "ClientPaymentAccount"
}
</script>

<style scoped>

</style>
