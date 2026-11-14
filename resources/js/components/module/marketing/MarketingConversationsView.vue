<template>
  <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
    <div class="mkt-bandeja-pantalla">
      <mkt-encabezado-pantalla
        titulo="Conversaciones"
        con-selector-empresa
        @contexto="(c) => (contexto = c)"
      >
        <template v-if="estado !== 'sin_permiso'" #filtros>
          <q-input
            v-model="search"
            dense
            outlined
            clearable
            placeholder="Buscar…"
            class="mkt-filtro--busqueda"
            aria-label="Buscar conversaciones"
            @update:model-value="debouncedLoad"
          >
            <template #prepend><q-icon name="search" size="18px" /></template>
          </q-input>
          <q-select
            :model-value="activeFilter"
            :options="filterOptions"
            option-value="key"
            option-label="label"
            emit-value
            map-options
            dense
            outlined
            options-dense
            label="Mostrar"
            aria-label="Filtrar conversaciones"
            @update:model-value="setFilter"
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps" class="mkt-bandeja__opcion">
                <q-item-section>{{ scope.opt.label }}</q-item-section>
              </q-item>
            </template>
          </q-select>
        </template>
        <template #acciones>
          <mkt-insignia-estado v-if="totalUnread > 0" :texto="`${totalUnread} sin leer`" tono="primario" />
        </template>

      <mkt-estado-vacio
        v-if="estado === 'sin_permiso'"
        icono="lock"
        titulo="No puedes ver las conversaciones"
        texto="No tienes acceso a las conversaciones. Si lo necesitas, pídelo a Irving."
      />

      <mkt-estado-vacio v-else-if="estado === 'error' && conversations.length === 0" icono="cloud_off" titulo="No se pudieron cargar las conversaciones" :texto="mensajeError" />

      <section v-else class="mkt-bandeja" :class="{ 'mkt-bandeja--detalle': !!selectedConv }" aria-label="Bandeja de conversaciones">
        <!-- Lista -->
        <div class="mkt-bandeja__lista">
          <div ref="listEl" class="mkt-bandeja__desplazable">
            <div v-if="loading && conversations.length === 0" class="mkt-bandeja__esqueleto" aria-busy="true">
              <div v-for="n in 5" :key="n" class="mkt-bandeja__esqueleto-fila">
                <q-skeleton type="QAvatar" size="40px" />
                <div class="mkt-bandeja__esqueleto-textos">
                  <q-skeleton type="text" width="60%" />
                  <q-skeleton type="text" width="85%" />
                </div>
              </div>
            </div>
            <template v-else>
              <conversation-list-item
                v-for="conv in conversations"
                :key="conv.id"
                :conversation="conv"
                :active="selectedConv?.id === conv.id"
                @select="openConversation"
              />
              <mkt-estado-vacio
                v-if="!loading && conversations.length === 0"
                icono="chat_bubble_outline"
                :titulo="activeFilter === 'all' && !search ? 'Aún no hay conversaciones' : 'Ninguna conversación coincide'"
                :texto="activeFilter === 'all' && !search ? 'Cuando un lead escriba por WhatsApp, su conversación aparecerá aquí.' : 'Prueba con otro filtro o búsqueda.'"
              />
            </template>
            <div v-if="hasMore && !loading" class="mkt-bandeja__mas">
              <mkt-boton tipo="enlace" etiqueta="Cargar más" :cargando="cargandoMas" @click="loadMore" />
            </div>
          </div>
        </div>

        <!-- Detalle -->
        <div class="mkt-bandeja__detalle">
          <mkt-estado-vacio
            v-if="!selectedConv && estadoDetalle !== 'no_encontrado'"
            icono="forum"
            titulo="Elige una conversación"
            texto="Selecciona una conversación de la lista para leerla y responder."
          />
          <mkt-estado-vacio
            v-else-if="!selectedConv"
            icono="search_off"
            titulo="No encontrado"
            texto="Esta conversación no existe o ya no está disponible."
          />

          <template v-else>
            <header class="mkt-bandeja__cabeza">
              <q-btn v-if="isMobile" flat dense round icon="arrow_back" aria-label="Volver a la lista" @click="cerrarDetalle" />
              <span class="mkt-bandeja__avatar" aria-hidden="true">{{ initial(selectedConv.lead) }}</span>
              <div class="mkt-bandeja__quien">
                <div class="mkt-bandeja__nombre">{{ selectedConv.lead?.full_name || selectedConv.lead?.phone || "—" }}</div>
                <div class="mkt-bandeja__sub">
                  <lead-status-badge v-if="selectedConv.lead?.status" :status="selectedConv.lead.status" />
                  <mkt-insignia-estado v-if="selectedConv.status === 'closed'" texto="Cerrada" tono="neutro" />
                </div>
              </div>
              <agent-toggle-button v-model="selectedConv.ai_handled" @toggled="onToggleAi" />
              <q-btn flat dense round icon="more_vert" aria-label="Más acciones">
                <q-menu anchor="bottom right" self="top right">
                  <q-list dense class="mkt-bandeja__menu">
                    <q-item v-if="selectedConv.lead?.id" clickable v-close-popup @click="goToLead">
                      <q-item-section avatar><q-icon name="person" size="18px" /></q-item-section>
                      <q-item-section>Ver ficha del lead</q-item-section>
                    </q-item>
                    <q-item v-if="selectedConv.status !== 'closed'" clickable v-close-popup @click="closeConversation">
                      <q-item-section avatar><q-icon name="task_alt" size="18px" /></q-item-section>
                      <q-item-section>Marcar como cerrada</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </header>

            <div ref="messagesEl" class="mkt-bandeja__mensajes" @scroll="onMessagesScroll">
              <div v-if="loadingMoreMessages" class="mkt-bandeja__cargando"><q-spinner /></div>
              <div v-if="loadingMessages && messages.length === 0" class="mkt-bandeja__cargando"><q-spinner size="28px" /></div>
              <template v-for="group in groupedMessages" :key="group.date">
                <message-day-divider :date="group.date" />
                <message-bubble v-for="msg in group.messages" :key="msg.id" :message="msg" />
              </template>
              <mkt-estado-vacio v-if="messages.length === 0 && !loadingMessages" icono="chat" titulo="Sin mensajes" texto="Esta conversación todavía no tiene mensajes." />
            </div>

            <footer class="mkt-bandeja__escribir">
              <q-input
                v-model="newMessage"
                outlined
                dense
                autogrow
                class="mkt-bandeja__entrada"
                :placeholder="selectedConv.status === 'closed' ? 'La conversación está cerrada' : 'Escribe un mensaje…'"
                :disable="selectedConv.status === 'closed'"
                aria-label="Mensaje"
                @keydown.enter.exact.prevent="sendMessage"
              />
              <mkt-boton
                tipo="principal"
                etiqueta="Enviar"
                icono-fa="fa-paper-plane"
                :deshabilitado="!newMessage.trim() || selectedConv.status === 'closed'"
                :cargando="sending"
                @click="sendMessage"
              />
            </footer>
          </template>
        </div>
      </section>
      </mkt-encabezado-pantalla>
    </div>
  </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import {
  MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktBoton,
  mensajeDeError,
} from "./kit";
import ConversationListItem from "./ConversationListItem.vue";
import MessageBubble from "./MessageBubble.vue";
import MessageDayDivider from "./MessageDayDivider.vue";
import AgentToggleButton from "./AgentToggleButton.vue";
import LeadStatusBadge from "./LeadStatusBadge.vue";

/**
 * Conversaciones (/marketing/conversations[/{id}]) — Fase 1: rediseño con el kit.
 * La lógica del bot NO cambia: mismos endpoints, mismos sondeos (lista 5 s, mensajes 3 s) y mismos botones.
 */
export default {
  name: "MarketingConversationsView",
  components: {
    MktRaiz, MktEncabezadoPantalla, MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktBoton,
    ConversationListItem, MessageBubble, MessageDayDivider, AgentToggleButton, LeadStatusBadge,
  },
  props: {
    initialConversationId: { type: Number, default: null },
    authUserId: { type: Number, default: null },
  },
  data() {
    return {
      contexto: null,
      estado: "cargando", // cargando | listo | error | sin_permiso
      estadoDetalle: "", // "" | no_encontrado
      mensajeError: "",
      conversations: [], selectedConv: null,
      messages: [], newMessage: "", sending: false,
      loading: false, loadingMessages: false, loadingMoreMessages: false, cargandoMas: false,
      search: "", activeFilter: "all", page: 1, hasMore: false,
      totalUnread: 0, isMobile: window.innerWidth < 768,
      _listTimer: null, _msgTimer: null, _searchTimer: null,
      filterOptions: [
        { key: "all", label: "Todas" },
        { key: "unread", label: "Sin leer" },
        { key: "mine", label: "Mías" },
        { key: "ai_on", label: "Con bot" },
        { key: "ai_off", label: "Sin bot" },
        { key: "closed", label: "Cerradas" },
      ],
    };
  },
  computed: {
    groupedMessages() {
      const groups = [];
      let currentDate = null;
      for (const msg of this.messages) {
        const dateStr = new Date(msg.sent_at).toDateString();
        if (dateStr !== currentDate) {
          currentDate = dateStr;
          groups.push({ date: msg.sent_at, messages: [] });
        }
        groups[groups.length - 1].messages.push(msg);
      }
      return groups;
    },
  },
  mounted() {
    this.loadConversations();
    this._listTimer = setInterval(() => this.loadConversations(true), 5000);
    window.addEventListener("resize", this.alRedimensionar);
    if (this.initialConversationId) this.loadConversationById(this.initialConversationId);
  },
  beforeUnmount() {
    clearInterval(this._listTimer);
    clearInterval(this._msgTimer);
    clearTimeout(this._searchTimer);
    window.removeEventListener("resize", this.alRedimensionar);
  },
  methods: {
    alRedimensionar() {
      this.isMobile = window.innerWidth < 768;
    },
    async loadConversations(silent = false) {
      if (!silent) this.loading = true;
      try {
        const params = { per_page: 30, page: 1, search: this.search };
        if (this.activeFilter === "unread") params.unread_only = 1;
        if (this.activeFilter === "ai_on") params.ai_handled = 1;
        if (this.activeFilter === "ai_off") params.ai_handled = 0;
        if (this.activeFilter === "closed") params.status = "closed";
        if (this.activeFilter === "mine" && this.authUserId) params.assigned_user_id = this.authUserId;
        const { data } = await axios.get("/api/marketing/conversations", { params });
        this.conversations = data.data ?? data;
        this.hasMore = data.meta?.current_page < data.meta?.last_page;
        this.page = 1;
        this.totalUnread = this.conversations.reduce((s, c) => s + (c.unread_count || 0), 0);
        if (this.selectedConv) {
          const fresh = this.conversations.find((c) => c.id === this.selectedConv.id);
          if (fresh) Object.assign(this.selectedConv, fresh);
        }
        this.estado = "listo";
      } catch (e) {
        if (silent) return; // el sondeo no tumba la pantalla
        if (e?.response?.status === 403) {
          this.estado = "sin_permiso";
          clearInterval(this._listTimer);
          return;
        }
        this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
        this.estado = "error";
      } finally {
        if (!silent) this.loading = false;
      }
    },
    async loadConversationById(id) {
      try {
        const { data } = await axios.get(`/api/marketing/conversations/${id}`);
        this.selectedConv = data.data ?? data;
        this.estadoDetalle = "";
        await this.loadMessages();
        clearInterval(this._msgTimer);
        this._msgTimer = setInterval(() => this.pollMessages(), 3000);
      } catch (e) {
        this.selectedConv = null;
        this.estadoDetalle = e?.response?.status === 404 ? "no_encontrado" : "";
        if (e?.response?.status !== 404) message(mensajeDeError(e, "No se pudo abrir la conversación."), "error");
      }
    },
    async openConversation(conv) {
      this.selectedConv = conv;
      this.estadoDetalle = "";
      await this.loadMessages();
      await axios.post(`/api/marketing/conversations/${conv.id}/mark-as-read`).catch(() => {});
      conv.unread_count = 0;
      this.totalUnread = this.conversations.reduce((s, c) => s + (c.unread_count || 0), 0);
      clearInterval(this._msgTimer);
      this._msgTimer = setInterval(() => this.pollMessages(), 3000);
    },
    cerrarDetalle() {
      this.selectedConv = null;
      clearInterval(this._msgTimer);
    },
    async loadMessages(before = null) {
      if (!this.selectedConv) return;
      this.loadingMessages = true;
      try {
        const params = { limit: 50, ...(before ? { before_id: before } : {}) };
        const { data } = await axios.get(`/api/marketing/conversations/${this.selectedConv.id}/messages`, { params });
        const msgs = data.data ?? data;
        if (before) {
          this.messages = [...msgs, ...this.messages];
        } else {
          this.messages = msgs;
          this.$nextTick(() => this.scrollToBottom());
        }
      } catch (e) {
        if (!before) this.messages = [];
        message(mensajeDeError(e, "No se pudieron cargar los mensajes."), "error");
      } finally {
        this.loadingMessages = false;
      }
    },
    async pollMessages() {
      if (!this.selectedConv) return;
      const { data } = await axios
        .get(`/api/marketing/conversations/${this.selectedConv.id}/messages`, { params: { limit: 10 } })
        .catch(() => ({ data: [] }));
      const msgs = data.data ?? data;
      if (Array.isArray(msgs) && msgs.length > 0) {
        const nuevos = msgs.filter((m) => !this.messages.find((e) => e.id === m.id));
        if (nuevos.length > 0) {
          this.messages.push(...nuevos);
          this.$nextTick(() => this.scrollToBottom());
        }
      }
    },
    async sendMessage() {
      if (!this.newMessage.trim() || !this.selectedConv || this.sending) return;
      this.sending = true;
      const text = this.newMessage;
      this.newMessage = "";
      try {
        await axios.post(`/api/marketing/conversations/${this.selectedConv.id}/send-message`, { text });
        await this.pollMessages();
      } catch (e) {
        this.newMessage = text;
        message(mensajeDeError(e, "No se pudo enviar el mensaje."), "error");
      } finally {
        this.sending = false;
      }
    },
    async onToggleAi() {
      const endpoint = `/api/marketing/conversations/${this.selectedConv.id}/toggle-ai`;
      try {
        await axios.post(endpoint);
      } catch (e) {
        window.console.error("Marketing: fallo al cambiar el bot de la conversación", e?.response?.status, endpoint);
        message("No se pudo cambiar el estado del bot.", "error");
      }
    },
    async closeConversation() {
      const endpoint = `/api/marketing/conversations/${this.selectedConv.id}/close`;
      try {
        await axios.post(endpoint);
      } catch (e) {
        window.console.error("Marketing: fallo al cerrar la conversación", e?.response?.status, endpoint);
        message("No se pudo cerrar la conversación.", "error");
        return;
      }
      this.selectedConv.status = "closed";
      message("Conversación cerrada.");
    },
    async loadMore() {
      if (!this.hasMore || this.cargandoMas) return;
      this.cargandoMas = true;
      try {
        const params = { per_page: 30, page: this.page + 1 };
        const { data } = await axios.get("/api/marketing/conversations", { params });
        this.page += 1;
        this.conversations.push(...(data.data ?? data));
        this.hasMore = data.meta?.current_page < data.meta?.last_page;
      } catch (e) {
        message(mensajeDeError(e, "No se pudieron cargar más conversaciones."), "error");
      } finally {
        this.cargandoMas = false;
      }
    },
    onMessagesScroll(e) {
      if (e.target.scrollTop < 50 && !this.loadingMoreMessages && this.messages.length >= 50) {
        this.loadingMoreMessages = true;
        this.loadMessages(this.messages[0]?.id).finally(() => { this.loadingMoreMessages = false; });
      }
    },
    scrollToBottom() {
      const el = this.$refs.messagesEl;
      if (el) el.scrollTop = el.scrollHeight;
    },
    setFilter(key) {
      this.activeFilter = key;
      this.page = 1;
      this.loadConversations();
    },
    debouncedLoad() {
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => this.loadConversations(), 400);
    },
    goToLead() {
      if (this.selectedConv?.lead?.id) window.location.href = `/marketing/leads/${this.selectedConv.lead.id}`;
    },
    initial(lead) {
      return (lead?.full_name || lead?.phone || "?").charAt(0).toUpperCase();
    },
  },
};
</script>

<style scoped>
.mkt-bandeja-pantalla { min-width: 0; }
/* La bandeja va dentro de la tarjeta del encabezado (una sola tarjeta, como el Listado de Clientes). */
.mkt-bandeja {
  display: grid;
  grid-template-columns: minmax(300px, 380px) minmax(0, 1fr);
  height: max(480px, calc(100vh - 300px));
  border-top: 1px solid var(--mkt-borde);
  overflow: hidden;
}
.mkt-bandeja__opcion { --bs-gutter-x: 0; }
.mkt-bandeja__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-bandeja__lista { display: flex; flex-direction: column; min-width: 0; min-height: 0; border-right: 1px solid var(--mkt-borde); }
.mkt-bandeja__desplazable { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.mkt-bandeja__esqueleto { display: flex; flex-direction: column; }
.mkt-bandeja__esqueleto-fila { display: flex; align-items: center; gap: 12px; padding: 12px var(--mkt-esp-2); border-bottom: 1px solid var(--mkt-borde); }
.mkt-bandeja__esqueleto-textos { flex: 1 1 auto; }
.mkt-bandeja__mas { display: flex; justify-content: center; padding: var(--mkt-esp-1); }
.mkt-bandeja__detalle { display: flex; flex-direction: column; min-width: 0; min-height: 0; justify-content: center; }
.mkt-bandeja--detalle .mkt-bandeja__detalle { justify-content: flex-start; }
.mkt-bandeja__cabeza {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px var(--mkt-esp-2);
  border-bottom: 1px solid var(--mkt-borde);
}
.mkt-bandeja__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  border-radius: 50%;
  font-weight: 600;
  color: var(--mkt-avatar-texto, #4f46e5);
  background: var(--mkt-avatar-fondo, #eef2ff);
}
.mkt-bandeja__quien { flex: 1 1 auto; min-width: 0; }
.mkt-bandeja__nombre { font-weight: 600; color: var(--mkt-texto); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mkt-bandeja__sub { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
.mkt-bandeja__menu { min-width: 220px; }
.mkt-bandeja__mensajes { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: var(--mkt-esp-1) 0; background: var(--mkt-fondo-suave); }
.mkt-bandeja__cargando { display: flex; justify-content: center; padding: var(--mkt-esp-2); }
.mkt-bandeja__escribir { display: flex; align-items: flex-end; gap: var(--mkt-esp-1); padding: 12px var(--mkt-esp-2); border-top: 1px solid var(--mkt-borde); }
.mkt-bandeja__entrada { flex: 1 1 auto; min-width: 0; }
.mkt-bandeja__menu :deep(.q-item) { --bs-gutter-x: 0; }

@media (max-width: 767px) {
  .mkt-bandeja { grid-template-columns: minmax(0, 1fr); height: max(480px, calc(100vh - 240px)); }
  .mkt-bandeja__lista { border-right: 0; }
  .mkt-bandeja__detalle { display: none; }
  .mkt-bandeja--detalle .mkt-bandeja__lista { display: none; }
  .mkt-bandeja--detalle .mkt-bandeja__detalle { display: flex; }
}
@media (max-width: 599px) {
  .mkt-bandeja__escribir { padding: 12px 8px; }
}
</style>
