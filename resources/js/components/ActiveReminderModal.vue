<template>
  <div
    v-if="alert"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-vb-plum/70 backdrop-blur-sm animate-fade-in"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="active-alert-title"
  >
    <div
      class="bg-white rounded-3xl border-2 shadow-2xl max-w-lg w-full overflow-hidden animate-scale-up"
      :class="isFollowUpReminder ? 'border-vb-violet/40' : 'border-vb-teal/40'"
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
            <span class="text-xs font-bold uppercase tracking-widest font-display text-vb-violet">
              {{ alertBadgeCategory }}
            </span>
          </div>

          <span
            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
            :class="isFollowUpReminder ? 'bg-vb-amber/15 text-vb-plum' : 'bg-vb-teal/15 text-vb-teal'"
          >
            {{ isFollowUpReminder ? '5-Minute Reminder' : 'Live Intake' }}
          </span>
        </div>

        <!-- Main Title & Time -->
        <div>
          <h2 id="active-alert-title" class="font-display font-bold text-xl text-vb-plum">
            {{ alertMainTitle }}
          </h2>
          <div class="flex items-center gap-2 text-xs font-semibold mt-1" :class="isFollowUpReminder ? 'text-vb-violet' : 'text-vb-teal'">
            <Clock class="w-4 h-4" />
            <span>{{ alertTimeLabel }}</span>
          </div>
        </div>

        <!-- Lead & Follow-up Details Card -->
        <div class="p-4 rounded-2xl bg-vb-cream/40 border border-vb-plum/10 space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-vb-muted">Reference ID:</span>
            <span class="font-mono font-bold text-vb-plum">{{ leadReferenceId }}</span>
          </div>

          <div v-if="leadCompanyName" class="flex justify-between">
            <span class="text-vb-muted">Company:</span>
            <span class="font-semibold text-vb-ink">{{ leadCompanyName }}</span>
          </div>

          <div v-if="leadServiceInterest" class="flex justify-between">
            <span class="text-vb-muted">Service Interest:</span>
            <span class="font-semibold text-vb-ink">{{ leadServiceInterest }}</span>
          </div>

          <div v-if="alertMessage" class="pt-2 border-t border-vb-plum/10">
            <span class="text-vb-muted block text-[11px] font-medium">Message:</span>
            <p class="text-xs text-vb-ink italic mt-0.5 whitespace-pre-line">
              "{{ alertMessage }}"
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2 pt-2">
          <button
            @click="openLead"
            class="btn-primary w-full py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-2"
          >
            <span>{{ isFollowUpReminder ? 'Open Lead & Log Call' : 'Open Lead Profile' }}</span>
            <ArrowRight class="w-4 h-4" />
          </button>

          <div v-if="leadPhone" class="grid grid-cols-2 gap-2">
            <a
              :href="'tel:' + leadPhone"
              class="p-2.5 rounded-xl border border-vb-plum/20 bg-white hover:bg-vb-cream text-vb-plum text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
            >
              <PhoneCall class="w-3.5 h-3.5 text-vb-teal" />
              <span>Call Client</span>
            </a>

            <a
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
            @click="dismiss"
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
