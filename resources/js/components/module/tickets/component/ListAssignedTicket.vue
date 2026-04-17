<template>
  <div class="col-sm-12 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="float-right">
          <div class="dropdown">
            <a
              data-toggle="collapse"
              href="#collapselist"
              role="button"
              aria-expanded="false"
              aria-controls="collapseExample"
            >
              <span class="text-muted">
                <i class="mdi mdi-chevron-down ml-1"> </i>
              </span>
            </a>
          </div>
        </div>

        <div id="collapselist">
          <!-- section Estado tickets-->
          <div>
            <label>
              Mostrar
              <select
                name="DataTables_Table_0_length"
                @change="changeEventAssignedTo($event.target.value)"
                aria-controls="DataTables_Table_0"
                class="
                  custom-select custom-select-sm
                  form-control form-control-sm
                "
              >
                <option value="Todo">Todos</option>
                <option value="Nuevo">Nuevo</option>
                <option value="Trabajo en curso">Trabajo en curso</option>
                <option value="Resueltos">Resueltos</option>
                <option value="Esperando al agente">
                  En espara del Agente
                </option>
                <option value="Esperando al cliente">
                  En espera del Cliente
                </option>
                <option value="Reciclado">Reciclado</option>
                <option value="Cerrado">Cerrado</option>
              </select>
            </label>
          </div>
          <!-- end section Estado tickets-->

          <!-- section Tabla-->
          <table id="assignedadmin" class="table table-striped">
            <thead>
              <tr>
                <th>Asignado</th>
                <th>Cantidad</th>
                <th>Porcentaje</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in ticketAssignedTo">
                <td>{{ row.asignado }}</td>
                <td>{{ row.cantidad }}</td>
                <td>
                  <span class="sr-only">{{ (max = row.porcentaje) }} </span>
                  <div
                    class="progress"
                    style="margin-bottom: 0px"
                    title="Porciento"
                    data-placement="top"
                    data-toggle="popover"
                    data-trigger="hover"
                    data-html="true"
                    data-content="Some data here..."
                  >
                    <div
                      class="progress-bar"
                      role="progressbar"
                      :aria-valuenow="0"
                      aria-valuemin="0"
                      aria-valuemax="100"
                      :style="'width:' + max + '%;'"
                    >
                      {{ max }}%
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- end section tabla -->
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { requestTicketAssignedTo } from "../helper/request";
import { onMounted, ref } from "vue";
export default {
  name: "ListAssignedTicket",
  props: {},
  setup(props) {
    const ticketAssignedTo = ref({});

    onMounted(() => {
      getTicketAssignedTo("Todo");
    });

    const getTicketAssignedTo = async (value) => {
      ticketAssignedTo.value = await requestTicketAssignedTo(value);
    };

    const changeEventAssignedTo = async (value) => {
      ticketAssignedTo.value = await requestTicketAssignedTo(value);
    };

    return {
      ticketAssignedTo,
      changeEventAssignedTo,
    };
  },
};
</script>


<style scoped>
</style>
