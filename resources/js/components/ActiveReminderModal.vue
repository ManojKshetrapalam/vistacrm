<template>
  <div
    v-if="alert"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md animate-fade-in"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="active-alert-title"
  >
    <div
      class="glass-elevated max-w-lg w-full overflow-hidden border-2 shadow-2xl animate-scale-up"
      :class="isFollowUpReminder ? 'border-vb-magenta/50' : 'border-vb-teal/50'"
    >
      <!-- Top Pulsing Spectrum Bar -->
      <div class="spectrum h-2 w-full animate-pulse"></div>

      <div class="p-6 space-y-4">
        <!-- Header Badge & Category -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
              <span
                class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                :class="isFollowUpReminder ? 'bg-rose-400' : 'bg-emerald-400'"
              ></span>
              <span
                class="relative inline-flex rounded-full h-3 w-3"
                :class="isFollowUpReminder ? 'bg-rose-500' : 'bg-emerald-500'"
              ></span>
            </span>
            <span class="text-xs font-bold uppercase tracking-widest font-display text-vb-magenta">
              {{ alertBadgeCategory }}
            </span>
          </div>

          <span
            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border backdrop-blur-sm"
            :class="isFollowUpReminder ? 'bg-vb-orange/20 text-amber-300 border-vb-orange/40' : 'bg-vb-teal/20 text-teal-300 border-vb-teal/40'"
          >
            {{ isFollowUpReminder ? '5-Minute Reminder' : 'Live Intake' }}
          </span>
        </div>

        <!-- Main Title & Time -->
        <div>
          <h2 id="active-alert-title" class="font-display font-bold text-xl text-white">
            {{ alertMainTitle }}
          </h2>
          <div class="flex items-center gap-2 text-xs font-semibold mt-1" :class="isFollowUpReminder ? 'text-vb-magenta' : 'text-vb-cyan'">
            <Clock class="w-4 h-4" />
            <span>{{ alertTimeLabel }}</span>
          </div>
        </div>

        <!-- Lead & Follow-up Details Card -->
        <div class="p-4.5 rounded-2xl bg-white/[0.05] border border-white/10 backdrop-blur-sm space-y-2 text-xs shadow-sm">
          <div class="flex justify-between">
            <span class="text-purple-200/70 font-medium">Reference ID:</span>
            <span class="font-mono font-bold text-vb-magenta">{{ leadReferenceId }}</span>
          </div>

          <div v-if="leadCompanyName" class="flex justify-between">
            <span class="text-purple-200/70 font-medium">Company:</span>
            <span class="font-semibold text-white">{{ leadCompanyName }}</span>
          </div>

          <div v-if="leadServiceInterest" class="flex justify-between">
            <span class="text-purple-200/70 font-medium">Service Interest:</span>
            <span class="font-semibold text-white">{{ leadServiceInterest }}</span>
          </div>

          <div v-if="alertMessage" class="pt-2 border-t border-white/10">
            <span class="text-purple-200/70 block text-[11px] font-semibold">Action Objective:</span>
            <p class="text-xs text-white/90 italic mt-0.5 whitespace-pre-line leading-relaxed">
              "{{ alertMessage }}"
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2 pt-2">
          <button
            @click="openLead"
            class="btn-primary w-full py-2.5 rounded-full text-xs font-semibold flex items-center justify-center gap-2 shadow-sm"
          >
            <span>{{ isFollowUpReminder ? 'Open Lead & Log Call' : 'Open Lead Profile' }}</span>
            <ArrowRight class="w-4 h-4" />
          </button>

          <div v-if="leadPhone" class="grid grid-cols-2 gap-2">
            <a
              :href="'tel:' + leadPhone"
              class="p-2.5 rounded-full border border-white/15 bg-white/10 hover:bg-white/15 text-white text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-sm"
            >
              <PhoneCall class="w-3.5 h-3.5 text-vb-cyan" />
              <span>Call Client</span>
            </a>

            <a
              :href="whatsAppUrl"
              target="_blank"
              rel="noopener"
              class="p-2.5 rounded-full border border-emerald-500/40 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-sm"
            >
              <MessageSquare class="w-3.5 h-3.5 text-emerald-400" />
              <span>WhatsApp</span>
            </a>
          </div>

          <button
            @click="dismiss"
            class="w-full py-2 text-xs font-semibold text-purple-200/70 hover:text-white transition-colors rounded-full hover:bg-white/10"
          >
            Acknowledge &amp; Dismiss
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '../stores/notification';
import {
  Clock,
  ArrowRight,
  PhoneCall,
  MessageSquare,
} from 'lucide-vue-next';

const router = useRouter();
const notificationStore = useNotificationStore();

const alert = computed(() => notificationStore.currentTopAlert);

const isFollowUpReminder = computed(() => {
  return alert.value?.event_type === 'follow_up_reminder' || !!alert.value?.follow_up_id;
});

const alertBadgeCategory = computed(() => {
  if (isFollowUpReminder.value) return 'Follow-up Due Reminder';
  if (alert.value?.event_type === 'unassigned_lead') return 'Unassigned Lead Alert';
  return 'New Lead Assignment';
});

const alertMainTitle = computed(() => {
  if (!alert.value) return 'Notification';
  if (isFollowUpReminder.value) {
    return alert.value.follow_up?.lead?.name || alert.value.lead?.name || alert.value.title;
  }
  return alert.value.lead?.name || alert.value.title;
});

const alertTimeLabel = computed(() => {
  if (!alert.value) return '';
  if (isFollowUpReminder.value) {
    const meta = alert.value.metadata;
    if (meta?.due_time_ist) return `Scheduled for: ${meta.due_time_ist}`;
    if (alert.value.follow_up?.scheduled_at_ist) return `Scheduled for: ${alert.value.follow_up.scheduled_at_ist}`;
    return 'Follow-up Due Now';
  }
  return `Received: ${alert.value.created_at_ist || 'Just now'}`;
});

const leadReferenceId = computed(() => {
  return alert.value?.lead?.reference_id ||
         alert.value?.follow_up?.lead?.reference_id ||
         alert.value?.metadata?.lead_reference_id ||
         'N/A';
});

const leadCompanyName = computed(() => {
  return alert.value?.lead?.company_name ||
         alert.value?.follow_up?.lead?.company_name ||
         alert.value?.metadata?.company_name ||
         '';
});

const leadServiceInterest = computed(() => {
  return alert.value?.lead?.service_interest ||
         alert.value?.follow_up?.lead?.service_interest ||
         alert.value?.metadata?.service_interest ||
         '';
});

const alertMessage = computed(() => {
  if (isFollowUpReminder.value && alert.value?.follow_up?.notes) {
    return alert.value.follow_up.notes;
  }
  return alert.value?.message || '';
});

const leadPhone = computed(() => {
  return alert.value?.lead?.phone ||
         alert.value?.follow_up?.lead?.phone ||
         alert.value?.metadata?.phone ||
         '';
});

const whatsAppUrl = computed(() => {
  if (!leadPhone.value) return '#';
  const cleanPhone = leadPhone.value.replace(/[^0-9]/g, '');
  return `https://wa.me/${cleanPhone}`;
});

const dismiss = async () => {
  if (!alert.value) return;
  await notificationStore.dismissAlert(alert.value.id);
};

const openLead = async () => {
  if (!alert.value) return;
  const leadId = alert.value.lead_id || alert.value.follow_up?.lead_id || alert.value.metadata?.lead_id;
  await dismiss();
  if (leadId) {
    router.push(`/leads/${leadId}`);
  } else {
    router.push('/leads');
  }
};
</script>
