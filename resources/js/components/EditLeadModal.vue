<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 animate-in fade-in duration-150"
    @click.self="close"
  >
    <div
      class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-vb-plum/10 animate-in zoom-in-95 duration-150 my-8"
      role="dialog"
      aria-modal="true"
    >
      <!-- Header -->
      <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-vb-plum text-white flex items-center justify-center shadow-md">
            <Edit class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-display font-bold text-base text-vb-plum">Edit Lead Profile</h3>
            <p class="text-xs text-vb-muted font-mono font-medium">{{ lead?.reference_id }} · {{ lead?.name }}</p>
          </div>
        </div>
        <button
          type="button"
          @click="close"
          class="p-1 rounded-lg text-vb-muted hover:text-vb-plum hover:bg-vb-plum/5 transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
        <div class="grid sm:grid-cols-2 gap-4">
          <!-- Name -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Contact Name <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="field text-xs"
              placeholder="e.g. Ramesh Hegde"
            />
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Mobile / Phone <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.phone"
              type="tel"
              required
              class="field text-xs"
              placeholder="e.g. +91 98450 12345"
            />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <!-- Email -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Email Address
            </label>
            <input
              v-model="form.email"
              type="email"
              class="field text-xs"
              placeholder="client@organization.com"
            />
          </div>

          <!-- Company -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Company Name
            </label>
            <input
              v-model="form.company_name"
              type="text"
              class="field text-xs"
              placeholder="e.g. Acme Tech Solutions"
            />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <!-- Status -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Pipeline Status
            </label>
            <select v-model="form.status" class="field text-xs">
              <option value="new">New</option>
              <option value="assigned">Assigned</option>
              <option value="contacted">Contacted</option>
              <option value="follow_up">Follow-up</option>
              <option value="interested">Interested</option>
              <option value="converted">Converted / Won</option>
              <option value="lost">Lost / Closed</option>
              <option value="not_reachable">Not Reachable</option>
            </select>
          </div>

          <!-- Priority -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Priority
            </label>
            <select v-model="form.priority" class="field text-xs">
              <option value="urgent">Urgent</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <!-- Source -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Lead Source
            </label>
            <input
              v-model="form.source"
              type="text"
              class="field text-xs"
              placeholder="e.g. Website, Referral, Direct"
            />
          </div>

          <!-- Service Interest -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Service Interest
            </label>
            <input
              v-model="form.service_interest"
              type="text"
              class="field text-xs"
              placeholder="e.g. IT AMC, Enterprise Software"
            />
          </div>
        </div>

        <!-- Additional Scope / Details -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Scope &amp; Background Notes
          </label>
          <textarea
            v-model="form.additional_details"
            rows="3"
            class="field text-xs font-sans resize-none"
            placeholder="Client requirements, commercial scope, or engagement notes..."
          ></textarea>
        </div>

        <!-- Error Msg -->
        <p v-if="error" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
          {{ error }}
        </p>

        <!-- Footer -->
        <div class="pt-4 border-t border-vb-plum/10 flex items-center justify-end gap-2.5">
          <button
            type="button"
            @click="close"
            :disabled="submitting"
            class="px-4 py-2 text-xs font-semibold text-vb-plum hover:bg-vb-plum/5 rounded-full transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5 shadow-sm disabled:opacity-50"
          >
            <span v-if="submitting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ submitting ? 'Saving Changes...' : 'Save Lead Changes' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import api from '../api/axios';
import { Edit, X } from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  lead: any;
}>();

const emit = defineEmits(['close', 'saved']);

const submitting = ref(false);
const error = ref('');

const form = reactive({
  name: '',
  phone: '',
  email: '',
  company_name: '',
  status: 'new',
  priority: 'medium',
  source: '',
  service_interest: '',
  additional_details: '',
});

watch(
  () => props.lead,
  (l) => {
    if (l) {
      form.name = l.name || '';
      form.phone = l.phone || '';
      form.email = l.email || '';
      form.company_name = l.company_name || '';
      form.status = l.status || 'new';
      form.priority = l.priority || 'medium';
      form.source = l.source || '';
      form.service_interest = l.service_interest || '';
      form.additional_details = l.additional_details || '';
    }
  },
  { immediate: true }
);

const close = () => {
  if (submitting.value) return;
  emit('close');
};

const handleSubmit = async () => {
  if (!props.lead?.id) return;
  submitting.value = true;
  error.value = '';

  try {
    const res = await api.put(`/leads/${props.lead.id}`, form);
    emit('saved', res.data.lead || res.data);
    close();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to update lead details.';
  } finally {
    submitting.value = false;
  }
};
</script>
