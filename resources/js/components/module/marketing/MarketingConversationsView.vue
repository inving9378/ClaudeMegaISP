<template>
  <div class="conversations-layout" style="height: calc(100vh - 70px); display: flex; overflow: hidden;">
    <!-- Left: Conversation list -->
    <div class="conv-list-pane q-pa-none" :style="{ width: selectedConv ? '35%' : '100%', display: isMobile && selectedConv ? 'none' : 'flex', flexDirection: 'column', borderRight: '1px solid #e0e0e0' }">
      <!-- Header -->
      <div class="q-pa-md row items-center">
        <div class="col text-h6 text-weight-bold">Conversaciones</div>
        <q-badge v-if="totalUnread > 0" color="primary" :label="totalUnread" rounded />
      </div>

      <!-- Filters -->
      <div class="q-px-md q-pb-sm">
        <q-input v-model="search" dense outlined placeholder="Buscar..." clearable class="q-mb-sm"
          @update:model-value="debouncedLoad" />
        <div class="row q-gutter-xs">
          <q-chip v-for="f in filterOptions" :key="f.key" dense :outline="activeFilter !== f.key"
            :color="activeFilter === f.key ? 'primary' : 'grey'" clickable @click="setFilter(f.key)">
            {{ f.label }}
          </q-chip>
        </div>
      </div>

      <!-- List -->
      <div class="col overflow-auto" ref="listEl">
        <div v-if="loading && conversations.length === 0" class="text-center q-pa-xl">
          <q-spinner color="primary" />
        </div>
        <q-list separator v-else>
          <conversation-list-item
            v-for="conv in conversations"
            :key="conv.id"
            :conversation="conv"
            :active="selectedConv?.id === conv.id"
            @select="openConversation"
          />
          <q-item v-if="!loading && conversations.length === 0" class="text-grey-6 text-center q-pa-lg">
            <q-item-section>Sin conversaciones</q-item-section>
          </q-item>
        </q-list>
        <div v-if="hasMore && !loading" class="text-center q-pa-sm">
          <q-btn flat size="sm" label="Cargar más" @click="loadMore" />
        </div>
      </div>
    </div>

    <!-- Right: Conversation detail -->
    <div class="conv-detail-pane" :style="{ flex: 1, display: !selectedConv && !isMobile ? 'flex' : (selectedConv ? 'flex' : 'none'), flexDirection: 'column' }">
      <!-- Placeholder -->
      <div v-if="!selectedConv" class="col row items-center justify-center text-grey-5">
        <div class="text-center">
          <q-icon name="chat_bubble_outline" size="80px" class="q-mb-md" />
          <div class="text-h6">Selecciona una conversación</div>
        </div>
      </div>

      <!-- Detail -->
      <template v-else>
        <!-- Header -->
        <div class="conv-header q-pa-md row items-center q-gutter-sm" style="border-bottom:1px solid #e0e0e0">
          <q-btn v-if="isMobile" flat round icon="arrow_back" @click="selectedConv = null" />
          <q-avatar :color="statusColor(selectedConv.lead?.status)" text-color="white" size="40px">
            {{ initial(selectedConv.lead) }}
          </q-avatar>
          <div class="col">
            <div class="text-weight-bold">{{ selectedConv.lead?.full_name || selectedConv.lead?.phone }}</div>
            <div class="text-caption text-grey-6">{{ statusLabel(selectedConv.lead?.status) }}</div>
          </div>
          <agent-toggle-button v-model="selectedConv.ai_handled" @toggled="onToggleAi" />
          <q-btn-dropdown flat round icon="more_vert">
            <q-list>
              <q-item clickable v-close-popup @click="goToLead">
                <q-item-section>Ver lead completo</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="closeConversation">
                <q-item-section>Marcar cerrada</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>

        <!-- Messages area -->
        <div class="col overflow-auto q-pa-sm" ref="messagesEl" @scroll="onMessagesScroll">
          <div v-if="loadingMoreMessages" class="text-center q-pa-sm"><q-spinner /></div>
          <template v-for="(group, idx) in groupedMessages" :key="group.date">
            <message-day-divider :date="group.date" />
            <message-bubble v-for="msg in group.messages" :key="msg.id" :message="msg" />
          </template>
          <div v-if="messages.length === 0 && !loadingMessages" class="text-center text-grey-5 q-pa-xl">Sin mensajes</div>
        </div>

        <!-- Input -->
        <div class="q-pa-md row q-gutter-sm items-end" style="border-top:1px solid #e0e0e0">
          <q-input
            v-model="newMessage"
            class="col"
            outlined
            dense
            autogrow
            placeholder="Escribe un mensaje..."
            :disable="selectedConv.status === 'closed'"
            @keydown.enter.exact.prevent="sendMessage"
          />
          <q-btn
            unelevated
            color="primary"
            icon="send"
            round
            :disable="!newMessage.trim() || selectedConv.status === 'closed'"
            :loading="sending"
            @click="sendMessage"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

const STATUS_COLORS = { new: 'grey-6', contacted: 'blue', qualified: 'amber-7', negotiating: 'orange', won: 'positive', lost: 'negative' };
const STATUS_LABELS = { new: 'Nuevo', contacted: 'Contactado', qualified: 'Calificado', negotiating: 'Negociando', won: 'Ganado', lost: 'Perdido' };

export default {
  name: 'MarketingConversationsView',
  props: {
    initialConversationId: { type: Number, default: null },
    authUserId: { type: Number, default: null },
  },
  data() {
    return {
      conversations: [], selectedConv: null,
      messages: [], newMessage: '', sending: false,
      loading: false, loadingMessages: false, loadingMoreMessages: false,
      search: '', activeFilter: 'all', page: 1, hasMore: false,
      totalUnread: 0, isMobile: window.innerWidth < 768,
      _listTimer: null, _msgTimer: null, _searchTimer: null,
      filterOptions: [
        { key: 'all', label: 'Todas' },
        { key: 'unread', label: 'Sin leer' },
        { key: 'mine', label: 'Mías' },
        { key: 'ai_on', label: 'Bot ON' },
        { key: 'ai_off', label: 'Bot OFF' },
        { key: 'closed', label: 'Cerradas' },
      ],
    };
  },
  computed: {
    groupedMessages() {
      const groups = [];
      let currentDate = null;
      for (const msg of this.messages) {
        const d = new Date(msg.sent_at);
        const dateStr = d.toDateString();
        if (dateStr !== currentDate) {
          currentDate = dateStr;
          groups.push({ date: msg.sent_at, messages: [] });
        }
        groups[groups.length - 1].messages.push(msg);
      }
      return groups;
    }
  },
  mounted() {
    this.loadConversations();
    this._listTimer = setInterval(() => this.loadConversations(true), 5000);
    window.addEventListener('resize', () => { this.isMobile = window.innerWidth < 768; });
    if (this.initialConversationId) {
      this.loadConversationById(this.initialConversationId);
    }
  },
  beforeUnmount() {
    clearInterval(this._listTimer);
    clearInterval(this._msgTimer);
  },
  methods: {
    async loadConversations(silent = false) {
      if (!silent) this.loading = true;
      try {
        const params = { per_page: 30, page: 1, search: this.search };
        if (this.activeFilter === 'unread')  params.unread_only = 1;
        if (this.activeFilter === 'ai_on')   params.ai_handled = 1;
        if (this.activeFilter === 'ai_off')  params.ai_handled = 0;
        if (this.activeFilter === 'closed')  params.status = 'closed';
        if (this.activeFilter === 'mine' && this.authUserId) params.assigned_user_id = this.authUserId;
        const { data } = await axios.get('/api/marketing/conversations', { params });
        this.conversations = data.data ?? data;
        this.hasMore = (data.meta?.current_page < data.meta?.last_page);
        this.totalUnread = this.conversations.reduce((s, c) => s + (c.unread_count || 0), 0);
        // Refresh selected conv data
        if (this.selectedConv) {
          const fresh = this.conversations.find(c => c.id === this.selectedConv.id);
          if (fresh) Object.assign(this.selectedConv, fresh);
        }
      } finally { if (!silent) this.loading = false; }
    },
    async loadConversationById(id) {
      const { data } = await axios.get('/api/marketing/conversations/' + id);
      const conv = data.data ?? data;
      this.selectedConv = conv;
      this.loadMessages();
    },
    async openConversation(conv) {
      this.selectedConv = conv;
      await this.loadMessages();
      await axios.post('/api/marketing/conversations/' + conv.id + '/mark-as-read').catch(() => {});
      conv.unread_count = 0;
      clearInterval(this._msgTimer);
      this._msgTimer = setInterval(() => this.pollMessages(), 3000);
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
      } finally { this.loadingMessages = false; }
    },
    async pollMessages() {
      if (!this.selectedConv) return;
      const { data } = await axios.get(`/api/marketing/conversations/${this.selectedConv.id}/messages`, { params: { limit: 10 } }).catch(() => ({ data: [] }));
      const msgs = data.data ?? data;
      if (msgs.length > 0) {
        const lastId = this.messages[this.messages.length - 1]?.id;
        const newMsgs = msgs.filter(m => !this.messages.find(e => e.id === m.id));
        if (newMsgs.length > 0) {
          this.messages.push(...newMsgs);
          this.$nextTick(() => this.scrollToBottom());
        }
      }
    },
    async sendMessage() {
      if (!this.newMessage.trim() || !this.selectedConv) return;
      this.sending = true;
      const text = this.newMessage;
      this.newMessage = '';
      try {
        await axios.post(`/api/marketing/conversations/${this.selectedConv.id}/send-message`, { text });
        await this.pollMessages();
      } catch {
        this.newMessage = text;
        this.$q?.notify({ type: 'negative', message: 'Error al enviar' });
      } finally { this.sending = false; }
    },
    async onToggleAi(val) {
      const endpoint = `/api/marketing/conversations/${this.selectedConv.id}/toggle-ai`;
      try {
        await axios.post(endpoint);
      } catch (e) {
        console.error('Marketing: fallo al cambiar IA de la conversación', e.response?.status, endpoint);
        this.$q?.notify({ type: 'negative', message: 'No se pudo cambiar el estado de la IA.' });
      }
    },
    async closeConversation() {
      const endpoint = `/api/marketing/conversations/${this.selectedConv.id}/close`;
      try {
        await axios.post(endpoint);
      } catch (e) {
        console.error('Marketing: fallo al cerrar la conversación', e.response?.status, endpoint);
        this.$q?.notify({ type: 'negative', message: 'No se pudo cerrar la conversación.' });
        return;
      }
      this.selectedConv.status = 'closed';
    },
    async loadMore() {
      if (!this.hasMore) return;
      this.page++;
      const params = { per_page: 30, page: this.page };
      const { data } = await axios.get('/api/marketing/conversations', { params });
      this.conversations.push(...(data.data ?? data));
      this.hasMore = (data.meta?.current_page < data.meta?.last_page);
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
      if (this.selectedConv?.lead?.id) {
        window.location.href = '/marketing/leads/' + this.selectedConv.lead.id;
      }
    },
    statusColor(s) { return STATUS_COLORS[s] || 'grey-6'; },
    statusLabel(s) { return STATUS_LABELS[s] || s; },
    initial(lead) { return (lead?.full_name || lead?.phone || '?').charAt(0).toUpperCase(); },
  }
}
</script>

<style scoped>
.conversations-layout { background: #fff; }
.conv-list-pane { min-width: 280px; }
</style>
