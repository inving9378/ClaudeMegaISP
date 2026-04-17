<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light ms-auto"
            @click="reload"
        >
            Refrescar
        </button>
    </div>
    <Datatable
        module="configuracion/service_in_address_list"
        model="ServiceInAddressList"
        list="Listado de Servicios en el Address List"
        @table="table"
    ></Datatable>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "ServiceInAddressListListar",
    components: { Datatable },
    props: {},
    setup(props) {
        const title = ref("Crear Equipo");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/configuracion/service_in_address_list/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", `.fa-edit`, function (e) {
                if (
                    confirm(
                        "Esta seguro que desea sacar del address list a este cliente"
                    )
                ) {
                    sacarDelAddressList($(e.target).parent().attr("id-item"));
                }
            });
        });

        const sacarDelAddressList = (id) => {
            axios["post"](`/configuracion/service_in_address_list/mikrotik-remove-address-list/${id}`).then(
                (response) => {
                    if (response.data.error) {
                        alert(response.data.error);
                        return true;
                    } else {
                        toastr.success(
                            `Elemento eliminado correctamente`,
                            'Service In Address List'
                        );
                        return true;
                    }
                }
            );
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        return {
            title,
            table,
            reload,
            reloadCrud,
        };
    },
};
</script>

<style scoped></style>
