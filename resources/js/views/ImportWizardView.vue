<template>
  <AppLayout>
    <div class="space-y-6 max-w-5xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">Data Ingestion Engine</span>
          <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">Batch Lead Import Wizard</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            Upload CSV or Excel (.xlsx, .xls) files with duplicate protection and bulk employee assignment.
          </p>
        </div>

        <button
          @click="downloadSampleCsv"
          class="px-4 py-2 bg-white border border-vb-plum/20 hover:border-vb-plum text-vb-plum rounded-full text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm self-start"
        >
          <Download class="w-3.5 h-3.5" />
          <span>Download Sample CSV Template</span>
        </button>
      </div>

      <!-- Step Indicator Bar -->
      <div class="card-glass p-4">
        <div class="grid grid-cols-4 gap-2 text-center text-xs">
          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 1 ? 'bg-vb-plum text-white font-bold shadow-sm' : step > 1 ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-vb-muted bg-white/50'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 1</span>
            <span>Upload File</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 2 ? 'bg-vb-plum text-white font-bold shadow-sm' : step > 2 ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-vb-muted bg-white/50'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 2</span>
            <span>Map Columns</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 3 ? 'bg-vb-plum text-white font-bold shadow-sm' : step > 3 ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-vb-muted bg-white/50'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 3</span>
            <span>Preview &amp; Assign</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 4 ? 'bg-vb-plum text-white font-bold shadow-sm' : 'text-vb-muted bg-white/50'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 4</span>
            <span>Import Report</span>
          </div>
        </div>
      </div>

      <!-- STEP 1: Upload File -->
      <div v-if="step === 1" class="card-glass p-8 space-y-6">
        <div
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="handleDrop"
          class="border-2 border-dashed rounded-3xl p-10 text-center transition-all cursor-pointer"
          :class="dragOver ? 'border-vb-violet bg-vb-violet/5' : 'border-vb-plum/20 hover:border-vb-plum/40 bg-white/50'"
          @click="fileInput?.click()"
        >
          <input
            ref="fileInput"
            type="file"
            accept=".csv, .xlsx, .xls"
            class="hidden"
            @change="handleFileSelect"
          />

          <div class="w-16 h-16 rounded-2xl bg-vb-cream text-vb-plum mx-auto flex items-center justify-center mb-4 shadow-sm">
            <UploadCloud class="w-8 h-8 text-vb-violet" />
          </div>

          <h3 class="font-display font-bold text-lg text-vb-plum">Select or Drag &amp; Drop Spreadsheet</h3>
          <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
            Supported formats: <strong>.csv</strong>, <strong>.xlsx</strong>, or <strong>.xls</strong> up to 10 MB.
          </p>

          <div class="mt-5 inline-flex items-center gap-2 btn-primary px-6 py-2.5 rounded-full text-xs font-semibold shadow-md">
            <FileSpreadsheet class="w-4 h-4" />
            <span>Choose File From Device</span>
          </div>
        </div>

        <div v-if="uploadError" class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
          {{ uploadError }}
        </div>
      </div>

      <!-- STEP 2: Column Mapping -->
      <div v-if="step === 2" class="card-glass p-8 space-y-6">
        <div class="border-b border-vb-plum/10 pb-4">
          <h3 class="font-display font-bold text-lg text-vb-plum">Map Spreadsheet Headers to CRM Fields</h3>
          <p class="text-xs text-vb-muted mt-0.5">
            We automatically detected matching columns from file: <strong>{{ uploadedFileName }}</strong> ({{ totalRowsFound }} rows found).
          </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 text-xs">
          <!-- Lead Name (Mandatory) -->
          <div class="p-4 rounded-xl border bg-white space-y-1.5" :class="mapping.name ? 'border-vb-plum/20' : 'border-rose-300 bg-rose-50/30'">
            <div class="flex items-center justify-between">
              <label class="font-bold text-vb-plum uppercase tracking-wider text-[11px]">
                Lead Name <span class="text-rose-500">*</span>
              </label>
              <span class="text-[10px] text-rose-500 font-semibold">Mandatory</span>
            </div>
            <select v-model="mapping.name" class="field text-xs">
              <option value="">-- Choose Column --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Phone Number (Mandatory) -->
          <div class="p-4 rounded-xl border bg-white space-y-1.5" :class="mapping.phone ? 'border-vb-plum/20' : 'border-rose-300 bg-rose-50/30'">
            <div class="flex items-center justify-between">
              <label class="font-bold text-vb-plum uppercase tracking-wider text-[11px]">
                Phone Number <span class="text-rose-500">*</span>
              </label>
              <span class="text-[10px] text-rose-500 font-semibold">Mandatory</span>
            </div>
            <select v-model="mapping.phone" class="field text-xs">
              <option value="">-- Choose Column --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Email -->
          <div class="p-4 rounded-xl border border-vb-plum/10 bg-white space-y-1.5">
            <label class="font-semibold text-vb-plum uppercase tracking-wider text-[11px]">Email Address</label>
            <select v-model="mapping.email" class="field text-xs">
              <option value="">-- Optional / Skip --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Company Name -->
          <div class="p-4 rounded-xl border border-vb-plum/10 bg-white space-y-1.5">
            <label class="font-semibold text-vb-plum uppercase tracking-wider text-[11px]">Company / Organization</label>
            <select v-model="mapping.company_name" class="field text-xs">
              <option value="">-- Optional / Skip --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Source -->
          <div class="p-4 rounded-xl border border-vb-plum/10 bg-white space-y-1.5">
            <label class="font-semibold text-vb-plum uppercase tracking-wider text-[11px]">Lead Source</label>
            <select v-model="mapping.source" class="field text-xs">
              <option value="">-- Optional / Default to "Import" --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Service Interest -->
          <div class="p-4 rounded-xl border border-vb-plum/10 bg-white space-y-1.5">
            <label class="font-semibold text-vb-plum uppercase tracking-wider text-[11px]">Service Interest</label>
            <select v-model="mapping.service_interest" class="field text-xs">
              <option value="">-- Optional / Skip --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Additional Details -->
          <div class="p-4 rounded-xl border border-vb-plum/10 bg-white space-y-1.5 sm:col-span-2">
            <label class="font-semibold text-vb-plum uppercase tracking-wider text-[11px]">Additional Scope / Notes</label>
            <select v-model="mapping.additional_details" class="field text-xs">
              <option value="">-- Optional / Skip --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-vb-plum/10">
          <button @click="step = 1" class="text-xs font-semibold text-vb-plum hover:underline">
            ← Choose Different File
          </button>
          <button
            @click="proceedToPreview"
            :disabled="!mapping.name || !mapping.phone"
            class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-md disabled:opacity-50"
          >
            <span>Preview &amp; Assignment Options</span>
            <ArrowRight class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- STEP 3: Preview & Assignment Settings -->
      <div v-if="step === 3" class="card-glass p-8 space-y-6">
        <div class="border-b border-vb-plum/10 pb-4">
          <h3 class="font-display font-bold text-lg text-vb-plum">Data Preview &amp; Lead Assignment</h3>
          <p class="text-xs text-vb-muted mt-0.5">
            Inspect sample rows and configure how imported leads should be assigned to solutions desk representatives.
          </p>
        </div>

        <!-- Sample Preview Table -->
        <div class="overflow-x-auto border border-vb-plum/10 rounded-2xl bg-white">
          <table class="w-full text-left text-xs">
            <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
              <tr>
                <th class="py-2.5 px-3">Lead Name</th>
                <th class="py-2.5 px-3">Phone</th>
                <th class="py-2.5 px-3">Email</th>
                <th class="py-2.5 px-3">Company</th>
                <th class="py-2.5 px-3">Service</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-vb-plum/5">
              <tr v-for="(row, idx) in samplePreview" :key="idx" class="hover:bg-vb-cream/30">
                <td class="py-2.5 px-3 font-semibold text-vb-plum">{{ row[mapping.name] || '—' }}</td>
                <td class="py-2.5 px-3 font-mono text-vb-ink">{{ row[mapping.phone] || '—' }}</td>
                <td class="py-2.5 px-3 text-vb-muted">{{ mapping.email ? row[mapping.email] : '—' }}</td>
                <td class="py-2.5 px-3 text-vb-ink">{{ mapping.company_name ? row[mapping.company_name] : '—' }}</td>
                <td class="py-2.5 px-3 text-vb-muted">{{ mapping.service_interest ? row[mapping.service_interest] : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Assignment & Deduplication Configuration -->
        <div class="p-6 bg-vb-cream/50 rounded-2xl border border-vb-plum/10 space-y-4">
          <h4 class="font-display font-bold text-sm text-vb-plum">Ingestion &amp; Assignment Rules</h4>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Assignment Mode -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Bulk Employee Allocation
              </label>
              <select v-model="assignmentMode" class="field text-xs">
                <option value="unassigned">Leave Unassigned</option>
                <option value="round_robin">Round-Robin (Evenly distribute across active team)</option>
                <option value="specific">Assign All to Specific Representative</option>
              </select>
            </div>

            <!-- Specific Employee Dropdown -->
            <div v-if="assignmentMode === 'specific'">
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Assignee Representative
              </label>
              <select v-model="specificAssignedTo" class="field text-xs">
                <option value="">Select Employee...</option>
                <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                  {{ emp.name }} ({{ emp.email }})
                </option>
              </select>
            </div>
          </div>

          <!-- Duplicate Strategy Checkbox -->
          <div class="pt-2">
            <label class="flex items-center gap-2.5 text-xs text-vb-ink font-semibold cursor-pointer">
              <input
                type="checkbox"
                v-model="skipDuplicates"
                class="rounded text-vb-plum focus:ring-vb-plum"
              />
              <span>Automatically skip duplicates (phones matching existing CRM leads or within the file)</span>
            </label>
            <p class="text-[11px] text-vb-muted ml-6 mt-0.5">
              Skipped duplicate records will be reported in the post-import summary and available for download.
            </p>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-vb-plum/10">
          <button @click="step = 2" class="text-xs font-semibold text-vb-plum hover:underline">
            ← Back to Mapping
          </button>
          <button
            @click="processBatchImport"
            :disabled="processingImport"
            class="btn-primary px-7 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-lg"
          >
            <span v-if="processingImport" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ processingImport ? 'Executing Batch Import...' : 'Confirm & Execute Import' }}</span>
          </button>
        </div>
      </div>

      <!-- STEP 4: Import Summary Report -->
      <div v-if="step === 4 && importReport" class="card-glass p-8 space-y-6">
        <div class="flex items-center gap-3 border-b border-vb-plum/10 pb-4">
          <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-md">
            <CheckCircle2 class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-display font-bold text-xl text-vb-plum">Import Processing Completed</h3>
            <p class="text-xs text-vb-muted">Summary audit metrics for file: {{ uploadedFileName }}</p>
          </div>
        </div>

        <!-- Metric Counters -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div class="p-4 rounded-xl bg-white border border-vb-plum/10 text-center">
            <span class="text-xs text-vb-muted uppercase tracking-wider block font-semibold">Total Rows</span>
            <span class="text-2xl font-bold text-vb-plum font-display mt-1 block">{{ importReport.total_rows }}</span>
          </div>

          <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-center">
            <span class="text-xs text-emerald-800 uppercase tracking-wider block font-semibold">Imported</span>
            <span class="text-2xl font-bold text-emerald-700 font-display mt-1 block">{{ importReport.imported_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-center">
            <span class="text-xs text-amber-800 uppercase tracking-wider block font-semibold">Duplicates</span>
            <span class="text-2xl font-bold text-amber-700 font-display mt-1 block">{{ importReport.duplicate_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-center">
            <span class="text-xs text-rose-800 uppercase tracking-wider block font-semibold">Invalid</span>
            <span class="text-2xl font-bold text-rose-700 font-display mt-1 block">{{ importReport.invalid_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-center col-span-2 sm:col-span-1">
            <span class="text-xs text-gray-700 uppercase tracking-wider block font-semibold">Skipped</span>
            <span class="text-2xl font-bold text-gray-800 font-display mt-1 block">{{ importReport.skipped_count }}</span>
          </div>
        </div>

        <!-- Failed Rows Table / Download -->
        <div v-if="importReport.failed_rows && importReport.failed_rows.length > 0" class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="font-display font-bold text-sm text-vb-plum">Failed &amp; Skipped Records Audit</h4>
            <button
              @click="downloadReportCsv(importReport.failed_rows)"
              class="text-xs font-semibold text-vb-violet hover:underline flex items-center gap-1"
            >
              <Download class="w-3.5 h-3.5" />
              <span>Download Failed Rows CSV</span>
            </button>
          </div>

          <div class="overflow-x-auto border border-vb-plum/10 rounded-2xl bg-white max-h-60 overflow-y-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold sticky top-0">
                <tr>
                  <th class="py-2 px-3">Row #</th>
                  <th class="py-2 px-3">Name</th>
                  <th class="py-2 px-3">Phone</th>
                  <th class="py-2 px-3">Failure Reason</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-vb-plum/5">
                <tr v-for="(fRow, fIdx) in importReport.failed_rows" :key="fIdx" class="hover:bg-rose-50/20">
                  <td class="py-2 px-3 font-mono font-bold text-vb-plum">Row {{ fRow.row }}</td>
                  <td class="py-2 px-3">{{ fRow.name || '—' }}</td>
                  <td class="py-2 px-3 font-mono">{{ fRow.phone || '—' }}</td>
                  <td class="py-2 px-3 text-rose-700 font-medium">{{ fRow.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-4 border-t border-vb-plum/10 flex items-center justify-end gap-3">
          <button
            @click="resetWizard"
            class="px-5 py-2.5 rounded-full text-xs font-semibold text-vb-plum border border-vb-plum/20 hover:bg-vb-cream transition-colors"
          >
            Import Another File
          </button>
          <router-link
            to="/leads"
            class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-1.5 shadow-md"
          >
            <span>View Leads Pipeline</span>
            <ArrowRight class="w-4 h-4" />
          </router-link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import {
  UploadCloud,
  FileSpreadsheet,
  Download,
  ArrowRight,
  CheckCircle2,
} from 'lucide-vue-next';

const step = ref(1);
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);
const uploadError = ref('');

const fileToken = ref('');
const uploadedFileName = ref('');
const fileHeaders = ref<string[]>([]);
const samplePreview = ref<any[]>([]);
const totalRowsFound = ref(0);

const mapping = reactive<Record<string, string>>({
  name: '',
  phone: '',
  email: '',
  company_name: '',
  source: '',
  service_interest: '',
  additional_details: '',
});

const assignmentMode = ref('unassigned');
const specificAssignedTo = ref('');
const skipDuplicates = ref(true);

const processingImport = ref(false);
const importReport = ref<any>(null);

const employeesList = ref<any[]>([]);

const fetchEmployees = async () => {
  try {
    const res = await api.get('/settings');
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

const handleDrop = (e: DragEvent) => {
  dragOver.value = false;
  if (e.dataTransfer?.files.length) {
    uploadFile(e.dataTransfer.files[0]);
  }
};

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files?.length) {
    uploadFile(target.files[0]);
  }
};

const uploadFile = async (file: File) => {
  uploadError.value = '';
  const formData = new FormData();
  formData.append('file', file);

  try {
    const res = await api.post('/import/upload-preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    fileToken.value = res.data.file_token;
    uploadedFileName.value = res.data.filename;
    fileHeaders.value = res.data.headers;
    samplePreview.value = res.data.preview;
    totalRowsFound.value = res.data.total_rows;

    // Apply auto-detected mapping
    Object.assign(mapping, res.data.detected_mapping || {});

    step.value = 2;
  } catch (err: any) {
    uploadError.value = err.response?.data?.message || 'Failed to process file. Ensure valid CSV or Excel.';
  }
};

const proceedToPreview = () => {
  if (!mapping.name || !mapping.phone) return;
  step.value = 3;
};

const processBatchImport = async () => {
  processingImport.value = true;
  try {
    const res = await api.post('/import/process', {
      file_token: fileToken.value,
      mapping: mapping,
      skip_duplicates: skipDuplicates.value,
      assignment_mode: assignmentMode.value,
      assigned_to: assignmentMode.value === 'specific' ? specificAssignedTo.value : null,
    });

    importReport.value = res.data.report;
    step.value = 4;
  } catch (err: any) {
    alert(err.response?.data?.message || 'Import failed to complete.');
  } finally {
    processingImport.value = false;
  }
};

const resetWizard = () => {
  step.value = 1;
  fileToken.value = '';
  uploadedFileName.value = '';
  fileHeaders.value = [];
  samplePreview.value = [];
  importReport.value = null;
  if (fileInput.value) fileInput.value.value = '';
};

const downloadSampleCsv = () => {
  const csvContent =
    "Name,Phone,Email,Company,Lead Source,Service Interest,Details\n" +
    "Suresh Gowda,+91 98800 12345,suresh@gowdatech.in,Gowda Tech,Website,IT Infrastructure & Hardware AMC,Needs comprehensive 24/7 workstation AMC\n" +
    "Meera Pillai,+91 97420 54321,meera@pillairesorts.com,Pillai Luxury Resorts,Referral,Hospitality Management,Looking for central hotel PMS & management consultation\n" +
    "Arun Verma,9916012345,arun@vermacommerce.com,Verma Commerce,WhatsApp Inbound,Retail & Commerce Solutions,Expanding omnichannel retail billing across 4 stores";

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = "vistabound_leads_sample.csv";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};

const downloadReportCsv = (failedRows: any[]) => {
  let csv = "Row Number,Lead Name,Phone,Failure Reason\n";
  failedRows.forEach((r) => {
    csv += `"${r.row}","${(r.name || '').replace(/"/g, '""')}","${r.phone || ''}","${(r.reason || '').replace(/"/g, '""')}"\n`;
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `vistabound_failed_import_${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};

onMounted(() => {
  fetchEmployees();
});
</script>
