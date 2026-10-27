<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-md">
            <MessageSquare class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-display font-bold text-base text-vb-plum">WhatsApp Direct Connect</h3>
            <p class="text-xs text-vb-muted">Engage {{ lead.name }} ({{ lead.phone }})</p>
          </div>
        </div>
        <button @click="close" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-4">
        <!-- Template Selector -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Select Message Template
          </label>
          <select
            v-model="selectedTemplateId"
            @change="fetchPreview"
            class="field text-sm"
          >
            <option value="">Custom Message (Free Text)</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">
              {{ t.title }}
            </option>
          </select>
        </div>

        <!-- Message Editor / Preview -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum">
              Rendered Message
            </label>
            <span class="text-[11px] text-vb-muted">Auto-substitutes name, service, & representative</span>
          </div>
          <textarea
            v-model="messageContent"
            rows="5"
            class="field text-sm font-sans resize-none"
            placeholder="Type your WhatsApp message..."
          ></textarea>
        </div>

        <!-- Phone Verification Box -->
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between">
          <div class="flex items-center gap-2 text-xs text-emerald-800">
            <CheckCircle2 class="w-4 h-4 text-emerald-600" />
            <span>Target WhatsApp: <strong>+{{ whatsappPhone || lead.phone_normalized }}</strong></span>
          </div>
          <span class="text-[10px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full font-semibold">Verified</span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-5 border-t border-vb-plum/10 bg-vb-cream/30 flex flex-wrap items-center justify-end gap-2.5">
        <button
          type="button"
          @click="close"
          class="px-4 py-2 text-xs font-semibold text-vb-plum hover:bg-vb-plum/5 rounded-full transition-colors"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="openWhatsApp('web')"
          :disabled="loading || !messageContent"
          class="px-4 py-2 text-xs font-semibold text-vb-plum border border-vb-plum/20 hover:bg-vb-plum/5 rounded-full transition-colors flex items-center gap-1.5"
        >
          <ExternalLink class="w-3.5 h-3.5" />
          <span>WhatsApp Web</span>
        </button>
        <button
          type="button"
          @click="openWhatsApp('app')"
          :disabled="loading || !messageContent"
          class="px-5 py-2 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-full shadow-md transition-all flex items-center gap-1.5"
        >
          <Send class="w-3.5 h-3.5" />
          <span>Send via App / wa.me</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import api from '../api/axios';
import { MessageSquare, X, CheckCircle2, ExternalLink, Send } from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  lead: any;
}>();

const emit = defineEmits(['close', 'dispatched']);

const templates = ref<any[]>([]);
const selectedTemplateId = ref<string | number>('');
const messageContent = ref('');
const whatsappPhone = ref('');
const waMeUrl = ref('');
const webWhatsAppUrl = ref('');
const loading = ref(false);

const close = () => {
  emit('close');
};

const fetchTemplates = async () => {
  try {
    const res = await api.get('/whatsapp/templates');
    templates.value = res.data;
    if (templates.value.length > 0 && !selectedTemplateId.value) {
      selectedTemplateId.value = templates.value[0].id;
      await fetchPreview();
    }
  } catch (err) {
    // quiet
  }
};

const fetchPreview = async () => {
  if (!props.lead?.id) return;
  loading.value = true;
  try {
    const res = await api.post(`/leads/${props.lead.id}/whatsapp/preview`, {
      template_id: selectedTemplateId.value || null,
      custom_text: !selectedTemplateId.value ? messageContent.value : null,
    });
    messageContent.value = res.data.rendered_message;
    whatsappPhone.value = res.data.whatsapp_phone;
    waMeUrl.value = res.data.wa_me_url;
    webWhatsAppUrl.value = res.data.web_whatsapp_url;
  } catch (err) {
    // quiet
  } finally {
    loading.value = false;
  }
};

const openWhatsApp = async (type: 'web' | 'app') => {
  if (!props.lead?.id || !messageContent.value) return;

  // 1. Log activity in CRM backend
  try {
    await api.post(`/leads/${props.lead.id}/whatsapp/log`, {
      message: messageContent.value,
      template_key: selectedTemplateId.value ? 'template_' + selectedTemplateId.value : 'custom',
    });
  } catch (err) {
    console.error('Failed to log WhatsApp dispatch', err);
  }

  // 2. Open WhatsApp window
  const url = type === 'web' ? webWhatsAppUrl.value : waMeUrl.value;
  window.open(url, '_blank');

  emit('dispatched');
  close();
};

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      fetchTemplates();
      fetchPreview();
    }
  }
);
</script>
