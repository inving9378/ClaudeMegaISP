<template>
    <div class="ia-float-btn" @click="toggleChat" :class="{ 'has-pulse': !isOpen }">
        <div class="ia-btn-inner"><i v-if="!isOpen" class="fas fa-robot"></i><i v-else class="fas fa-times"></i></div>
        <span v-if="!isOpen" class="ia-badge">IA</span>
    </div>
    <transition name="ia-slide">
        <div v-if="isOpen" class="ia-chat-panel" :class="{ 'ia-expanded': isExpanded }">
            <div class="ia-header">
                <div class="ia-header-left">
                    <div class="ia-avatar"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="ia-title">Agente IA MegaISP</div>
                        <div class="ia-subtitle"><span class="ia-dot"></span>{{ isTyping ? 'Procesando...' : 'En línea' }}</div>
                    </div>
                </div>
                <div class="ia-header-actions">
                    <button class="ia-btn-icon" @click="isExpanded=!isExpanded"><i :class="isExpanded?'fas fa-compress-alt':'fas fa-expand-alt'"></i></button>
                    <button class="ia-btn-icon" @click="messages=[]"><i class="fas fa-plus"></i></button>
                    <button class="ia-btn-icon" @click="isOpen=false"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div v-if="messages.length===0" class="ia-suggestions">
                <div class="ia-welcome"><i class="fas fa-robot ia-welcome-icon"></i><p>¡Hola! Soy tu Agente IA. ¿En qué puedo ayudarte?</p></div>
                <div class="ia-chips">
                    <button class="ia-chip" v-for="s in suggestions" :key="s.text" @click="sendSuggestion(s.text)">
                        <i :class="s.icon"></i> {{ s.label }}
                    </button>
                </div>
            </div>
            <div class="ia-messages" ref="messagesContainer">
                <div v-for="(msg,i) in messages" :key="i" :class="['ia-msg', msg.role==='user'?'ia-msg-user':'ia-msg-ai']">
                    <div v-if="msg.role==='assistant'" class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body">
                        <div class="ia-msg-text" v-html="fmt(msg.content)"></div>
                        <div v-if="msg.action_type==='estadisticas_clientes'&&msg.action_data" class="ia-stats-grid">
                            <div class="ia-stat-card ia-stat-primary"><div class="ia-stat-num">{{msg.action_data.total}}</div><div class="ia-stat-label">Total</div></div>
                            <div class="ia-stat-card ia-stat-success"><div class="ia-stat-num">{{msg.action_data.activos}}</div><div class="ia-stat-label">Activos</div></div>
                            <div class="ia-stat-card ia-stat-danger"><div class="ia-stat-num">{{msg.action_data.bloqueados}}</div><div class="ia-stat-label">Bloqueados</div></div>
                            <div class="ia-stat-card ia-stat-info"><div class="ia-stat-num">{{msg.action_data.nuevos_mes}}</div><div class="ia-stat-label">Nuevos mes</div></div>
                        </div>
                        <div v-if="(msg.action_type==='buscar_cliente'||msg.action_type==='clientes_morosos')&&msg.action_data" class="ia-result-table">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Estado</th></tr></thead>
                                <tbody>
                                    <tr v-for="c in (msg.action_data.clientes||msg.action_data.morosos)" :key="c.id">
                                        <td><a :href="`${url}/cliente/${c.id}`" target="_blank">{{c.name}} {{c.father_last_name}}</a></td>
                                        <td>{{c.phone}}</td>
                                        <td><span :class="`badge bg-${c.estado==='Activo'?'success':'danger'}`">{{c.estado}}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="msg.action_type==='listar_tareas'&&msg.action_data" class="ia-result-table">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>#</th><th>Título</th><th>Estado</th><th>Prioridad</th></tr></thead>
                                <tbody>
                                    <tr v-for="t in msg.action_data.tareas" :key="t.id">
                                        <td>{{t.id}}</td><td>{{t.title}}</td>
                                        <td><span :class="`badge bg-${sc(t.status)}`">{{t.status}}</span></td>
                                        <td>{{t.priority}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="(msg.action_type==='pagos_del_dia'||msg.action_type==='reporte_ingresos')&&msg.action_data" class="ia-result-summary">
                            <div class="ia-summary-row" v-if="msg.action_data.total_registros!==undefined"><span>Pagos:</span><strong>{{msg.action_data.total_registros}}</strong></div>
                            <div class="ia-summary-row" v-if="msg.action_data.total_monto!==undefined"><span>Total:</span><strong class="text-success">${{fm(msg.action_data.total_monto)}}</strong></div>
                            <div class="ia-summary-row" v-if="msg.action_data.total_ingresos!==undefined"><span>Ingresos ({{msg.action_data.periodo}}):</span><strong class="text-success">${{fm(msg.action_data.total_ingresos)}}</strong></div>
                            <div class="ia-summary-row" v-if="msg.action_data.num_pagos!==undefined"><span>Pagos:</span><strong>{{msg.action_data.num_pagos}}</strong></div>
                        </div>
                        <div class="ia-msg-time">{{ft(msg.timestamp)}}</div>
                    </div>
                </div>
                <div v-if="isTyping" class="ia-msg ia-msg-ai">
                    <div class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body"><div class="ia-typing"><span></span><span></span><span></span></div></div>
                </div>
            </div>
            <div class="ia-input-area">
                <div class="ia-input-row">
                    <textarea ref="inputRef" v-model="inputText" @keydown.enter.exact.prevent="send" @keydown.enter.shift.exact="inputText+='\n'" placeholder="Escribe... (Enter para enviar)" :disabled="isTyping" rows="1" class="ia-input"></textarea>
                    <button class="ia-send-btn" @click="send" :disabled="isTyping||!inputText.trim()"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="ia-input-hint">Shift+Enter = nueva línea</div>
            </div>
        </div>
    </transition>
</template>
<script>
import { ref, nextTick } from 'vue';
import axios from 'axios';
export default {
    name: 'IaChatFloat',
    props: { url: String },
    setup(props) {
        const isOpen=ref(false),isExpanded=ref(false),isTyping=ref(false),inputText=ref(''),messages=ref([]);
        const messagesContainer=ref(null),inputRef=ref(null);
        const suggestions=[
            {text:'dame estadísticas de clientes',label:'Estadísticas',icon:'fas fa-chart-bar'},
            {text:'lista los pagos de hoy',label:'Pagos de hoy',icon:'fas fa-dollar-sign'},
            {text:'muestra las tareas pendientes',label:'Tareas',icon:'fas fa-tasks'},
            {text:'clientes morosos',label:'Morosos',icon:'fas fa-user-slash'},
            {text:'reporte de ingresos del mes',label:'Ingresos',icon:'fas fa-chart-line'},
        ];
        const scroll=()=>nextTick(()=>{const e=messagesContainer.value;if(e)e.scrollTop=e.scrollHeight;});
        const toggleChat=()=>{isOpen.value=!isOpen.value;if(isOpen.value)nextTick(()=>inputRef.value?.focus());};
        const sendSuggestion=(t)=>{inputText.value=t;send();};
        const fmt=(t)=>t?t.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/`(.*?)`/g,'<code>$1</code>').replace(/\n/g,'<br>'):'' ;
        const ft=(d)=>d?new Date(d).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}):'';
        const fm=(n)=>parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2});
        const sc=(s)=>({ToDo:'secondary',InProgress:'warning',Done:'success',PostponedByClient:'info'}[s]||'secondary');
        const send=async()=>{
            const text=inputText.value.trim();
            if(!text||isTyping.value)return;
            messages.value.push({role:'user',content:text,timestamp:new Date()});
            inputText.value='';isTyping.value=true;scroll();
            const history=messages.value.slice(-10).map(m=>({role:m.role,content:m.content}));
            try{
                const res=await axios.post(`${props.url}/ia/chat`,{message:text,history:history.slice(0,-1),context:window.location.pathname});
                if(res.data.success){
                    messages.value.push({role:'assistant',content:res.data.message,action_type:res.data.action_type,action_data:res.data.action_result,timestamp:new Date()});
                }else{
                    messages.value.push({role:'assistant',content:'❌ '+(res.data.error||'Error'),timestamp:new Date()});
                }
            }catch(e){
                messages.value.push({role:'assistant',content:'❌ Error de conexión. Verifica CLAUDE_API_KEY en .env',timestamp:new Date()});
            }finally{isTyping.value=false;scroll();nextTick(()=>inputRef.value?.focus());}
        };
        return {isOpen,isExpanded,isTyping,inputText,messages,messagesContainer,inputRef,suggestions,toggleChat,sendSuggestion,send,fmt,ft,fm,sc};
    }
};
</script>
<style scoped>
.ia-float-btn{position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 20px rgba(99,102,241,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s}
.ia-float-btn:hover{transform:scale(1.1)}
.ia-float-btn.has-pulse::after{content:'';position:absolute;width:100%;height:100%;border-radius:50%;background:rgba(99,102,241,.3);animation:ia-pulse 2s infinite}
@keyframes ia-pulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.ia-btn-inner{color:white;font-size:22px;z-index:1}.ia-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:9px;font-weight:700;border-radius:10px;padding:2px 5px;z-index:2}
.ia-chat-panel{position:fixed;bottom:90px;right:24px;z-index:9998;width:380px;height:560px;background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(99,102,241,.15)}
.ia-chat-panel.ia-expanded{width:680px;height:80vh}
.ia-header{background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ia-header-left{display:flex;align-items:center;gap:10px}
.ia-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:white;font-size:18px}
.ia-title{color:white;font-weight:600;font-size:14px}.ia-subtitle{color:rgba(255,255,255,.8);font-size:11px;display:flex;align-items:center;gap:4px}
.ia-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.ia-header-actions{display:flex;gap:4px}
.ia-btn-icon{background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
.ia-btn-icon:hover{background:rgba(255,255,255,.3)}
.ia-suggestions{padding:16px;flex-shrink:0}
.ia-welcome{text-align:center;padding:8px 0 12px}
.ia-welcome-icon{font-size:32px;color:#6366f1;margin-bottom:8px;display:block}
.ia-welcome p{color:#64748b;font-size:13px;margin:0}
.ia-chips{display:flex;flex-wrap:wrap;gap:6px}
.ia-chip{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:5px 12px;font-size:12px;cursor:pointer;color:#475569;transition:all .2s;display:flex;align-items:center;gap:5px}
.ia-chip:hover{background:#e0e7ff;border-color:#6366f1;color:#6366f1}
.ia-messages{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:12px}
.ia-messages::-webkit-scrollbar{width:4px}.ia-messages::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}
.ia-msg{display:flex;gap:8px;align-items:flex-start}.ia-msg-user{flex-direction:row-reverse}
.ia-msg-avatar{width:30px;height:30px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-size:13px}
.ia-msg-body{max-width:80%;display:flex;flex-direction:column;gap:4px}.ia-msg-user .ia-msg-body{align-items:flex-end}
.ia-msg-text{padding:10px 13px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word}
.ia-msg-ai .ia-msg-text{background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;border-radius:4px 14px 14px 14px}
.ia-msg-user .ia-msg-text{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:14px 4px 14px 14px}
.ia-msg-time{font-size:10px;color:#94a3b8;padding:0 4px}
.ia-typing{display:flex;gap:4px;padding:8px 12px}
.ia-typing span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ia-bounce .8s infinite}
.ia-typing span:nth-child(2){animation-delay:.15s}.ia-typing span:nth-child(3){animation-delay:.3s}
@keyframes ia-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}
.ia-result-table{background:white;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;margin-top:6px;font-size:12px}
.ia-result-table .table{margin:0}
.ia-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px}
.ia-stat-card{border-radius:8px;padding:8px 10px;text-align:center}
.ia-stat-primary{background:#e0e7ff}.ia-stat-success{background:#dcfce7}.ia-stat-danger{background:#fee2e2}.ia-stat-info{background:#e0f2fe}
.ia-stat-num{font-size:20px;font-weight:700;color:#1e293b}.ia-stat-label{font-size:10px;color:#64748b}
.ia-result-summary{background:#f8fafc;border-radius:8px;padding:8px 12px;margin-top:6px;border:1px solid #e2e8f0}
.ia-summary-row{display:flex;justify-content:space-between;font-size:12px;padding:2px 0;color:#475569}
.ia-input-area{border-top:1px solid #e2e8f0;padding:10px 12px;flex-shrink:0;background:#fafafa}
.ia-input-row{display:flex;gap:8px;align-items:flex-end}
.ia-input{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;resize:none;max-height:80px;background:white;color:#1e293b;line-height:1.4}
.ia-input:focus{outline:none;border-color:#6366f1}
.ia-send-btn{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ia-send-btn:disabled{opacity:.4;cursor:not-allowed}
.ia-input-hint{font-size:10px;color:#94a3b8;margin-top:4px}
.ia-slide-enter-active,.ia-slide-leave-active{transition:all .25s cubic-bezier(.4,0,.2,1)}
.ia-slide-enter-from,.ia-slide-leave-to{opacity:0;transform:translateY(20px) scale(.95)}
code{background:#1e293b;color:#7dd3fc;padding:1px 5px;border-radius:4px;font-size:11px}
</style>
<script>
import { ref, nextTick } from 'vue';
import axios from 'axios';
export default {
    name: 'IaChatFloat',
    props: { url: String },
    setup(props) {
        const isOpen=ref(false),isExpanded=ref(false),isTyping=ref(false),inputText=ref(''),messages=ref([]);
        const messagesContainer=ref(null),inputRef=ref(null);
        const suggestions=[
            {text:'dame estadísticas de clientes',label:'Estadísticas',icon:'fas fa-chart-bar'},
            {text:'lista los pagos de hoy',label:'Pagos de hoy',icon:'fas fa-dollar-sign'},
            {text:'muestra las tareas pendientes',label:'Tareas',icon:'fas fa-tasks'},
            {text:'clientes morosos',label:'Morosos',icon:'fas fa-user-slash'},
            {text:'reporte de ingresos del mes',label:'Ingresos',icon:'fas fa-chart-line'},
        ];
        const scroll=()=>nextTick(()=>{const e=messagesContainer.value;if(e)e.scrollTop=e.scrollHeight;});
        const toggleChat=()=>{isOpen.value=!isOpen.value;if(isOpen.value)nextTick(()=>inputRef.value?.focus());};
        const sendSuggestion=(t)=>{inputText.value=t;send();};
        const fmt=(t)=>t?t.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/`(.*?)`/g,'<code>$1</code>').replace(/\n/g,'<br>'):'' ;
        const ft=(d)=>d?new Date(d).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}):'';
        const fm=(n)=>parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2});
        const sc=(s)=>({ToDo:'secondary',InProgress:'warning',Done:'success',PostponedByClient:'info'}[s]||'secondary');
        const send=async()=>{
            const text=inputText.value.trim();
            if(!text||isTyping.value)return;
            messages.value.push({role:'user',content:text,timestamp:new Date()});
            inputText.value='';isTyping.value=true;scroll();
            const history=messages.value.slice(-10).map(m=>({role:m.role,content:m.content}));
            try{
                const res=await axios.post(`${props.url}/ia/chat`,{message:text,history:history.slice(0,-1),context:window.location.pathname});
                if(res.data.success){
                    messages.value.push({role:'assistant',content:res.data.message,action_type:res.data.action_type,action_data:res.data.action_result,timestamp:new Date()});
                }else{
                    messages.value.push({role:'assistant',content:'❌ '+(res.data.error||'Error'),timestamp:new Date()});
                }
            }catch(e){
                messages.value.push({role:'assistant',content:'❌ Error de conexión. Verifica CLAUDE_API_KEY en .env',timestamp:new Date()});
            }finally{isTyping.value=false;scroll();nextTick(()=>inputRef.value?.focus());}
        };
        return {isOpen,isExpanded,isTyping,inputText,messages,messagesContainer,inputRef,suggestions,toggleChat,sendSuggestion,send,fmt,ft,fm,sc};
    }
};
</script>
<style scoped>
.ia-float-btn{position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 20px rgba(99,102,241,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s}
.ia-float-btn:hover{transform:scale(1.1)}
.ia-float-btn.has-pulse::after{content:'';position:absolute;width:100%;height:100%;border-radius:50%;background:rgba(99,102,241,.3);animation:ia-pulse 2s infinite}
@keyframes ia-pulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.ia-btn-inner{color:white;font-size:22px;z-index:1}.ia-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:9px;font-weight:700;border-radius:10px;padding:2px 5px;z-index:2}
.ia-chat-panel{position:fixed;bottom:90px;right:24px;z-index:9998;width:380px;height:560px;background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(99,102,241,.15)}
.ia-chat-panel.ia-expanded{width:680px;height:80vh}
.ia-header{background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ia-header-left{display:flex;align-items:center;gap:10px}
.ia-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:white;font-size:18px}
.ia-title{color:white;font-weight:600;font-size:14px}.ia-subtitle{color:rgba(255,255,255,.8);font-size:11px;display:flex;align-items:center;gap:4px}
.ia-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.ia-header-actions{display:flex;gap:4px}
.ia-btn-icon{background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
.ia-btn-icon:hover{background:rgba(255,255,255,.3)}
.ia-suggestions{padding:16px;flex-shrink:0}
.ia-welcome{text-align:center;padding:8px 0 12px}
.ia-welcome-icon{font-size:32px;color:#6366f1;margin-bottom:8px;display:block}
.ia-welcome p{color:#64748b;font-size:13px;margin:0}
.ia-chips{display:flex;flex-wrap:wrap;gap:6px}
.ia-chip{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:5px 12px;font-size:12px;cursor:pointer;color:#475569;transition:all .2s;display:flex;align-items:center;gap:5px}
.ia-chip:hover{background:#e0e7ff;border-color:#6366f1;color:#6366f1}
.ia-messages{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:12px}
.ia-messages::-webkit-scrollbar{width:4px}.ia-messages::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}
.ia-msg{display:flex;gap:8px;align-items:flex-start}.ia-msg-user{flex-direction:row-reverse}
.ia-msg-avatar{width:30px;height:30px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-size:13px}
.ia-msg-body{max-width:80%;display:flex;flex-direction:column;gap:4px}.ia-msg-user .ia-msg-body{align-items:flex-end}
.ia-msg-text{padding:10px 13px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word}
.ia-msg-ai .ia-msg-text{background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;border-radius:4px 14px 14px 14px}
.ia-msg-user .ia-msg-text{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:14px 4px 14px 14px}
.ia-msg-time{font-size:10px;color:#94a3b8;padding:0 4px}
.ia-typing{display:flex;gap:4px;padding:8px 12px}
.ia-typing span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ia-bounce .8s infinite}
.ia-typing span:nth-child(2){animation-delay:.15s}.ia-typing span:nth-child(3){animation-delay:.3s}
@keyframes ia-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}
.ia-result-table{background:white;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;margin-top:6px;font-size:12px}
.ia-result-table .table{margin:0}
.ia-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px}
.ia-stat-card{border-radius:8px;padding:8px 10px;text-align:center}
.ia-stat-primary{background:#e0e7ff}.ia-stat-success{background:#dcfce7}.ia-stat-danger{background:#fee2e2}.ia-stat-info{background:#e0f2fe}
.ia-stat-num{font-size:20px;font-weight:700;color:#1e293b}.ia-stat-label{font-size:10px;color:#64748b}
.ia-result-summary{background:#f8fafc;border-radius:8px;padding:8px 12px;margin-top:6px;border:1px solid #e2e8f0}
.ia-summary-row{display:flex;justify-content:space-between;font-size:12px;padding:2px 0;color:#475569}
.ia-input-area{border-top:1px solid #e2e8f0;padding:10px 12px;flex-shrink:0;background:#fafafa}
.ia-input-row{display:flex;gap:8px;align-items:flex-end}
.ia-input{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;resize:none;max-height:80px;background:white;color:#1e293b;line-height:1.4}
.ia-input:focus{outline:none;border-color:#6366f1}
.ia-send-btn{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ia-send-btn:disabled{opacity:.4;cursor:not-allowed}
.ia-input-hint{font-size:10px;color:#94a3b8;margin-top:4px}
.ia-slide-enter-active,.ia-slide-leave-active{transition:all .25s cubic-bezier(.4,0,.2,1)}
.ia-slide-enter-from,.ia-slide-leave-to{opacity:0;transform:translateY(20px) scale(.95)}
code{background:#1e293b;color:#7dd3fc;padding:1px 5px;border-radius:4px;font-size:11px}
</style>
