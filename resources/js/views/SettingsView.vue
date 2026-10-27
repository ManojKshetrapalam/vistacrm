<template>
  <AppLayout>
    <div class="space-y-6 max-w-5xl mx-auto">
      <!-- Header -->
      <div>
        <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">System Preferences</span>
        <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">CRM Settings &amp; WhatsApp Templates</h2>
        <p class="text-xs text-vb-muted mt-0.5">
          Configure active lead channels, service interest verticals, and client outreach message templates.
        </p>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Lead Sources Management -->
        <div class="card-glass p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-vb-plum/10 pb-3">
            <div>
              <h3 class="font-display font-bold text-sm text-vb-plum">Active Lead Sources</h3>
              <p class="text-[11px] text-vb-muted">Allowed origin channels for intake dropdowns</p>
            </div>
          </div>

          <!-- Add Source Input -->
          <div class="flex gap-2">
            <input
              v-model="newSource"
              placeholder="e.g. Instagram Ads, Trade Fair"
              class="field text-xs"
              @keydown.enter.prevent="addSource"
            />
            <button
              @click="addSource"
              class="btn-primary px-4 py-2 text-xs rounded-xl font-semibold shrink-0"
            >
              Add
            </button>
          </div>

          <!-- List of Sources -->
          <div class="space-y-1.5 max-h-56 overflow-y-auto">
            <div
              v-for="(src, idx) in leadSources"
              :key="idx"
              class="p-2.5 rounded-xl bg-white border border-vb-plum/10 flex items-center justify-between text-xs font-semibold text-vb-ink"
            >
              <span>{{ src }}</span>
              <button
                @click="removeSource(idx)"
                class="text-vb-muted hover:text-rose-600 p-1 rounded"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <button
            @click="saveSettings"
            :disabled="saving"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full w-full"
          >
            {{ saving ? 'Saving...' : 'Save Lead Sources' }}
          </button>
        </div>

        <!-- Service Categories Management -->
        <div class="card-glass p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-vb-plum/10 pb-3">
            <div>
              <h3 class="font-display font-bold text-sm text-vb-plum">Service &amp; Capability Categories</h3>
              <p class="text-[11px] text-vb-muted">Verticals and services offered to clients</p>
            </div>
          </div>

          <!-- Add Service Input -->
          <div class="flex gap-2">
            <input
              v-model="newCategory"
              placeholder="e.g. AI & Automation Services"
              class="field text-xs"
              @keydown.enter.prevent="addCategory"
            />
            <button
              @click="addCategory"
              class="btn-primary px-4 py-2 text-xs rounded-xl font-semibold shrink-0"
            >
              Add
            </button>
          </div>

          <!-- List of Categories -->
          <div class="space-y-1.5 max-h-56 overflow-y-auto">
            <div
              v-for="(cat, idx) in serviceCategories"
              :key="idx"
              class="p-2.5 rounded-xl bg-white border border-vb-plum/10 flex items-center justify-between text-xs font-semibold text-vb-ink"
            >
              <span>{{ cat }}</span>
              <button
                @click="removeCategory(idx)"
                class="text-vb-muted hover:text-rose-600 p-1 rounded"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <button
            @click="saveSettings"
            :disabled="saving"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full w-full"
          >
            {{ saving ? 'Saving...' : 'Save Service Categories' }}
          </button>
        </div>
      </div>

      <!-- WhatsApp Outreach Templates -->
      <div class="card-glass p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-vb-plum/10 pb-4">
          <div>
            <h3 class="font-display font-bold text-lg text-vb-plum">WhatsApp Direct Connect Templates</h3>
            <p class="text-xs text-vb-muted">
              Predefined templates with dynamic tags: <code class="bg-vb-cream px-1 py-0.5 rounded text-vb-violet">{lead_name}</code>, <code class="bg-vb-cream px-1 py-0.5 rounded text-vb-violet">{service_interest}</code>, <code class="bg-vb-cream px-1 py-0.5 rounded text-vb-violet">{employee_name}</code>, <code class="bg-vb-cream px-1 py-0.5 rounded text-vb-violet">{company_name}</code>.
            </p>
          </div>

          <button
            @click="openAddTemplateModal"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-2 self-start"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Create Template</span>
          </button>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div
            v-for="tmpl in templates"
            :key="tmpl.id"
            class="p-5 rounded-2xl bg-white border border-vb-plum/10 shadow-sm space-y-2.5 flex flex-col justify-between"
          >
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="font-bold text-sm text-vb-plum font-display">{{ tmpl.title }}</span>
                <span class="text-[10px] text-vb-muted font-mono bg-vb-cream px-2 py-0.5 rounded">{{ tmpl.template_key }}</span>
              </div>
              <p class="text-xs text-vb-ink leading-relaxed whitespace-pre-line bg-vb-cream/40 p-3 rounded-xl border border-vb-plum/5 font-sans">
                {{ tmpl.content }}
              </p>
            </div>

            <div class="pt-2 border-t border-vb-plum/5 flex items-center justify-end gap-2 text-xs">
              <button
                @click="openEditTemplateModal(tmpl)"
                class="px-3 py-1 rounded-lg border border-vb-plum/20 text-vb-plum font-semibold hover:bg-vb-cream text-[11px]"
              >
                Edit
              </button>
              <button
                @click="deleteTemplate(tmpl)"
                class="px-2.5 py-1 text-rose-600 hover:bg-rose-50 rounded-lg text-[11px] font-semibold"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- System Diagnostics & Reminder Monitor -->
      <div class="card-glass p-6 space-y-6">
        <div class="flex items-center justify-between border-b border-vb-plum/10 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">System Health</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                Scheduler: 1 Min
              </span>
            </div>
            <h3 class="font-display font-bold text-base text-vb-plum mt-0.5">Follow-up Reminders &amp; Integration Diagnostics</h3>
            <p class="text-xs text-vb-muted">
              Real-time monitoring of follow-up reminder timers, WhatsApp delivery channels, queue backlogs, and scheduler state.
            </p>
          </div>

          <button
            @click="fetchDiagnostics"
            :disabled="diagnosticsLoading"
            class="px-3.5 py-2 rounded-xl border border-vb-plum/20 bg-white hover:bg-vb-cream text-xs font-semibold text-vb-plum flex items-center gap-1.5 transition-colors"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': diagnosticsLoading }" />
            <span>{{ diagnosticsLoading ? 'Refreshing...' : 'Refresh Status' }}</span>
          </button>
        </div>

        <div v-if="diagnostics" class="space-y-6">
          <!-- Timestamps & Timezones Card -->
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-2xl bg-white border border-vb-plum/10 space-y-1">
              <span class="text-[11px] font-semibold text-vb-muted uppercase tracking-wider">Local Business Time (IST)</span>
              <p class="text-sm font-bold text-vb-plum font-mono">{{ diagnostics.server_time?.ist }}</p>
              <p class="text-[10px] text-vb-muted">Default application timezone for follow-up schedules</p>
            </div>
            <div class="p-4 rounded-2xl bg-white border border-vb-plum/10 space-y-1">
              <span class="text-[11px] font-semibold text-vb-muted uppercase tracking-wider">Database Storage Time (UTC)</span>
              <p class="text-sm font-bold text-vb-ink font-mono">{{ diagnostics.server_time?.utc }}</p>
              <p class="text-[10px] text-vb-muted">Standard normalized UTC timestamp storage</p>
            </div>
          </div>

          <!-- Metrics Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-xl bg-white border border-vb-plum/10 text-center">
              <span class="text-[10px] uppercase font-bold text-vb-muted tracking-wider block">Pending Tasks</span>
              <span class="text-xl font-bold font-display text-vb-plum">{{ diagnostics.follow_ups?.pending }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-white border border-vb-plum/10 text-center">
              <span class="text-[10px] uppercase font-bold text-vb-muted tracking-wider block">Overdue Tasks</span>
              <span class="text-xl font-bold font-display text-rose-600">{{ diagnostics.follow_ups?.overdue }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-white border border-vb-plum/10 text-center">
              <span class="text-[10px] uppercase font-bold text-vb-muted tracking-wider block">Reminders Due</span>
              <span class="text-xl font-bold font-display text-vb-amber">{{ diagnostics.follow_ups?.pending_reminders_due }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-white border border-vb-plum/10 text-center">
              <span class="text-[10px] uppercase font-bold text-vb-muted tracking-wider block">Queue Backlog</span>
              <span class="text-xl font-bold font-display text-vb-teal">{{ diagnostics.queue?.pending_jobs }}</span>
            </div>
          </div>

          <!-- WhatsApp Channel Status Card -->
          <div class="p-4 rounded-2xl bg-white border border-vb-plum/10 space-y-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="font-bold text-xs text-vb-plum font-display">WhatsApp Notification Channel:</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="diagnostics.whatsapp?.configured ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ diagnostics.whatsapp?.status }}
                </span>
              </div>
              <span class="text-xs font-mono text-vb-muted">Driver: {{ diagnostics.whatsapp?.driver }}</span>
            </div>
            <p class="text-xs text-vb-muted leading-relaxed">
              {{ diagnostics.whatsapp?.advisory }}
            </p>
          </div>

          <!-- Recent Logs Table -->
          <div class="space-y-2">
            <span class="text-xs font-bold text-vb-plum uppercase tracking-wider block">Recent Reminder Delivery Logs</span>
            <div class="overflow-x-auto rounded-xl border border-vb-plum/10 bg-white">
              <table class="w-full text-left text-xs">
                <thead class="bg-vb-cream/50 border-b border-vb-plum/10 text-[11px] font-bold text-vb-muted uppercase tracking-wider">
                  <tr>
                    <th class="p-2.5">Time (IST)</th>
                    <th class="p-2.5">Follow-up</th>
                    <th class="p-2.5">Recipient</th>
                    <th class="p-2.5">Channel</th>
                    <th class="p-2.5">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-vb-plum/5">
                  <tr v-if="!diagnostics.recent_logs?.length">
                    <td colspan="5" class="p-4 text-center text-vb-muted">No recent reminder logs.</td>
                  </tr>
                  <tr v-for="log in diagnostics.recent_logs" :key="log.id" class="hover:bg-vb-cream/20">
                    <td class="p-2.5 font-mono text-vb-muted">{{ log.created_at_ist }}</td>
                    <td class="p-2.5 font-medium text-vb-plum">FU #{{ log.follow_up_id || 'N/A' }}</td>
                    <td class="p-2.5 font-semibold text-vb-ink">{{ log.user_name }}</td>
                    <td class="p-2.5 font-mono text-[11px] text-vb-violet">{{ log.channel }}</td>
                    <td class="p-2.5">
                      <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="log.status === 'delivered' || log.status === 'sent' ? 'bg-emerald-100 text-emerald-800' : (log.status === 'simulated' ? 'bg-sky-100 text-sky-800' : 'bg-rose-100 text-rose-800')"
                      >
                        {{ log.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Template Modal (Add / Edit) -->
    <div v-if="templateModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">
          {{ editingTemplateId ? 'Edit WhatsApp Template' : 'New WhatsApp Template' }}
        </h3>

        <form @submit.prevent="handleSaveTemplate" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Template Title</label>
            <input v-model="templateForm.title" type="text" required placeholder="e.g. Consultation Reminder" class="field text-sm" />
          </div>

          <div v-if="!editingTemplateId">
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Unique Key</label>
            <input v-model="templateForm.template_key" type="text" required placeholder="e.g. consultation_reminder" class="field text-sm font-mono" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum">Message Content</label>
              <span class="text-[10px] text-vb-muted">Use {lead_name}, {service_interest}, etc.</span>
            </div>
            <textarea
              v-model="templateForm.content"
              rows="5"
              required
              class="field text-sm font-sans resize-none"
              placeholder="Hello {lead_name}, greetings from Vistabound Integrated Solutions..."
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-vb-plum/10">
            <button type="button" @click="templateModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
              Cancel
            </button>
            <button type="submit" :disabled="templateSaving" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold">
              {{ templateSaving ? 'Saving...' : 'Save Template' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import { Trash2, Plus, RefreshCw } from 'lucide-vue-next';

const leadSources = ref<string[]>([]);
const serviceCategories = ref<string[]>([]);
const templates = ref<any[]>([]);

const newSource = ref('');
const newCategory = ref('');
const saving = ref(false);

const templateModalOpen = ref(false);
const editingTemplateId = ref<number | null>(null);
const templateSaving = ref(false);
const templateForm = reactive({
  title: '',
  template_key: '',
  content: '',
});

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    leadSources.value = res.data.lead_sources || [];
    serviceCategories.value = res.data.service_categories || [];
    templates.value = res.data.templates || [];
  } catch (err) {
    console.error('Failed to load settings', err);
  }
};

const addSource = () => {
  if (!newSource.value.trim()) return;
  leadSources.value.push(newSource.value.trim());
  newSource.value = '';
};

const removeSource = (idx: number) => {
  leadSources.value.splice(idx, 1);
};

const addCategory = () => {
  if (!newCategory.value.trim()) return;
  serviceCategories.value.push(newCategory.value.trim());
  newCategory.value = '';
};

const removeCategory = (idx: number) => {
  serviceCategories.value.splice(idx, 1);
};

const saveSettings = async () => {
  saving.value = true;
  try {
    await api.post('/settings', {
      lead_sources: leadSources.value,
      service_categories: serviceCategories.value,
    });
    alert('Settings updated successfully!');
  } catch (err) {
    alert('Failed to save settings.');
  } finally {
    saving.value = false;
  }
};

const openAddTemplateModal = () => {
  editingTemplateId.value = null;
  templateForm.title = '';
  templateForm.template_key = '';
  templateForm.content = '';
  templateModalOpen.value = true;
};

const openEditTemplateModal = (tmpl: any) => {
  editingTemplateId.value = tmpl.id;
  templateForm.title = tmpl.title;
  templateForm.template_key = tmpl.template_key;
  templateForm.content = tmpl.content;
  templateModalOpen.value = true;
};

const handleSaveTemplate = async () => {
  templateSaving.value = true;
  try {
    if (editingTemplateId.value) {
      await api.put(`/settings/templates/${editingTemplateId.value}`, {
        title: templateForm.title,
        content: templateForm.content,
      });
    } else {
      await api.post('/settings/templates', templateForm);
    }
    templateModalOpen.value = false;
    await fetchSettings();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to save template.');
  } finally {
    templateSaving.value = false;
  }
};

const deleteTemplate = async (tmpl: any) => {
  if (!confirm(`Delete template "${tmpl.title}"?`)) return;
  try {
    await api.delete(`/settings/templates/${tmpl.id}`);
    await fetchSettings();
  } catch (err) {
    alert('Failed to delete template.');
  }
};

const diagnostics = ref<any>(null);
const diagnosticsLoading = ref(false);

const fetchDiagnostics = async () => {
  diagnosticsLoading.value = true;
  try {
    const res = await api.get('/settings/diagnostics');
    diagnostics.value = res.data;
  } catch (err) {
    console.error('Failed to fetch diagnostics:', err);
  } finally {
    diagnosticsLoading.value = false;
  }
};

onMounted(() => {
  fetchSettings();
  fetchDiagnostics();
});
</script>
