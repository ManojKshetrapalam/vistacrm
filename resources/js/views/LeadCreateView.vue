<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-xs text-vb-muted">
        <router-link to="/leads" class="hover:text-vb-plum">Leads</router-link>
        <span>/</span>
        <span class="text-vb-plum font-semibold">New Lead</span>
      </div>

      <div class="card-glass p-6 sm:p-8">
        <div class="border-b border-vb-plum/10 pb-5 mb-6">
          <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">Intake Form</span>
          <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">Register New Lead Account</h2>
          <p class="text-xs text-vb-muted mt-1">
            Fill in client contact information. Phone number will be normalized for WhatsApp compatibility and duplicate checks.
          </p>
        </div>

        <!-- Duplicate Warning Alert -->
        <div
          v-if="duplicateLead"
          class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs space-y-2 animate-in fade-in"
        >
          <div class="flex items-center gap-2 font-bold text-sm text-amber-950">
            <AlertTriangle class="w-4 h-4 text-amber-600" />
            <span>Duplicate Phone Detected in CRM</span>
          </div>
          <p>
            An existing lead <strong>{{ duplicateLead.name }}</strong> (Ref: <router-link :to="`/leads/${duplicateLead.id}`" class="underline font-bold">{{ duplicateLead.reference_id }}</router-link>) with company <em>{{ duplicateLead.company_name || 'N/A' }}</em> is already registered to representative <strong>{{ duplicateLead.assigned_to_name }}</strong>.
          </p>
          <label class="flex items-center gap-2 pt-1 font-semibold text-amber-950 cursor-pointer">
            <input
              type="checkbox"
              v-model="confirmDuplicate"
              class="rounded text-vb-plum focus:ring-vb-plum"
            />
            <span>I confirm this is a separate inquiry. Create record anyway.</span>
          </label>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Lead Name -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Lead / Contact Name <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                placeholder="e.g. Ramesh Hegde"
                class="field text-sm"
              />
            </div>

            <!-- Phone Number -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Mobile / WhatsApp Phone <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.phone"
                type="tel"
                required
                placeholder="e.g. +91 98450 12345"
                class="field text-sm"
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
                placeholder="client@organization.com"
                class="field text-sm"
              />
            </div>

            <!-- Company Name -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Company / Organization Name
              </label>
              <input
                v-model="form.company_name"
                type="text"
                placeholder="e.g. Acme Tech Solutions"
                class="field text-sm"
              />
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Lead Source -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Lead Source
              </label>
              <select v-model="form.source" class="field text-sm">
                <option v-for="src in leadSources" :key="src" :value="src">{{ src }}</option>
              </select>
            </div>

            <!-- Service Interest -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Service or Product Interest
              </label>
              <select v-model="form.service_interest" class="field text-sm">
                <option value="">Select Primary Interest...</option>
                <option v-for="cat in serviceCategories" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>
          </div>

          <div class="grid sm:grid-cols-3 gap-4">
            <!-- Status -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Initial Status
              </label>
              <select v-model="form.status" class="field text-sm">
                <option value="new">New</option>
                <option value="assigned">Assigned</option>
                <option value="contacted">Contacted</option>
                <option value="follow_up">Follow-up</option>
                <option value="interested">Interested</option>
              </select>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Priority Level
              </label>
              <select v-model="form.priority" class="field text-sm">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>

            <!-- Assigned Representative (Admin Only) -->
            <div v-if="authStore.isAdmin">
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Assign Representative
              </label>
              <select v-model="form.assigned_to" class="field text-sm">
                <option value="">Leave Unassigned</option>
                <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                  {{ emp.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Next Follow-up Date (Optional) -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Schedule Initial Follow-up (Optional)
            </label>
            <input
              v-model="form.next_follow_up_date"
              type="datetime-local"
              class="field text-sm max-w-sm"
            />
          </div>

          <!-- Additional Details -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Inquiry Scope &amp; Background Details
            </label>
            <textarea
              v-model="form.additional_details"
              rows="4"
              class="field text-sm font-sans resize-none"
              placeholder="Client requirements, budget range, specific pain points, or notes from initial consultation..."
            ></textarea>
          </div>

          <div v-if="errorMsg" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
            {{ errorMsg }}
          </div>

          <!-- Form Buttons -->
          <div class="pt-4 border-t border-vb-plum/10 flex items-center justify-end gap-3">
            <router-link
              to="/leads"
              class="px-5 py-2.5 rounded-full text-xs font-semibold text-vb-plum hover:bg-vb-plum/5 transition-colors"
            >
              Cancel
            </router-link>
            <button
              type="submit"
              :disabled="submitting"
              class="btn-primary text-xs font-semibold px-6 py-2.5 rounded-full flex items-center gap-2 shadow-md"
            >
              <Check class="w-4 h-4" />
              <span>{{ submitting ? 'Saving...' : 'Create Lead Record' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import { AlertTriangle, Check } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();

const leadSources = ref<string[]>(['Website', 'WhatsApp Inbound', 'LinkedIn', 'Referral', 'Cold Outreach']);
const serviceCategories = ref<string[]>([]);
const employeesList = ref<any[]>([]);

const submitting = ref(false);
const errorMsg = ref('');
const duplicateLead = ref<any>(null);
const confirmDuplicate = ref(false);

const form = reactive({
  name: '',
  phone: '',
  email: '',
  company_name: '',
  source: 'Website',
  service_interest: '',
  status: 'new',
  priority: 'medium',
  assigned_to: '',
  next_follow_up_date: '',
  additional_details: '',
});

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    leadSources.value = res.data.lead_sources || [];
    serviceCategories.value = res.data.service_categories || [];
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

const handleSubmit = async () => {
  submitting.value = true;
  errorMsg.value = '';
  duplicateLead.value = null;

  try {
    const payload: any = {
      name: form.name,
      phone: form.phone,
      email: form.email || null,
      company_name: form.company_name || null,
      source: form.source,
      service_interest: form.service_interest || null,
      status: form.status,
      priority: form.priority,
      assigned_to: form.assigned_to || null,
      next_follow_up_date: form.next_follow_up_date || null,
      additional_details: form.additional_details || null,
      confirm_duplicate: confirmDuplicate.value,
    };

    const res = await api.post('/leads', payload);
    router.push(`/leads/${res.data.lead.id}`);
  } catch (err: any) {
    if (err.response?.status === 409 && err.response?.data?.duplicate) {
      duplicateLead.value = err.response.data.existing_lead;
      errorMsg.value = err.response.data.message;
    } else {
      errorMsg.value = err.response?.data?.message || 'Failed to create lead.';
    }
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  fetchSettings();
});
</script>
