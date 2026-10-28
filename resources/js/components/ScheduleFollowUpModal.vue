<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
      <!-- Header -->
      <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-vb-violet text-white flex items-center justify-center shadow-md">
            <CalendarClock class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-display font-bold text-base text-vb-plum">Schedule Follow-up</h3>
            <p class="text-xs text-vb-muted">{{ lead?.name }} · {{ lead?.reference_id }}</p>
          </div>
        </div>
        <button @click="close" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <!-- Date and Time -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Follow-up Date & Time <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.scheduled_at"
            type="datetime-local"
            required
            class="field text-sm"
          />
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Action Item / Objective
          </label>
          <textarea
            v-model="form.notes"
            rows="3"
            class="field text-sm font-sans resize-none"
            placeholder="e.g. Call to finalize IT infrastructure AMC terms and review commercial scope..."
          ></textarea>
        </div>

        <!-- Error Msg -->
        <p v-if="error" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
          {{ error }}
        </p>

        <!-- Footer -->
        <div class="pt-3 border-t border-vb-plum/10 flex items-center justify-end gap-2.5">
          <button
            type="button"
            @click="close"
            class="px-4 py-2 text-xs font-semibold text-vb-plum hover:bg-vb-plum/5 rounded-full transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5"
          >
            <Check class="w-3.5 h-3.5" />
            <span>{{ submitting ? 'Scheduling...' : 'Set Follow-up' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import api from '../api/axios';
import { syncEngine, isOnline } from '../services/syncEngine';
import { localNotificationService } from '../services/localNotifications';
import { CalendarClock, X, Check } from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  lead: any;
}>();

const emit = defineEmits(['close', 'saved']);

const submitting = ref(false);
const error = ref('');

const form = reactive({
  scheduled_at: '',
  notes: '',
});

const close = () => {
  emit('close');
};

const handleSubmit = async () => {
  if (!props.lead?.id) return;
  submitting.value = true;
  error.value = '';

  try {
    if (isOnline.value) {
      const res = await api.post(`/leads/${props.lead.id}/follow-ups`, {
        scheduled_at: form.scheduled_at,
        notes: form.notes,
      });
      if (res.data?.follow_up) {
        await localNotificationService.scheduleReminder(res.data.follow_up);
      }
    } else {
      await syncEngine.enqueueMutation('create_followup', props.lead.id, {
        scheduled_at: form.scheduled_at,
        notes: form.notes,
      });
      // Schedule local alarm immediately offline
      await localNotificationService.scheduleReminder({
        id: Math.floor(Math.random() * 1000000),
        scheduled_at: form.scheduled_at,
        lead: props.lead,
        notes: form.notes,
      });
    }
    emit('saved');
    close();
  } catch (err: any) {
    try {
      await syncEngine.enqueueMutation('create_followup', props.lead.id, {
        scheduled_at: form.scheduled_at,
        notes: form.notes,
      });
      emit('saved');
      close();
    } catch {
      error.value = err.response?.data?.message || 'Failed to schedule follow-up.';
    }
  } finally {
    submitting.value = false;
  }
};

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      // Default to tomorrow 11:00 AM
      const d = new Date();
      d.setDate(d.getDate() + 1);
      d.setHours(11, 0, 0, 0);
      form.scheduled_at = d.toISOString().slice(0, 16);
      form.notes = '';
      error.value = '';
    }
  }
);
</script>
