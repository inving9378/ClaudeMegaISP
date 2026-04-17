<template>
  <div class="col-sm-12 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="float-right">
          <div class="dropdown">
            <a
              data-toggle="collapse"
              href="#collapseassing"
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

        <div id="collapseassing">
          <!-- section Estado tickets-->
          <div>
            <label>
              Mostrar
              <select
                name="DataTables_Table_0_length"
                @change="changeEventAssignedToMe($event.target.value)"
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
          <table id="assigned" class="table table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Estado</th>
                <th>Prioridad</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in ticketAssignedToMe">
                <td><a :href="`/tickets/ver/${row.id}`">{{ row.id }}</a></td>
                 <td><a :href="`/tickets/ver/${row.id}`">{{ row.topic }}</a></td>
                <td>{{ row.estado }}</td>
                <td>{{ getPriority(row.priority) }}</td>
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
import { onMounted, ref } from "vue";
import { requestTicketAssignedToMe } from "../helper/request";
export default {
  name: "AssignedTicket",

  setup(props) {
    const priorityId = { 1: "Urgente", 2: "Alta", 3: "Normal", 4: "Baja" };
    const ticketAssignedToMe = ref({});

    onMounted(() => {
      getTicketAssignedToMe("Todo");
    });

    const getPriority = (priority) => {
      return priorityId[priority];
    };

    const getTicketAssignedToMe = async (value) => {
      ticketAssignedToMe.value = await requestTicketAssignedToMe(value);
    };

    const changeEventAssignedToMe = async (value) => {
      ticketAssignedToMe.value = await requestTicketAssignedToMe(value);
    };

    return {
      ticketAssignedToMe,
      getPriority,
      changeEventAssignedToMe,
    };
  },
};
</script>


<style scoped>
</style>
