<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
      <!-- Header -->
      <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-vb-plum text-white flex items-center justify-center shadow-md">
            <PhoneCall class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-display font-bold text-base text-vb-plum">Log Call Outcome</h3>
            <p class="text-xs text-vb-muted">{{ lead?.name }} · {{ lead?.phone }}</p>
          </div>
        </div>
        <button @click="close" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <!-- Call Outcome -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Call Outcome <span class="text-rose-500">*</span>
          </label>
          <select v-model="form.outcome" required class="field text-sm">
            <option value="Connected">Connected & Spoke with Client</option>
            <option value="Callback Requested">Callback Requested</option>
            <option value="Interested">Interested / Qualified</option>
            <option value="Meeting Scheduled">Meeting / Consultation Scheduled</option>
            <option value="No Answer">No Answer / Rang Out</option>
            <option value="Busy">Line Busy / Call Disconnected</option>
            <option value="Wrong Number">Wrong Number / Invalid Contact</option>
            <option value="Not Interested">Not Interested / Closed</option>
          </select>
        </div>

        <!-- Duration -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Call Duration (Minutes)
          </label>
          <input
            v-model.number="form.duration_minutes"
            type="number"
            min="0"
            max="180"
            class="field text-sm"
            placeholder="e.g. 5"
          />
        </div>

        <!-- Update Status -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Update Lead Status To
          </label>
          <select v-model="form.update_status" class="field text-sm">
            <option value="">Keep Current ({{ lead?.status }})</option>
            <option value="contacted">Contacted</option>
            <option value="follow_up">Follow-up</option>
            <option value="interested">Interested</option>
            <option value="converted">Converted</option>
            <option value="lost">Lost</option>
            <option value="not_reachable">Not Reachable</option>
          </select>
        </div>

        <!-- Schedule Next Follow-up -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Schedule Next Follow-up (Optional)
          </label>
          <input
            v-model="form.next_follow_up_date"
            type="datetime-local"
            class="field text-sm"
          />
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Discussion Summary / Notes
          </label>
          <textarea
            v-model="form.notes"
            rows="3"
            class="field text-sm font-sans resize-none"
            placeholder="Key discussion points, client objections, or specific interest details..."
          ></textarea>
        </div>

        <!-- Direct Dial Button -->
        <div class="pt-1">
          <a
            :href="'tel:' + (lead?.phone || '')"
            class="w-full py-2 px-3 border border-vb-plum/20 hover:border-vb-plum text-vb-plum rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-colors"
          >
            <Phone class="w-3.5 h-3.5" />
            <span>Launch Phone Dialler ({{ lead?.phone }})</span>
          </a>
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
            <span>{{ submitting ? 'Saving...' : 'Save Call Log' }}</span>
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
import { PhoneCall, Phone, X, Check } from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  lead: any;
}>();

const emit = defineEmits(['close', 'saved']);

const submitting = ref(false);
const error = ref('');

const form = reactive({
  outcome: 'Connected',
  duration_minutes: 3,
  update_status: '',
  next_follow_up_date: '',
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
      await api.post(`/leads/${props.lead.id}/call`, {
        outcome: form.outcome,
        duration_minutes: form.duration_minutes,
        update_status: form.update_status || null,
        next_follow_up_date: form.next_follow_up_date || null,
        notes: form.notes,
      });
    } else {
      await syncEngine.enqueueMutation('log_call', props.lead.id, {
        outcome: form.outcome,
        duration: form.duration_minutes,
        notes: form.notes,
      });
      if (form.update_status) {
        await syncEngine.enqueueMutation('update_status', props.lead.id, {
          status: form.update_status,
        });
      }
      if (form.next_follow_up_date) {
        await syncEngine.enqueueMutation('create_followup', props.lead.id, {
          scheduled_at: form.next_follow_up_date,
          notes: 'Follow-up from call: ' + form.outcome,
        });
      }
    }
    emit('saved');
    close();
  } catch (err: any) {
    try {
      await syncEngine.enqueueMutation('log_call', props.lead.id, {
        outcome: form.outcome,
        duration: form.duration_minutes,
        notes: form.notes,
      });
      emit('saved');
      close();
    } catch {
      error.value = err.response?.data?.message || 'Failed to save call log.';
    }
  } finally {
    submitting.value = false;
  }
};

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      form.outcome = 'Connected';
      form.duration_minutes = 3;
      form.update_status = '';
      form.next_follow_up_date = '';
      form.notes = '';
      error.value = '';
    }
  }
);
</script>
