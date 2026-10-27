<template>
  <div
    v-if="activeReminder"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-vb-plum/70 backdrop-blur-sm animate-fade-in"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="reminder-alert-title"
  >
    <div
      class="bg-white rounded-3xl border-2 border-vb-violet/40 shadow-2xl max-w-lg w-full overflow-hidden animate-scale-up"
    >
      <!-- Top Pulsing Spectrum Bar -->
      <div class="spectrum h-2 w-full animate-pulse"></div>

      <div class="p-6 space-y-4">
        <!-- Header Badge -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
            </span>
            <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">
              Follow-up Due Reminder
            </span>
          </div>

          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-vb-amber/15 text-vb-plum">
            5-Minute Notice
          </span>
        </div>

        <!-- Main Title & Time -->
        <div>
          <h2 id="reminder-alert-title" class="font-display font-bold text-xl text-vb-plum">
            {{ activeReminder.follow_up?.lead?.name || 'Lead Follow-up Due' }}
          </h2>
          <div class="flex items-center gap-2 text-xs font-semibold text-vb-violet mt-1">
            <Clock class="w-4 h-4" />
            <span>Scheduled for: {{ reminderDueTime }}</span>
          </div>
        </div>

        <!-- Detail Card -->
        <div class="p-4 rounded-2xl bg-vb-cream/40 border border-vb-plum/10 space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-vb-muted">Reference ID:</span>
            <span class="font-mono font-bold text-vb-plum">{{ activeReminder.follow_up?.lead?.reference_id }}</span>
          </div>

          <div v-if="activeReminder.follow_up?.lead?.company_name" class="flex justify-between">
            <span class="text-vb-muted">Company:</span>
            <span class="font-semibold text-vb-ink">{{ activeReminder.follow_up.lead.company_name }}</span>
          </div>

          <div v-if="activeReminder.follow_up?.lead?.service_interest" class="flex justify-between">
            <span class="text-vb-muted">Service Interest:</span>
            <span class="font-semibold text-vb-ink">{{ activeReminder.follow_up.lead.service_interest }}</span>
          </div>

          <div v-if="activeReminder.follow_up?.notes" class="pt-2 border-t border-vb-plum/10">
            <span class="text-vb-muted block text-[11px]">Appointment Notes:</span>
            <p class="text-xs text-vb-ink italic mt-0.5">
              "{{ activeReminder.follow_up.notes }}"
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2 pt-2">
          <button
            @click="openLead(activeReminder.follow_up?.lead_id)"
            class="btn-primary w-full py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-2"
          >
            <span>Open Lead Details &amp; Log Call</span>
            <ArrowRight class="w-4 h-4" />
          </button>

          <div class="grid grid-cols-2 gap-2">
            <a
              v-if="leadPhone"
              :href="'tel:' + leadPhone"
              class="p-2.5 rounded-xl border border-vb-plum/20 bg-white hover:bg-vb-cream text-vb-plum text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
            >
              <PhoneCall class="w-3.5 h-3.5 text-vb-teal" />
              <span>Call Client</span>
            </a>

            <a
              v-if="leadPhone"
              :href="whatsAppUrl"
              target="_blank"
              rel="noopener"
              class="p-2.5 rounded-xl border border-vb-plum/20 bg-white hover:bg-vb-cream text-vb-plum text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
            >
              <MessageSquare class="w-3.5 h-3.5 text-emerald-600" />
              <span>WhatsApp</span>
            </a>
          </div>

          <button
            @click="acknowledge"
            class="w-full py-2 text-xs font-semibold text-vb-muted hover:text-vb-plum transition-colors"
          >
            Acknowledge &amp; Dismiss
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/axios';
import {
  Clock,
  ArrowRight,
  PhoneCall,
  MessageSquare,
} from 'lucide-vue-next';

const router = useRouter();

const activeReminders = ref<any[]>([]);
let pollTimer: any = null;

const activeReminder = computed(() => {
  return activeReminders.value.length > 0 ? activeReminders.value[0] : null;
});

const reminderDueTime = computed(() => {
  if (!activeReminder.value) return '';
  const meta = activeReminder.value.metadata;
  if (meta && meta.due_time_ist) {
    return meta.due_time_ist;
  }
  return activeReminder.value.follow_up?.scheduled_at_ist || 'Now';
});

const leadPhone = computed(() => {
  return activeReminder.value?.follow_up?.lead?.phone || '';
});

const whatsAppUrl = computed(() => {
  if (!leadPhone.value) return '#';
  const cleanPhone = leadPhone.value.replace(/[^0-9]/g, '');
  return `https://wa.me/${cleanPhone}`;
});

const fetchActiveReminders = async () => {
  try {
    const res = await api.get('/follow-ups/active-reminders');
    activeReminders.value = res.data.data || [];
  } catch (err) {
    // quiet
  }
};

const acknowledge = async () => {
  if (!activeReminder.value) return;
  const id = activeReminder.value.id;

  // Optimistically remove from queue
  activeReminders.value.shift();

  try {
    await api.post(`/follow-ups/reminders/${id}/acknowledge`);
  } catch (err) {
    console.error('Failed to acknowledge reminder:', err);
  }
};

const openLead = async (leadId?: number | string) => {
  await acknowledge();
  if (leadId) {
    router.push(`/leads/${leadId}`);
  }
};

onMounted(() => {
  fetchActiveReminders();
  // Poll every 25 seconds for new reminders
  pollTimer = setInterval(fetchActiveReminders, 25000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>
